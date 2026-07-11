<?php

declare(strict_types=1);

namespace App\Modules\Messages\Services;

use PDO;
use Throwable;

final class MessageService
{
    private const SCHEMA_UNAVAILABLE_MESSAGE = 'Mesajlasma altyapisi henuz hazir degil. Lutfen yoneticiyle iletisime gecin.';

    /** @var callable|null */
    private $notificationDispatcher;

    /** @var array<int,bool> */
    private array $schemaReadyByConnection = [];

    /** @var array<int,bool> */
    private array $typingColumnByConnection = [];

    public function __construct(
        private ?MessageSchemaService $schema = null,
        ?callable $notificationDispatcher = null,
    ) {
        $this->schema ??= new MessageSchemaService();
        $this->notificationDispatcher = $notificationDispatcher;
    }

    public function ensureSchema(PDO $pdo, bool $respectRuntimeGate = true): void
    {
        $key = spl_object_id($pdo);

        try {
            $this->schema->ensureSchema($pdo, $respectRuntimeGate);
        } catch (Throwable) {
            // Graceful fallback: callers check readiness before any table query.
        }

        $this->schemaReadyByConnection[$key] = $this->hasRequiredTables($pdo);
    }

    public function isSchemaReady(PDO $pdo, bool $attemptEnsure = true): bool
    {
        $key = spl_object_id($pdo);
        if ($attemptEnsure) {
            $this->ensureSchema($pdo, true);
        }

        if (!array_key_exists($key, $this->schemaReadyByConnection)) {
            $this->schemaReadyByConnection[$key] = $this->hasRequiredTables($pdo);
        }

        return $this->schemaReadyByConnection[$key];
    }

    public function unavailableMessage(): string
    {
        return self::SCHEMA_UNAVAILABLE_MESSAGE;
    }

    public function canonicalThreadKey(int $leftUserId, int $rightUserId): string
    {
        $low = min($leftUserId, $rightUserId);
        $high = max($leftUserId, $rightUserId);

        return $low . ':' . $high;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function threadForUser(PDO $pdo, int $userId, int $threadId, string $baseUri = ''): ?array
    {
        if ($userId <= 0 || $threadId <= 0) {
            return null;
        }

        if (!$this->isSchemaReady($pdo)) {
            return null;
        }

        $typingSelect = $this->participantTypingSelectSql($pdo, 'other_p');
        $otherUserNameSql = $this->userDisplayNameSql($pdo, 'u');
        $otherUserAvatarSql = $this->userColumnSql($pdo, 'avatar', 'u', "''");

        try {
            $stmt = $pdo->prepare("
            SELECT
                t.id AS thread_id,
                t.thread_key,
                t.last_message_id,
                t.last_message_at,
                t.created_at AS thread_created_at,
                self_p.last_read_message_id AS self_last_read_message_id,
                other_p.user_id AS with_user_id,
                other_p.last_read_message_id AS with_last_read_message_id,
                {$typingSelect} AS with_typing_at,
                other_p.last_read_at AS with_last_read_at,
                {$otherUserNameSql} AS with_user_name,
                {$otherUserAvatarSql} AS with_user_avatar,
                lm.sender_user_id AS last_sender_user_id,
                lm.body AS last_message_body,
                lm.created_at AS last_message_created_at,
                (
                    SELECT COUNT(*)
                    FROM message_messages mm
                    WHERE mm.thread_id = t.id
                      AND mm.sender_user_id <> :user_id_unread
                      AND (self_p.last_read_message_id IS NULL OR mm.id > self_p.last_read_message_id)
                ) AS unread_count
            FROM message_thread_participants self_p
            INNER JOIN message_threads t ON t.id = self_p.thread_id
            INNER JOIN message_thread_participants other_p ON other_p.thread_id = t.id AND other_p.user_id <> self_p.user_id
            LEFT JOIN users u ON u.id = other_p.user_id
            LEFT JOIN message_messages lm ON lm.id = t.last_message_id
            WHERE self_p.user_id = :user_id
              AND t.id = :thread_id
            LIMIT 1
        ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id_unread', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => 'MessageService::threadForUser', 'thread_id' => $threadId, 'user_id' => $userId]);
            }

            return null;
        }

        return $this->decorateThreadRow($row, $userId, $baseUri);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listThreads(PDO $pdo, int $userId, int $limit = 50, string $baseUri = ''): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (!$this->isSchemaReady($pdo)) {
            return [];
        }
        $limit = max(1, min(100, $limit));

        $typingSelect = $this->participantTypingSelectSql($pdo, 'other_p');
        $otherUserNameSql = $this->userDisplayNameSql($pdo, 'u');
        $otherUserAvatarSql = $this->userColumnSql($pdo, 'avatar', 'u', "''");

        try {
            $stmt = $pdo->prepare("
            SELECT
                t.id AS thread_id,
                t.thread_key,
                t.last_message_id,
                t.last_message_at,
                t.created_at AS thread_created_at,
                self_p.last_read_message_id AS self_last_read_message_id,
                other_p.user_id AS with_user_id,
                other_p.last_read_message_id AS with_last_read_message_id,
                {$typingSelect} AS with_typing_at,
                other_p.last_read_at AS with_last_read_at,
                {$otherUserNameSql} AS with_user_name,
                {$otherUserAvatarSql} AS with_user_avatar,
                lm.sender_user_id AS last_sender_user_id,
                lm.body AS last_message_body,
                lm.created_at AS last_message_created_at,
                (
                    SELECT COUNT(*)
                    FROM message_messages mm
                    WHERE mm.thread_id = t.id
                      AND mm.sender_user_id <> :user_id_unread
                      AND (self_p.last_read_message_id IS NULL OR mm.id > self_p.last_read_message_id)
                ) AS unread_count
            FROM message_thread_participants self_p
            INNER JOIN message_threads t ON t.id = self_p.thread_id
            INNER JOIN message_thread_participants other_p ON other_p.thread_id = t.id AND other_p.user_id <> self_p.user_id
            LEFT JOIN users u ON u.id = other_p.user_id
            LEFT JOIN message_messages lm ON lm.id = t.last_message_id
            WHERE self_p.user_id = :user_id
            ORDER BY COALESCE(t.last_message_at, t.created_at) DESC, t.id DESC
            LIMIT :limit
        ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id_unread', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => 'MessageService::listThreads', 'user_id' => $userId]);
            }

            return [];
        }

        $threads = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $threads[] = $this->decorateThreadRow($row, $userId, $baseUri);
        }

        return $threads;
    }

