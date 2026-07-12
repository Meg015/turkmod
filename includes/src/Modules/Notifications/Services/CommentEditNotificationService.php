<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;

final class CommentEditNotificationService
{
    public function __construct(private ?NotificationDispatchService $dispatch = null)
    {
        $this->dispatch ??= new NotificationDispatchService();
    }

    public function dispatch(PDO $pdo, array $comment, int $editorUserId, string $editorName, array $editResult): bool
    {
        $commentId = (int) ($comment['id'] ?? 0);
        $recipientId = (int) ($comment['user_id'] ?? 0);
        $topicId = (int) ($comment['topic_id'] ?? 0);
        if (
            empty($editResult['changed'])
            || $commentId <= 0
            || $recipientId <= 0
            || $topicId <= 0
            || $editorUserId <= 0
            || $editorUserId === $recipientId
        ) {
            return false;
        }

        $topicStmt = $pdo->prepare('SELECT id, title, slug FROM topics WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $topicStmt->execute([$topicId]);
        $topic = $topicStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($topic === []) {
            return false;
        }

        $topicTitle = trim((string) ($topic['title'] ?? 'Konu')) ?: 'Konu';
        $newBody = trim(strip_tags((string) ($editResult['body'] ?? '')));
        $excerpt = mb_strlen($newBody) > 140 ? mb_substr($newBody, 0, 137) . '...' : $newBody;
        $reason = trim((string) ($editResult['edit_reason'] ?? ''));
        $revisionId = (int) ($editResult['history_id'] ?? 0);
        $dedupeSuffix = $revisionId > 0
            ? (string) $revisionId
            : date('YmdHis') . ':' . substr(hash('sha256', $newBody . '|' . $reason), 0, 12);

        return $this->dispatch->dispatch(
            $pdo,
            'comment_edited_by_staff',
            $recipientId,
            $editorUserId,
            'comment',
            $commentId,
            [
                'actor_name' => trim($editorName) !== '' ? trim($editorName) : 'Yönetim',
                'topic_title' => $topicTitle,
                'comment_excerpt' => $excerpt,
                'moderation_note' => $reason,
                'moderation_note_line' => $reason !== '' ? ' Neden: ' . $reason : '',
                'link' => \topicUrl((string) ($topic['slug'] ?? ''), (int) ($topic['id'] ?? $topicId)) . '#comment-' . $commentId,
                'dedupe_key' => 'comment_edited_by_staff:' . $recipientId . ':' . $commentId . ':' . $dedupeSuffix,
            ]
        );
    }
}
