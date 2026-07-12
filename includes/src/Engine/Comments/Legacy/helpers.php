<?php

declare(strict_types=1);

if (!function_exists('commentTopicCountsAsVisible')) {
    function commentTopicCountsAsVisible(array $comment, ?string $statusOverride = null, ?bool $deletedOverride = null): bool
    {
        $status = strtolower(trim((string) ($statusOverride ?? ($comment['status'] ?? ''))));
        $isDeleted = $deletedOverride ?? !empty($comment['deleted_at']);

        return $status === 'approved' && !$isDeleted;
    }
}

if (!function_exists('commentTopicCountDelta')) {
    function commentTopicCountDelta(array $comment, ?string $nextStatus = null, ?bool $nextDeleted = null): int
    {
        $currentVisible = commentTopicCountsAsVisible($comment);
        $nextVisible = commentTopicCountsAsVisible($comment, $nextStatus, $nextDeleted);

        return (int) $nextVisible - (int) $currentVisible;
    }
}

if (!function_exists('commentApplyTopicCountDelta')) {
    function commentApplyTopicCountDelta(?PDO $pdo, array $comment, ?string $nextStatus = null, ?bool $nextDeleted = null): int
    {
        if (!$pdo) {
            return 0;
        }

        $delta = commentTopicCountDelta($comment, $nextStatus, $nextDeleted);
        $topicId = (int) ($comment['topic_id'] ?? 0);
        if ($delta === 0 || $topicId <= 0) {
            return $delta;
        }

        if ($delta > 0) {
            $stmt = $pdo->prepare("UPDATE topics SET comment_count = comment_count + 1 WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE topics SET comment_count = GREATEST(comment_count - 1, 0) WHERE id = ?");
        }
        $stmt->execute([$topicId]);

        return $delta;
    }
}

if (!function_exists('commentSchemaHasColumn')) {
    function commentSchemaHasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?: '';
        if ($table === '' || $column === '') {
            return false;
        }

        $cacheKey = $table . '.' . $column;
        if (!array_key_exists($cacheKey, $cache)) {
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
                $cache[$cacheKey] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $cache[$cacheKey] = false;
            }
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('commentUpdateWithHistory')) {
    /** @return array{changed:bool,history_id:int,body:string,edit_reason:?string} */
    function commentUpdateWithHistory(
        PDO $pdo,
        array $comment,
        string $newBody,
        int $editorUserId,
        ?string $editReason = null,
        bool $historyEnabled = true
    ): array {
        $commentId = (int) ($comment['id'] ?? 0);
        $oldBody = (string) ($comment['body'] ?? '');
        $newBody = trim($newBody);
        $editReason = trim((string) $editReason);
        $editReason = $editReason !== '' ? mb_substr($editReason, 0, 255) : null;

        if ($commentId <= 0 || $editorUserId <= 0 || $newBody === '') {
            throw new InvalidArgumentException('Gecersiz yorum duzenleme verisi.');
        }

        if ($oldBody === $newBody) {
            return ['changed' => false, 'history_id' => 0, 'body' => $oldBody, 'edit_reason' => $editReason];
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $historyId = 0;
            if ($historyEnabled) {
                $historyStmt = $pdo->prepare(
                    'INSERT INTO comment_edit_history (comment_id, user_id, old_body, new_body, edit_reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $historyStmt->execute([$commentId, $editorUserId, $oldBody, $newBody, $editReason]);
                $historyId = (int) $pdo->lastInsertId();
            }

            if (commentSchemaHasColumn($pdo, 'comments', 'is_edited')) {
                $updateStmt = $pdo->prepare('UPDATE comments SET body = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW() WHERE id = ?');
            } else {
                $updateStmt = $pdo->prepare('UPDATE comments SET body = ?, updated_at = NOW() WHERE id = ?');
            }
            $updateStmt->execute([$newBody, $commentId]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return ['changed' => true, 'history_id' => $historyId, 'body' => $newBody, 'edit_reason' => $editReason];
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