    /**
     * @return array<string,mixed>
     */
    public function dropdownPayload(PDO $pdo, int $userId, int $limit = 6, string $baseUri = ''): array
    {
        if (!$this->isSchemaReady($pdo)) {
            return [
                'ok' => false,
                'message' => self::SCHEMA_UNAVAILABLE_MESSAGE,
                'unread_count' => 0,
                'latest' => [],
            ];
        }

        return [
            'ok' => true,
            'unread_count' => $this->unreadCount($pdo, $userId),
            'latest' => $this->listThreads($pdo, $userId, $limit, $baseUri),
        ];
    }

    public function unreadCount(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if (!$this->isSchemaReady($pdo)) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(unread_per_thread), 0) AS unread_total
            FROM (
                SELECT
                    (
                        SELECT COUNT(*)
                        FROM message_messages mm
                        WHERE mm.thread_id = self_p.thread_id
                          AND mm.sender_user_id <> :user_id_unread
                          AND (self_p.last_read_message_id IS NULL OR mm.id > self_p.last_read_message_id)
                    ) AS unread_per_thread
                FROM message_thread_participants self_p
                WHERE self_p.user_id = :user_id
            ) unread_counts
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id_unread', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function openThread(PDO $pdo, int $userId, int $threadId, string $baseUri = ''): ?array
    {
        if ($userId <= 0 || $threadId <= 0) {
            return null;
        }

        if (!$this->isSchemaReady($pdo)) {
            return null;
        }

        $thread = $this->threadForUser($pdo, $userId, $threadId, $baseUri);
        if ($thread === null) {
            return null;
        }

        $markedUnreadCount = $this->markThreadReadOnOpen($pdo, $threadId, $userId);
        $thread = $this->threadForUser($pdo, $userId, $threadId, $baseUri);
        if ($thread === null) {
            return null;
        }

        $messages = $this->fetchThreadMessages($pdo, $threadId, 300);
        $withCursor = isset($thread['with_last_read_message_id']) ? (int) $thread['with_last_read_message_id'] : 0;
        $withReadAt = (string) ($thread['with_last_read_at'] ?? '');

        return [
            'thread' => $thread,
            'messages' => $this->decorateMessages($messages, $userId, $withCursor, $withReadAt),
            'marked_unread_count' => $markedUnreadCount,
        ];
    }

    /**
     * @return array{success:bool,message:string,thread_id:int,message_id:int}
     */
    public function sendMessage(PDO $pdo, int $senderUserId, int $targetUserId, string $body, string $baseUri = ''): array
    {
        if ($senderUserId <= 0) {
            return ['success' => false, 'message' => 'Mesaj gonderebilmek icin giris yapmalisiniz.', 'thread_id' => 0, 'message_id' => 0];
        }

        if ($targetUserId <= 0) {
            return ['success' => false, 'message' => 'Gecerli bir kullanici secilmedi.', 'thread_id' => 0, 'message_id' => 0];
        }

        if ($senderUserId === $targetUserId) {
            return ['success' => false, 'message' => 'Kendinize mesaj gonderemezsiniz.', 'thread_id' => 0, 'message_id' => 0];
        }

        if (!$this->isSchemaReady($pdo)) {
            return ['success' => false, 'message' => self::SCHEMA_UNAVAILABLE_MESSAGE, 'thread_id' => 0, 'message_id' => 0];
        }

        $targetUser = $this->lookupUser($pdo, $targetUserId);
        if (!$this->isUserMessageEligible($targetUser)) {
            return ['success' => false, 'message' => 'Mesaj gonderilecek kullanici su anda uygun degil.', 'thread_id' => 0, 'message_id' => 0];
        }

        $threadId = $this->getOrCreateThreadByUser($pdo, $senderUserId, $targetUserId);
        if ($threadId <= 0) {
            return ['success' => false, 'message' => 'Sohbet acilamadi.', 'thread_id' => 0, 'message_id' => 0];
        }

        return $this->persistMessage($pdo, $senderUserId, $targetUserId, $threadId, $body, $baseUri);
    }

