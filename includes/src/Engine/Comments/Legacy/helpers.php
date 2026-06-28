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
