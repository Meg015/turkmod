<?php

declare(strict_types=1);

namespace App\Engine\Users;

use DomainException;
use PDO;
use Throwable;

/** Database changes only; files and caches are handled after the caller commits. */
final class UserDeletionService
{
    private const SYSTEM_EMAIL = 'deleted-user@system.invalid';
    private const SYSTEM_NAME = 'Silinmiş Kullanıcı';

    /** @var array<string, list<string>> */
    private array $columns = [];

    /** @return array{topics:int, comments:int, replacement_user_id:int, file_paths:list<string>} */
    public function delete(PDO $pdo, int $userId, int $actorId, string $mode, string $confirmation, string $reason): array
    {
        if ($userId <= 0 || $actorId <= 0 || !in_array($mode, ['preserve', 'purge'], true)) {
            throw new DomainException('Geçersiz kullanıcı veya içerik seçeneği.');
        }
        if ($userId === $actorId) {
            throw new DomainException('Kendi hesabınızı silemezsiniz.');
        }
        if (!userHasPermission($pdo, $actorId, 'users.delete')) {
            throw new DomainException('Kullanıcı silme yetkiniz yok.');
        }
        if ($mode === 'purge' && (!userHasPermission($pdo, $actorId, 'topics.delete') || !userHasPermission($pdo, $actorId, 'comments.delete'))) {
            throw new DomainException('İçerikleri de silmek için konu ve yorum silme yetkileri gereklidir.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new DomainException('Silme gerekçesi 1 ile 1000 karakter arasında olmalıdır.');
        }

        $this->columns = [];
        $ownsTransaction = !$pdo->inTransaction();
        $ownsTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT user_account_delete');
        try {
            $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $actor = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL AND is_banned = 0" . $lock);
            $actor->execute([$actorId]);
            if (!$actor->fetchColumn()) {
                throw new DomainException('İşlem için aktif bir yönetici hesabı gereklidir.');
            }
            $select = $pdo->prepare('SELECT * FROM users WHERE id = ?' . $lock);
            $select->execute([$userId]);
            $user = $select->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                throw new DomainException('Kullanıcı bulunamadı veya zaten silinmiş.');
            }
            if ($user['email'] === self::SYSTEM_EMAIL || $user['username'] === self::SYSTEM_NAME) {
                throw new DomainException('Silinmiş Kullanıcı sistem hesabı silinemez.');
            }
            if (!empty($user['deleted_at'])) {
                throw new DomainException('Silinmiş bir hesap üzerinde işlem yapılamaz.');
            }
            if (trim($confirmation) !== (string) $user['username']) {
                throw new DomainException('Onay için kullanıcı adını eksiksiz yazın.');
            }

            $replacementId = $this->replacementUser($pdo);
            $topicIds = $this->ids($pdo, 'SELECT id FROM topics WHERE author_id = ?' . $lock, [$userId]);
            $reactionIds = $this->has($pdo, 'comment_reactions', 'user_id')
                ? $this->ids($pdo, 'SELECT DISTINCT comment_id FROM comment_reactions WHERE user_id = ?', [$userId]) : [];
            $mentionIds = $this->has($pdo, 'comment_mentions', 'mentioned_user_id')
                ? $this->ids($pdo, 'SELECT DISTINCT comment_id FROM comment_mentions WHERE mentioned_user_id = ? OR mentioner_user_id = ?', [$userId, $userId]) : [];
            $affectedUsers = [$replacementId];
            if ($reactionIds !== []) {
                foreach (array_chunk($reactionIds, 400) as $chunk) {
                    $affectedUsers = array_merge($affectedUsers, $this->ids($pdo, 'SELECT DISTINCT user_id FROM comments WHERE id IN (' . $this->marks($chunk) . ') AND user_id IS NOT NULL', $chunk));
                }
            }
            $threadIds = $this->has($pdo, 'message_thread_participants', 'user_id')
                ? $this->ids($pdo, 'SELECT thread_id FROM message_thread_participants WHERE user_id = ?', [$userId]) : [];
            $paths = empty($user['avatar']) ? [] : [(string) $user['avatar']];

            if ($mode === 'preserve') {
                $count = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ?');
                $count->execute([$userId]);
                $commentCount = (int) $count->fetchColumn();
                $pdo->prepare('UPDATE topics SET author_id = ? WHERE author_id = ?')->execute([$replacementId, $userId]);
                $pdo->prepare('UPDATE comments SET user_id = ? WHERE user_id = ?')->execute([$replacementId, $userId]);
            } else {
                $select = $pdo->prepare('SELECT c.id, c.topic_id, c.user_id, c.status, c.deleted_at FROM comments c JOIN topics t ON t.id = c.topic_id WHERE c.user_id = ? OR t.author_id = ? ORDER BY c.id' . $lock);
                $select->execute([$userId, $userId]);
                $comments = $select->fetchAll(PDO::FETCH_ASSOC);
                $commentCount = count($comments);
                $commentIds = array_map('intval', array_column($comments, 'id'));
                $affectedTopics = array_values(array_unique(array_map('intval', array_column($comments, 'topic_id'))));
                $affectedUsers = array_merge($affectedUsers, array_filter(array_map('intval', array_column($comments, 'user_id'))));

                $paths = array_merge($paths, $this->filePaths($pdo, 'media_files', 'path', 'topic_id', $topicIds), $this->filePaths($pdo, 'comment_media', 'file_path', 'comment_id', $commentIds));
                foreach ($comments as $comment) {
                    if ((int) $comment['user_id'] !== $userId && (int) $comment['user_id'] > 0 && $comment['status'] === 'approved' && empty($comment['deleted_at']) && function_exists('eventsReverseActivityPoints')) {
                        $reversal = eventsReverseActivityPoints($pdo, (int) $comment['user_id'], 'comment_created', 'comment', (int) $comment['id'], 'comment_deleted');
                        if (($reversal['skipped'] ?? '') === 'error') {
                            throw new DomainException('Silinen yorumun etkinlik puanı geri alınamadı.');
                        }
                    }
                }
                // Preserve other people's replies on surviving topics before the parent FK cascades.
                foreach (array_chunk($commentIds, 400) as $chunk) {
                    $pdo->prepare('UPDATE comments SET parent_id = NULL WHERE parent_id IN (' . $this->marks($chunk) . ') AND (user_id IS NULL OR user_id <> ?)')->execute([...$chunk, $userId]);
                }
                $this->resetImports($pdo, $topicIds);
                $pdo->prepare('DELETE FROM comments WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM topics WHERE author_id = ?')->execute([$userId]);
                $this->refreshTopicCounts($pdo, $affectedTopics);
            }

            // These records may belong to content that survives in either mode.
            foreach (['comment_media', 'comment_edit_history', 'media_files'] as $table) {
                if ($this->has($pdo, $table, 'user_id')) {
                    $pdo->prepare("UPDATE {$table} SET user_id = ? WHERE user_id = ?")->execute([$replacementId, $userId]);
                }
            }
            $this->removeUnconstrainedReferences($pdo, $userId);
            $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $delete->execute([$userId]);
            if ($delete->rowCount() !== 1) {
                throw new DomainException('Kullanıcı kalıcı olarak silinemedi.');
            }
            $this->refreshReactionCounts($pdo, $reactionIds);
            $this->refreshMentionCounts($pdo, $mentionIds);
            $this->refreshUserCounts($pdo, array_values(array_unique($affectedUsers)));
            $this->refreshMessageThreads($pdo, $threadIds);
            if ($this->has($pdo, 'leaderboard_cache', 'user_id')) {
                $pdo->exec('DELETE FROM leaderboard_cache');
            }
            $result = ['topics' => count($topicIds), 'comments' => $commentCount, 'replacement_user_id' => $replacementId, 'file_paths' => array_values(array_unique(array_filter($paths)))];
            $this->audit($pdo, $userId, $actorId, $mode, $reason, $result);
            $ownsTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT user_account_delete');
            return $result;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                } else {
                    $pdo->exec('ROLLBACK TO SAVEPOINT user_account_delete');
                    $pdo->exec('RELEASE SAVEPOINT user_account_delete');
                }
            }
            throw $error;
        }
    }

    private function replacementUser(PDO $pdo): int
    {
        // The unique email serializes concurrent first-use creation without replacing an account.
        $insert = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $pdo->prepare("{$insert} INTO users (username, email, password, status, deleted_at, public_profile, public_show_topics, public_show_comments, public_show_socials, created_at, updated_at) VALUES (?, ?, '!', 'inactive', CURRENT_TIMESTAMP, 0, 0, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)")
            ->execute([self::SYSTEM_NAME, self::SYSTEM_EMAIL]);
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $find = $pdo->prepare('SELECT id, username, password, status, deleted_at FROM users WHERE email = ?' . $lock);
        $find->execute([self::SYSTEM_EMAIL]);
        $user = $find->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            if ($user['username'] !== self::SYSTEM_NAME || $user['password'] !== '!' || $user['status'] !== 'inactive' || empty($user['deleted_at'])) {
                throw new DomainException('Silinmiş Kullanıcı için ayrılan sistem hesabı başka bir hesapla çakışıyor.');
            }
            return (int) $user['id'];
        }
        throw new DomainException('Silinmiş Kullanıcı sistem hesabı oluşturulamadı.');
    }

    private function removeUnconstrainedReferences(PDO $pdo, int $userId): void
    {
        if ($this->has($pdo, 'ban_appeals', 'user_id') && $this->has($pdo, 'ban_appeal_messages', 'appeal_id')) {
            $pdo->prepare('DELETE FROM ban_appeal_messages WHERE appeal_id IN (SELECT id FROM ban_appeals WHERE user_id = ?)')->execute([$userId]);
        }
        foreach (['user_group_members', 'user_group_logs', 'ban_appeals', 'events_email_queue', 'bulk_email_recipients'] as $table) {
            if ($this->has($pdo, $table, 'user_id')) {
                $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
            }
        }
        foreach ([['user_group_members', 'assigned_by'], ['user_group_logs', 'changed_by'], ['ban_appeals', 'reviewed_by'], ['security_events', 'user_id'], ['email_logs', 'user_id'], ['notifications', 'actor_user_id'], ['contact_messages', 'admin_reply_admin_id']] as [$table, $column]) {
            if ($this->has($pdo, $table, $column)) {
                $pdo->prepare("UPDATE {$table} SET {$column} = NULL WHERE {$column} = ?")->execute([$userId]);
            }
        }
    }

    /** @param list<int> $topicIds */
    private function resetImports(PDO $pdo, array $topicIds): void
    {
        if (!$this->has($pdo, 'bot_imports', 'topic_id')) { return; }
        foreach (array_chunk($topicIds, 400) as $chunk) {
            $select = $pdo->prepare('SELECT bot_site_id FROM bot_imports WHERE status = ? AND topic_id IN (' . $this->marks($chunk) . ')');
            $select->execute(['imported', ...$chunk]);
            foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $siteId) {
                if ($this->has($pdo, 'bot_sites', 'total_imports')) {
                    $pdo->prepare('UPDATE bot_sites SET total_imports = CASE WHEN total_imports > 0 THEN total_imports - 1 ELSE 0 END WHERE id = ?')->execute([(int) $siteId]);
                }
            }
            $pdo->prepare("UPDATE bot_imports SET topic_id = NULL, status = 'preview', updated_at = CURRENT_TIMESTAMP WHERE topic_id IN (" . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    /** @param list<int> $ids */
    private function refreshTopicCounts(PDO $pdo, array $ids): void
    {
        foreach (array_chunk($ids, 400) as $chunk) {
            $pdo->prepare("UPDATE topics SET comment_count = (SELECT COUNT(*) FROM comments c WHERE c.topic_id = topics.id AND c.status = 'approved' AND c.deleted_at IS NULL) WHERE id IN (" . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    /** @param list<int> $ids */
    private function refreshReactionCounts(PDO $pdo, array $ids): void
    {
        if (!$this->has($pdo, 'comments', 'reaction_count') || !$this->has($pdo, 'comment_reactions', 'comment_id')) { return; }
        foreach (array_chunk($ids, 400) as $chunk) {
            $pdo->prepare('UPDATE comments SET reaction_count = (SELECT COUNT(*) FROM comment_reactions r WHERE r.comment_id = comments.id) WHERE id IN (' . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    /** @param list<int> $ids */
    private function refreshMentionCounts(PDO $pdo, array $ids): void
    {
        if (!$this->has($pdo, 'comments', 'mention_count')) { return; }
        foreach (array_chunk($ids, 400) as $chunk) {
            $pdo->prepare('UPDATE comments SET mention_count = (SELECT COUNT(*) FROM comment_mentions m WHERE m.comment_id = comments.id) WHERE id IN (' . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    /** @param list<int> $ids */
    private function refreshUserCounts(PDO $pdo, array $ids): void
    {
        $updates = [];
        foreach ([
            'total_topics' => "(SELECT COUNT(*) FROM topics t WHERE t.author_id = users.id AND t.status = 'published' AND t.deleted_at IS NULL)",
            'total_comments' => "(SELECT COUNT(*) FROM comments c WHERE c.user_id = users.id AND c.status = 'approved' AND c.deleted_at IS NULL)",
            'total_downloads' => "(SELECT COALESCE(SUM(t.download_count), 0) FROM topics t WHERE t.author_id = users.id AND t.status = 'published' AND t.deleted_at IS NULL)",
        ] as $column => $expression) {
            if ($this->has($pdo, 'users', $column)) { $updates[] = $column . ' = ' . $expression; }
        }
        if ($this->has($pdo, 'users', 'helpful_count') && $this->has($pdo, 'comment_reactions', 'reaction_type')) {
            $updates[] = "helpful_count = (SELECT COUNT(*) FROM comment_reactions r JOIN comments c ON c.id = r.comment_id WHERE c.user_id = users.id AND r.reaction_type = 'helpful' AND c.deleted_at IS NULL AND c.status = 'approved')";
        }
        if ($updates === []) { return; }
        foreach (array_chunk($ids, 400) as $chunk) {
            $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id IN (' . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    /** @param list<int> $ids */
    private function refreshMessageThreads(PDO $pdo, array $ids): void
    {
        if (!$this->has($pdo, 'message_threads', 'last_message_id') || !$this->has($pdo, 'message_messages', 'thread_id')) { return; }
        foreach (array_chunk($ids, 400) as $chunk) {
            $pdo->prepare('UPDATE message_threads SET last_message_id = (SELECT MAX(m.id) FROM message_messages m WHERE m.thread_id = message_threads.id), last_message_at = (SELECT MAX(m.created_at) FROM message_messages m WHERE m.thread_id = message_threads.id) WHERE id IN (' . $this->marks($chunk) . ')')->execute($chunk);
        }
    }

    private function audit(PDO $pdo, int $userId, int $actorId, string $mode, string $reason, array $result): void
    {
        if (!$this->has($pdo, 'activity_logs', 'action') && !$this->has($pdo, 'admin_action_log', 'action_type')) {
            throw new DomainException('Silme işlemi için günlük tablosu bulunamadı. Veritabanı eşitlemesini çalıştırın.');
        }
        $details = ['content_mode' => $mode, 'topics' => $result['topics'], 'comments' => $result['comments'], 'replacement_user_id' => $result['replacement_user_id']];
        if ($this->has($pdo, 'activity_logs', 'action')) {
            $pdo->prepare("INSERT INTO activity_logs (actor_id, action, subject_type, subject_id, properties, created_at, updated_at) VALUES (?, 'user_deleted_permanently', 'user', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)")
                ->execute([$actorId, $userId, json_encode(['reason' => $reason] + $details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }
        if ($this->has($pdo, 'admin_action_log', 'action_type')) {
            $pdo->prepare("INSERT INTO admin_action_log (actor_id, action_type, target_type, target_id, reason, new_value, is_reversible, created_at) VALUES (?, 'delete', 'user', ?, ?, ?, 0, CURRENT_TIMESTAMP)")
                ->execute([$actorId, $userId, $reason, json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }
    }

    private function has(PDO $pdo, string $table, string $column): bool
    {
        if (!array_key_exists($table, $this->columns)) {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $this->columns[$table] = array_column($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC), 'name');
            } else {
                $select = $pdo->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $select->execute([$table]);
                $this->columns[$table] = $select->fetchAll(PDO::FETCH_COLUMN);
            }
        }
        return in_array($column, $this->columns[$table], true);
    }

    /** @return list<int> */
    private function ids(PDO $pdo, string $sql, array $params): array
    {
        $select = $pdo->prepare($sql);
        $select->execute($params);
        return array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));
    }

    private function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** @param list<int> $ids @return list<string> */
    private function filePaths(PDO $pdo, string $table, string $column, string $foreignKey, array $ids): array
    {
        if (!$this->has($pdo, $table, $column)) { return []; }
        $paths = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $select = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$foreignKey} IN (" . $this->marks($chunk) . ')');
            $select->execute($chunk);
            $paths = array_merge($paths, $select->fetchAll(PDO::FETCH_COLUMN));
        }
        return $paths;
    }
}