    /**
     * @return array{success:bool,message:string,thread_id:int,message_id:int}
     */
    public function sendMessageToThread(PDO $pdo, int $senderUserId, int $threadId, string $body, string $baseUri = ''): array
    {
        if ($senderUserId <= 0) {
            return ['success' => false, 'message' => 'Mesaj gonderebilmek icin giris yapmalisiniz.', 'thread_id' => 0, 'message_id' => 0];
        }

        if (!$this->isSchemaReady($pdo)) {
            return ['success' => false, 'message' => self::SCHEMA_UNAVAILABLE_MESSAGE, 'thread_id' => 0, 'message_id' => 0];
        }

        $thread = $this->threadForUser($pdo, $senderUserId, $threadId, $baseUri);
        if ($thread === null) {
            return ['success' => false, 'message' => 'Sohbet bulunamadi.', 'thread_id' => 0, 'message_id' => 0];
        }

        $targetUserId = (int) ($thread['with_user_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === $senderUserId) {
            return ['success' => false, 'message' => 'Sohbet katilimcisi gecersiz.', 'thread_id' => 0, 'message_id' => 0];
        }

        $targetUser = $this->lookupUser($pdo, $targetUserId);
        if (!$this->isUserMessageEligible($targetUser)) {
            return ['success' => false, 'message' => 'Mesaj gonderilecek kullanici su anda uygun degil.', 'thread_id' => 0, 'message_id' => 0];
        }

        return $this->persistMessage($pdo, $senderUserId, $targetUserId, $threadId, $body, $baseUri);
    }

    public function getOrCreateThreadByUser(PDO $pdo, int $userId, int $otherUserId): int
    {
        if ($userId <= 0 || $otherUserId <= 0 || $userId === $otherUserId) {
            return 0;
        }

        if (!$this->isSchemaReady($pdo)) {
            return 0;
        }

        $threadKey = $this->canonicalThreadKey($userId, $otherUserId);
        $existingId = $this->threadIdByKey($pdo, $threadKey);
        if ($existingId > 0) {
            $this->ensureParticipants($pdo, $existingId, $userId, $otherUserId);

            return $existingId;
        }

        $nowSql = $this->schema->nowSql($pdo);
        $insertSql = "INSERT INTO message_threads (thread_key, created_at, updated_at) VALUES (:thread_key, {$nowSql}, {$nowSql})";

        try {
            $ownsTransaction = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownsTransaction = true;
            }

            try {
                $insert = $pdo->prepare($insertSql);
                $insert->execute(['thread_key' => $threadKey]);
            } catch (Throwable) {
                // Duplicate thread_key can happen under race conditions; read existing id below.
            }

            $threadId = $this->threadIdByKey($pdo, $threadKey);
            if ($threadId > 0) {
                $this->ensureParticipants($pdo, $threadId, $userId, $otherUserId);
            }

            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return $threadId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return 0;
        }
    }

    public function markAllRead(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if (!$this->isSchemaReady($pdo)) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT
                self_p.thread_id,
                self_p.last_read_message_id,
                MAX(CASE WHEN mm.sender_user_id <> :user_id_other THEN mm.id ELSE NULL END) AS max_other_message_id
            FROM message_thread_participants self_p
            LEFT JOIN message_messages mm ON mm.thread_id = self_p.thread_id
            WHERE self_p.user_id = :user_id
            GROUP BY self_p.thread_id, self_p.last_read_message_id
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id_other', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return 0;
        }

        $updatedThreads = 0;
        $nowSql = $this->schema->nowSql($pdo);
        $update = $pdo->prepare("
            UPDATE message_thread_participants
            SET last_read_message_id = :last_read_message_id,
                last_read_at = {$nowSql},
                updated_at = {$nowSql}
            WHERE thread_id = :thread_id
              AND user_id = :user_id
        ");

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $threadId = (int) ($row['thread_id'] ?? 0);
            $currentCursor = isset($row['last_read_message_id']) ? (int) $row['last_read_message_id'] : 0;
            $maxOtherMessageId = isset($row['max_other_message_id']) ? (int) $row['max_other_message_id'] : 0;

            if ($threadId <= 0 || $maxOtherMessageId <= 0 || $maxOtherMessageId <= $currentCursor) {
                continue;
            }

            $update->execute([
                'last_read_message_id' => $maxOtherMessageId,
                'thread_id' => $threadId,
                'user_id' => $userId,
            ]);
            if ($update->rowCount() > 0) {
                $updatedThreads++;
            }
        }

        return $updatedThreads;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function searchUsers(PDO $pdo, int $userId, string $query, int $limit = 8, string $baseUri = ''): array
    {
        $query = trim($query);
        if ($userId <= 0 || mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $rows = [];
        $displayNameSql = $this->userDisplayNameSql($pdo);
        $avatarSql = $this->userColumnSql($pdo, 'avatar', '', "''");
        $statusSql = $this->userColumnSql($pdo, 'status', '', "'active'");
        $isBannedSql = $this->userColumnSql($pdo, 'is_banned', '', '0');
        $deletedAtSql = $this->userColumnSql($pdo, 'deleted_at', '', 'NULL');
        $sql = "
            SELECT id, {$displayNameSql} AS username, {$avatarSql} AS avatar, {$statusSql} AS status, {$isBannedSql} AS is_banned, {$deletedAtSql} AS deleted_at
            FROM users
            WHERE id <> :user_id
              AND {$displayNameSql} LIKE :query
            ORDER BY {$displayNameSql} ASC
            LIMIT :limit
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            // Minimal fallback for older schemas.
            $displayNameSql = $this->userDisplayNameSql($pdo);
            $avatarSql = $this->userColumnSql($pdo, 'avatar', '', "''");
            $fallback = $pdo->prepare("
                SELECT id, {$displayNameSql} AS username, {$avatarSql} AS avatar
                FROM users
                WHERE id <> :user_id
                  AND {$displayNameSql} LIKE :query
                ORDER BY {$displayNameSql} ASC
                LIMIT :limit
            ");
            $fallback->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $fallback->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $fallback->bindValue(':limit', $limit, PDO::PARAM_INT);
            $fallback->execute();
            $rows = $fallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $users = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !$this->isUserMessageEligible($row)) {
                continue;
            }

            $users[] = [
                'id' => (int) ($row['id'] ?? 0),
                'username' => trim((string) ($row['username'] ?? 'Kullanici')) ?: 'Kullanici',
                'name' => trim((string) ($row['username'] ?? 'Kullanici')) ?: 'Kullanici',
                'avatar' => $this->resolveAvatar((string) ($row['avatar'] ?? ''), $baseUri),
            ];
        }

        return $users;
    }

    private function threadIdByKey(PDO $pdo, string $threadKey): int
    {
        $stmt = $pdo->prepare('SELECT id FROM message_threads WHERE thread_key = ? LIMIT 1');
        $stmt->execute([$threadKey]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function ensureParticipants(PDO $pdo, int $threadId, int $firstUserId, int $secondUserId): void
    {
        if ($threadId <= 0 || $firstUserId <= 0 || $secondUserId <= 0) {
            return;
        }

        $nowSql = $this->schema->nowSql($pdo);
        $insert = $pdo->prepare($this->schema->insertIgnorePrefix($pdo) . "
            INTO message_thread_participants (thread_id, user_id, last_read_message_id, last_read_at, created_at, updated_at)
            VALUES (:thread_id, :user_id, NULL, NULL, {$nowSql}, {$nowSql})
        ");

        foreach ([$firstUserId, $secondUserId] as $participantId) {
            $insert->execute([
                'thread_id' => $threadId,
                'user_id' => $participantId,
            ]);
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchThreadMessages(PDO $pdo, int $threadId, int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $pdo->prepare("
            SELECT id, thread_id, sender_user_id, body, is_deleted, created_at, updated_at
            FROM message_messages
            WHERE thread_id = :thread_id
            ORDER BY id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @return list<array<string,mixed>>
     */
    private function decorateMessages(array $messages, int $viewerUserId, int $otherReadCursor, string $otherReadAt = ''): array
    {
        $items = [];
        foreach ($messages as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $senderId = (int) ($row['sender_user_id'] ?? 0);
            $createdAt = (string) ($row['created_at'] ?? '');
            $isMine = $senderId === $viewerUserId;

            $items[] = [
                'id' => $id,
                'thread_id' => (int) ($row['thread_id'] ?? 0),
                'sender_user_id' => $senderId,
                'body' => (string) ($row['body'] ?? ''),
                'is_deleted' => !empty($row['is_deleted']),
                'created_at' => $createdAt,
                'created_at_label' => $this->formatDateTime($createdAt),
                'is_mine' => $isMine,
                'is_read_by_recipient' => $isMine && $otherReadCursor > 0 && $id <= $otherReadCursor,
                'read_at_label' => ($isMine && $otherReadCursor > 0 && $id <= $otherReadCursor && $otherReadAt !== '') ? $this->formatDateTime($otherReadAt) : '',
            ];
        }

        return $items;
    }

    private function markThreadReadOnOpen(PDO $pdo, int $threadId, int $userId): int
    {
        $participant = $this->participantForThread($pdo, $threadId, $userId);
        if ($participant === null) {
            return 0;
        }

        $currentCursor = isset($participant['last_read_message_id']) ? (int) $participant['last_read_message_id'] : 0;
        $unreadCount = $this->countUnreadMessages($pdo, $threadId, $userId, $currentCursor);
        if ($unreadCount <= 0) {
            return 0;
        }

        $latestMessageId = $this->latestMessageId($pdo, $threadId);
        if ($latestMessageId <= 0 || $latestMessageId <= $currentCursor) {
            return 0;
        }

        $nowSql = $this->schema->nowSql($pdo);
        $update = $pdo->prepare("
            UPDATE message_thread_participants
            SET last_read_message_id = :last_read_message_id,
                last_read_at = {$nowSql},
                updated_at = {$nowSql}
            WHERE thread_id = :thread_id
              AND user_id = :user_id
        ");
        $update->execute([
            'last_read_message_id' => $latestMessageId,
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);

        return $unreadCount;
    }

    private function latestMessageId(PDO $pdo, int $threadId): int
    {
        $stmt = $pdo->prepare('SELECT MAX(id) FROM message_messages WHERE thread_id = ?');
        $stmt->execute([$threadId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function participantForThread(PDO $pdo, int $threadId, int $userId): ?array
    {
        $stmt = $pdo->prepare('
            SELECT thread_id, user_id, last_read_message_id, last_read_at
            FROM message_thread_participants
            WHERE thread_id = :thread_id
              AND user_id = :user_id
            LIMIT 1
        ');
        $stmt->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function countUnreadMessages(PDO $pdo, int $threadId, int $userId, int $cursor): int
    {
        if ($cursor > 0) {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM message_messages
                WHERE thread_id = :thread_id
                  AND sender_user_id <> :user_id
                  AND id > :cursor
            ');
            $stmt->execute([
                'thread_id' => $threadId,
                'user_id' => $userId,
                'cursor' => $cursor,
            ]);
        } else {
            $stmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM message_messages
                WHERE thread_id = :thread_id
                  AND sender_user_id <> :user_id
            ');
            $stmt->execute([
                'thread_id' => $threadId,
                'user_id' => $userId,
            ]);
        }

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array{success:bool,message:string,thread_id:int,message_id:int}
     */
    private function persistMessage(PDO $pdo, int $senderUserId, int $targetUserId, int $threadId, string $body, string $baseUri): array
    {
        $body = $this->normalizeBody($body);
        if ($body === '') {
            return ['success' => false, 'message' => 'Bos mesaj gonderemezsiniz.', 'thread_id' => $threadId, 'message_id' => 0];
        }

        if (mb_strlen($body) > 4000) {
            return ['success' => false, 'message' => 'Mesaj en fazla 4000 karakter olabilir.', 'thread_id' => $threadId, 'message_id' => 0];
        }

        $this->ensureSchema($pdo);
        $messageId = 0;
        $nowSql = $this->schema->nowSql($pdo);

        try {
            $ownsTransaction = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $ownsTransaction = true;
            }

            $insertMessage = $pdo->prepare("
                INSERT INTO message_messages (thread_id, sender_user_id, body, created_at, updated_at)
                VALUES (:thread_id, :sender_user_id, :body, {$nowSql}, {$nowSql})
            ");
            $insertMessage->execute([
                'thread_id' => $threadId,
                'sender_user_id' => $senderUserId,
                'body' => $body,
            ]);
            $messageId = (int) $pdo->lastInsertId();

            $updateThread = $pdo->prepare("
                UPDATE message_threads
                SET last_message_id = :last_message_id,
                    last_message_at = {$nowSql},
                    updated_at = {$nowSql}
                WHERE id = :thread_id
            ");
            $updateThread->execute([
                'last_message_id' => $messageId,
                'thread_id' => $threadId,
            ]);

            $this->ensureParticipants($pdo, $threadId, $senderUserId, $targetUserId);

            $typingResetSql = $this->participantTypingColumnExists($pdo) ? ",\n                    typing_at = NULL" : '';
            $updateSenderCursor = $pdo->prepare("
                UPDATE message_thread_participants
                SET last_read_message_id = :last_read_message_id,
                    last_read_at = {$nowSql},
                    updated_at = {$nowSql}{$typingResetSql}
                WHERE thread_id = :thread_id
                  AND user_id = :user_id
            ");
            $updateSenderCursor->execute([
                'last_read_message_id' => $messageId,
                'thread_id' => $threadId,
                'user_id' => $senderUserId,
            ]);

            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['success' => false, 'message' => 'Mesaj gonderilemedi.', 'thread_id' => $threadId, 'message_id' => 0];
        }

        $this->dispatchNotificationBestEffort($pdo, $targetUserId, $senderUserId, $threadId, $messageId, $body, $baseUri);

        $participants = $this->getThreadParticipants($pdo, $threadId);
        $this->broadcastToWebSocket($participants, [
            'type' => 'new_message',
            'thread_id' => $threadId,
            'message_id' => $messageId
        ]);

        return [
            'success' => true,
            'message' => 'Mesaj gonderildi.',
            'thread_id' => $threadId,
            'message_id' => $messageId,
        ];
    }

    private function dispatchNotificationBestEffort(
        PDO $pdo,
        int $recipientUserId,
        int $senderUserId,
        int $threadId,
        int $messageId,
        string $body,
        string $baseUri,
    ): void {
        if ($recipientUserId <= 0 || $senderUserId <= 0 || $messageId <= 0 || $threadId <= 0) {
            return;
        }

        $senderName = 'Bir kullanici';
        $sender = $this->lookupUser($pdo, $senderUserId);
        if (is_array($sender) && trim((string) ($sender['username'] ?? '')) !== '') {
            $senderName = (string) $sender['username'];
        }

        $link = $this->threadUrl($threadId, $baseUri);
        $title = $senderName . ' size bir mesaj gonderdi';
        $message = function_exists('mb_strimwidth')
            ? mb_strimwidth($body, 0, 220, '...')
            : substr($body, 0, 220);

        try {
            if (is_callable($this->notificationDispatcher)) {
                ($this->notificationDispatcher)($pdo, $recipientUserId, $senderUserId, $threadId, $messageId, $link, $title, $message);

                return;
            }

            if (function_exists('notificationDispatch')) {
                notificationDispatch(
                    $pdo,
                    'direct_message_received',
                    $recipientUserId,
                    $senderUserId,
                    'message_thread',
                    $threadId,
                    [
                        'title' => $title,
                        'message' => $message,
                        'link' => $link,
                        'actor_name' => $senderName,
                        'topic_title' => 'Mesajlar',
                        'dedupe_key' => 'direct_message_received:' . $messageId,
                    ],
                );
            }
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, ['source' => 'MessageService notification']);
            } else {
                error_log('Message notification dispatch failed: ' . $exception->getMessage());
            }
        }
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = strip_tags($body);

        return trim($body);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function lookupUser(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $displayNameSql = $this->userDisplayNameSql($pdo);
            $avatarSql = $this->userColumnSql($pdo, 'avatar', '', "''");
            $statusSql = $this->userColumnSql($pdo, 'status', '', "'active'");
            $isBannedSql = $this->userColumnSql($pdo, 'is_banned', '', '0');
            $deletedAtSql = $this->userColumnSql($pdo, 'deleted_at', '', 'NULL');
            $stmt = $pdo->prepare('
                SELECT id, ' . $displayNameSql . ' AS username, ' . $avatarSql . ' AS avatar, ' . $statusSql . ' AS status, ' . $isBannedSql . ' AS is_banned, ' . $deletedAtSql . ' AS deleted_at
                FROM users
                WHERE id = :id
                LIMIT 1
            ');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function userDisplayNameSql(PDO $pdo, string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        foreach (['username', 'name', 'author'] as $column) {
            if ($this->schema->columnExists($pdo, 'users', $column)) {
                return $prefix . $column;
            }
        }

        return "'Kullanici'";
    }

    private function userColumnSql(PDO $pdo, string $column, string $alias = '', string $fallbackSql = 'NULL'): string
    {
        if ($this->schema->columnExists($pdo, 'users', $column)) {
            return ($alias !== '' ? $alias . '.' : '') . $column;
        }

        return $fallbackSql;
    }

    /**
     * @param array<string,mixed>|null $user
     */
    private function isUserMessageEligible(?array $user): bool
    {
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            return false;
        }

        $status = strtolower(trim((string) ($user['status'] ?? 'active')));
        if ($status !== '' && !in_array($status, ['active'], true)) {
            return false;
        }

        if (!empty($user['is_banned'])) {
            return false;
        }

        $deletedAt = trim((string) ($user['deleted_at'] ?? ''));
        if ($deletedAt !== '' && $deletedAt !== '0000-00-00 00:00:00') {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateThreadRow(array $row, int $userId, string $baseUri): array
    {
        $threadId = (int) ($row['thread_id'] ?? 0);
        $lastMessageBody = trim((string) ($row['last_message_body'] ?? ''));
        $preview = preg_replace('/\s+/u', ' ', $lastMessageBody) ?? $lastMessageBody;
        if ($preview !== '') {
            $preview = function_exists('mb_strimwidth')
                ? mb_strimwidth($preview, 0, 120, '...')
                : substr($preview, 0, 120);
        }

        $lastMessageId = (int) ($row['last_message_id'] ?? 0);
        $lastSenderId = (int) ($row['last_sender_user_id'] ?? 0);
        $withCursor = (int) ($row['with_last_read_message_id'] ?? 0);
        $lastMessageAt = (string) ($row['last_message_created_at'] ?? $row['last_message_at'] ?? $row['thread_created_at'] ?? '');

        $withTypingAt = (string) ($row['with_typing_at'] ?? '');
        $isTypingNow = false;
        if ($withTypingAt !== '') {
            $isTypingNow = strtotime($withTypingAt) >= time() - 6;
        }

        return [
            'thread_id' => $threadId,
            'thread_key' => (string) ($row['thread_key'] ?? ''),
            'with_user_id' => (int) ($row['with_user_id'] ?? 0),
            'with_user_name' => trim((string) ($row['with_user_name'] ?? 'Kullanici')) ?: 'Kullanici',
            'with_user_avatar' => $this->resolveAvatar((string) ($row['with_user_avatar'] ?? ''), $baseUri),
            'unread_count' => max(0, (int) ($row['unread_count'] ?? 0)),
            'last_message_id' => $lastMessageId,
            'last_message_body' => $lastMessageBody,
            'last_message_preview' => $preview !== '' ? $preview : 'Henuz mesaj yok',
            'last_message_at' => $lastMessageAt,
            'last_message_at_label' => $this->formatDateTime($lastMessageAt),
            'last_sender_user_id' => $lastSenderId,
            'last_message_is_mine' => $lastSenderId > 0 && $lastSenderId === $userId,
            'last_message_read' => $lastSenderId > 0 && $lastSenderId === $userId && $lastMessageId > 0 && $withCursor >= $lastMessageId,
            'self_last_read_message_id' => (int) ($row['self_last_read_message_id'] ?? 0),
            'with_last_read_message_id' => $withCursor,
            'with_last_read_at' => (string) ($row['with_last_read_at'] ?? ''),
            'is_typing_now' => $isTypingNow,
            'thread_url' => $this->threadUrl($threadId, $baseUri),
        ];
    }

    private function resolveAvatar(string $rawAvatar, string $baseUri): string
    {
        $baseUri = rtrim($baseUri, '/');
        $fallback = function_exists('defaultAvatarUrl')
            ? (string) defaultAvatarUrl($baseUri)
            : ($baseUri !== '' ? $baseUri . '/assets/images/noavatar-neon-helmet.svg' : '/assets/images/noavatar-neon-helmet.svg');

        $rawAvatar = trim($rawAvatar);
        if ($rawAvatar === '') {
            return $fallback;
        }

        if (function_exists('resolveAvatarUrl')) {
            $resolved = (string) resolveAvatarUrl($rawAvatar, $baseUri, true);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        if (preg_match('~^(https?:)?//|^/~i', $rawAvatar) === 1) {
            return $rawAvatar;
        }

        if ($baseUri !== '') {
            return $baseUri . '/' . ltrim($rawAvatar, '/');
        }

        return '/' . ltrim($rawAvatar, '/');
    }

    private function formatDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        $now = time();
        $diff = $now - $timestamp;

        // Just now (< 60s)
        if ($diff < 60) {
            return 'Az once';
        }

        // Minutes ago (< 60 min)
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' dk once';
        }

        // Today - show time only
        if (date('Y-m-d', $timestamp) === date('Y-m-d', $now)) {
            return date('H:i', $timestamp);
        }

        // Yesterday
        if (date('Y-m-d', $timestamp) === date('Y-m-d', $now - 86400)) {
            return 'Dun ' . date('H:i', $timestamp);
        }

        // This year - show date without year
        if (date('Y', $timestamp) === date('Y', $now)) {
            return date('d M H:i', $timestamp);
        }

        // Older - full date
        return date('d M Y H:i', $timestamp);
    }

    private function threadUrl(int $threadId, string $baseUri = ''): string
    {
        $threadId = max(0, $threadId);
        if ($threadId <= 0) {
            return '';
        }

        $base = (string) routePublicStaticUrl('messages');

        return $base . '?thread=' . $threadId;
    }

    private function hasRequiredTables(PDO $pdo): bool
    {
        return $this->schema->tableExists($pdo, 'message_threads')
            && $this->schema->tableExists($pdo, 'message_thread_participants')
            && $this->schema->tableExists($pdo, 'message_messages')
            && $this->schema->columnExists($pdo, 'message_messages', 'is_deleted');
    }

    public function updateTypingStatus(PDO $pdo, int $threadId, int $userId): void
    {
        if ($threadId <= 0 || $userId <= 0 || !$this->isSchemaReady($pdo) || !$this->participantTypingColumnExists($pdo)) {
            return;
        }

        $nowSql = $this->schema->nowSql($pdo);
        $update = $pdo->prepare("
            UPDATE message_thread_participants
            SET typing_at = {$nowSql}
            WHERE thread_id = :thread_id
              AND user_id = :user_id
        ");
        $update->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);

        $participants = $this->getThreadParticipants($pdo, $threadId);
        $this->broadcastToWebSocket($participants, [
            'type' => 'typing',
            'thread_id' => $threadId,
            'user_id' => $userId
        ]);
    }

    public function clearTypingStatus(PDO $pdo, int $threadId, int $userId): void
    {
        if ($threadId <= 0 || $userId <= 0 || !$this->isSchemaReady($pdo) || !$this->participantTypingColumnExists($pdo)) {
            return;
        }

        $update = $pdo->prepare("
            UPDATE message_thread_participants
            SET typing_at = NULL
            WHERE thread_id = :thread_id
              AND user_id = :user_id
        ");
        $update->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
        ]);

        $participants = $this->getThreadParticipants($pdo, $threadId);
        $this->broadcastToWebSocket($participants, [
            'type' => 'stop_typing',
            'thread_id' => $threadId,
            'user_id' => $userId
        ]);
    }

    public function deleteMessage(PDO $pdo, int $messageId, int $userId): array
    {
        if ($messageId <= 0 || $userId <= 0 || !$this->isSchemaReady($pdo)) {
            return ['success' => false, 'message' => 'GeÃ§ersiz istek.'];
        }

        // Fetch message
        $stmt = $pdo->prepare("SELECT id, thread_id, sender_user_id, created_at, is_deleted FROM message_messages WHERE id = :id");
        $stmt->execute(['id' => $messageId]);
        $msg = $stmt->fetch();

        if (!$msg) {
            return ['success' => false, 'message' => 'Mesaj bulunamadÄ±.'];
        }

        if ((int)$msg['sender_user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Sadece kendi mesajlarÄ±nÄ±zÄ± silebilirsiniz.'];
        }

        if (!empty($msg['is_deleted'])) {
            return ['success' => false, 'message' => 'Mesaj zaten silinmiÅŸ.'];
        }

        // 15 minute limit
        $createdAt = strtotime((string)$msg['created_at']);
        if (time() - $createdAt > 15 * 60) {
            return ['success' => false, 'message' => 'Mesajlar sadece ilk 15 dakika iÃ§inde silinebilir.'];
        }

        $nowSql = $this->schema->nowSql($pdo);
        $update = $pdo->prepare("UPDATE message_messages SET is_deleted = 1, body = :deleted_body, updated_at = {$nowSql} WHERE id = :id");
        $update->execute([
            'id' => $messageId,
            'deleted_body' => 'Bu mesaj silindi',
        ]);

        $threadId = (int) $msg['thread_id'];
        $participants = $this->getThreadParticipants($pdo, $threadId);
        $this->broadcastToWebSocket($participants, [
            'type' => 'delete_message',
            'thread_id' => $threadId,
            'message_id' => $messageId
        ]);

        return ['success' => true, 'message' => 'Mesaj baÅŸarÄ±yla silindi.'];
    }

    public function editMessage(PDO $pdo, int $messageId, int $userId, string $newBody): array
    {
        if ($messageId <= 0 || $userId <= 0 || !$this->isSchemaReady($pdo)) {
            return ['success' => false, 'message' => 'GeÃ§ersiz istek.'];
        }

        $newBody = trim($newBody);
        if ($newBody === '') {
            return ['success' => false, 'message' => 'Mesaj iÃ§eriÄŸi boÅŸ olamaz.'];
        }

        // Fetch message
        $stmt = $pdo->prepare("SELECT id, thread_id, sender_user_id, created_at, is_deleted FROM message_messages WHERE id = :id");
        $stmt->execute(['id' => $messageId]);
        $msg = $stmt->fetch();

        if (!$msg) {
            return ['success' => false, 'message' => 'Mesaj bulunamadÄ±.'];
        }

        if ((int)$msg['sender_user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Sadece kendi mesajlarÄ±nÄ±zÄ± dÃ¼zenleyebilirsiniz.'];
        }

        if (!empty($msg['is_deleted'])) {
            return ['success' => false, 'message' => 'SilinmiÅŸ mesajÄ± dÃ¼zenleyemezsiniz.'];
        }

        // 15 minute limit
        $createdAt = strtotime((string)$msg['created_at']);
        if (time() - $createdAt > 15 * 60) {
            return ['success' => false, 'message' => 'Mesajlar sadece ilk 15 dakika iÃ§inde dÃ¼zenlenebilir.'];
        }

        $nowSql = $this->schema->nowSql($pdo);
        $update = $pdo->prepare("UPDATE message_messages SET body = :body, updated_at = {$nowSql} WHERE id = :id");
        $update->execute(['id' => $messageId, 'body' => $newBody]);

        $threadId = (int) $msg['thread_id'];
        $participants = $this->getThreadParticipants($pdo, $threadId);
        $this->broadcastToWebSocket($participants, [
            'type' => 'edit_message',
            'thread_id' => $threadId,
            'message_id' => $messageId,
            'body' => $newBody
        ]);

        return ['success' => true, 'message' => 'Mesaj baÅŸarÄ±yla dÃ¼zenlendi.'];
    }

    public function getHistory(PDO $pdo, int $userId, int $threadId, int $beforeId): array
    {
        if ($userId <= 0 || $threadId <= 0 || $beforeId <= 0 || !$this->isSchemaReady($pdo)) {
            return [];
        }

        $thread = $this->threadForUser($pdo, $userId, $threadId);
        if (!$thread) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                mm.id,
                mm.thread_id,
                mm.sender_user_id,
                mm.body,
                mm.created_at,
                (mm.sender_user_id = :user_id) AS is_mine,
                (mm.id <= :with_cursor) AS is_read_by_recipient,
                mm.is_deleted
            FROM message_messages mm
            WHERE mm.thread_id = :thread_id
              AND mm.id < :before_id
            ORDER BY mm.id DESC
            LIMIT 50
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':with_cursor', (int) $thread['with_last_read_message_id'], PDO::PARAM_INT);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_INT);
        $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $otherReadCursor = (int) $thread['with_last_read_message_id'];
        $otherReadAt = (string) ($thread['with_last_read_at'] ?? '');

        // Reverse to chronological
        $messages = array_reverse($messages);

        foreach ($messages as &$msg) {
            $msg['created_at_label'] = $this->formatDateTime((string) ($msg['created_at'] ?? ''));
            $isMine = !empty($msg['is_mine']);
            $id = (int)$msg['id'];
            if ($isMine && $otherReadCursor > 0 && $id <= $otherReadCursor && $otherReadAt !== '') {
                $msg['read_at_label'] = $this->formatDateTime($otherReadAt);
            } else {
                $msg['read_at_label'] = '';
            }
        }
        unset($msg);

        return $messages;
    }

    public function getThreadParticipants(PDO $pdo, int $threadId): array
    {
        $stmt = $pdo->prepare("SELECT user_id FROM message_thread_participants WHERE thread_id = :thread_id");
        $stmt->execute(['thread_id' => $threadId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_map('intval', $rows);
    }

    public function broadcastToWebSocket(array|int $userIds, array $payload): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', is_array($userIds) ? $userIds : [$userIds]))));
        if (empty($userIds)) {
            return;
        }

        try {
            $body = json_encode([
                'user_id' => $userIds,
                'payload' => $payload
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                return;
            }

            $url = 'http://127.0.0.1:8081/broadcast';
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                if ($ch !== false) {
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    if (defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 250);
                    }
                    if (defined('CURLOPT_TIMEOUT_MS')) {
                        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);
                    } else {
                        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                    }
                    curl_exec($ch);
                    curl_close($ch);

                    return;
                }
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 1,
                    'ignore_errors' => true,
                ],
            ]);
            file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            // Ignore broadcast errors
        }
    }

    private function participantTypingSelectSql(PDO $pdo, string $alias = 'other_p'): string
    {
        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $safeAlias = $safeAlias !== '' ? $safeAlias : 'other_p';

        return $this->participantTypingColumnExists($pdo) ? ($safeAlias . '.typing_at') : 'NULL';
    }

    private function participantTypingColumnExists(PDO $pdo): bool
    {
        $key = spl_object_id($pdo);
        if (!array_key_exists($key, $this->typingColumnByConnection)) {
            $this->typingColumnByConnection[$key] = $this->schema->columnExists($pdo, 'message_thread_participants', 'typing_at');
        }

        return $this->typingColumnByConnection[$key];
    }
}
