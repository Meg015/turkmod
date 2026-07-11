<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use App\Modules\Notifications\Services\NotificationDispatchService;
use PDO;

final class NotificationEventSubscriber implements Listener
{
    /** @var callable(PDO,string,int,?int,string,int,array<string,mixed>):bool|null */
    private $dispatcher;

    /**
     * @param callable(PDO,string,int,?int,string,int,array<string,mixed>):bool|null $dispatcher
     */
    public function __construct(
        private ?NotificationDispatchService $notifications = null,
        ?callable $dispatcher = null,
    ) {
        $this->notifications ??= new NotificationDispatchService();
        $this->dispatcher = $dispatcher;
    }

    public function handle(Event $event): void
    {
        match ($event->name()) {
            'topic.published' => $this->handleTopicPublished($event->payload()),
            'comment.created' => $this->handleCommentCreated($event->payload()),
            'report.created' => $this->handleReportCreated($event->payload()),
            default => null,
        };
    }

    /** @param array<string,mixed> $payload */
    private function handleTopicPublished(array $payload): void
    {
        $pdo = $this->payloadPdo($payload);
        $topicId = (int) ($payload['topic_id'] ?? 0);
        $recipientId = (int) ($payload['author_id'] ?? 0);
        if (!$pdo || $topicId <= 0 || $recipientId <= 0) {
            return;
        }

        $actorId = (int) ($payload['actor_user_id'] ?? $payload['editor_user_id'] ?? 0);
        $this->dispatch($pdo, 'topic_approved', $recipientId, $actorId > 0 ? $actorId : null, 'topic', $topicId, [
            'topic_title' => (string) ($payload['title'] ?? 'Konu'),
            'type' => 'success',
            'link' => $this->topicLink($pdo, $topicId),
            'dedupe_key' => 'topic_approved:' . $recipientId . ':' . $topicId,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function handleCommentCreated(array $payload): void
    {
        $pdo = $this->payloadPdo($payload);
        $commentId = (int) ($payload['comment_id'] ?? $payload['id'] ?? 0);
        $topicId = (int) ($payload['topic_id'] ?? 0);
        $actorId = (int) ($payload['actor_user_id'] ?? $payload['user_id'] ?? $payload['author_id'] ?? 0);
        $recipientId = (int) ($payload['recipient_user_id'] ?? $payload['parent_comment_author_id'] ?? $payload['topic_author_id'] ?? 0);
        if (!$pdo || $commentId <= 0 || $recipientId <= 0) {
            return;
        }

        $eventKey = isset($payload['parent_comment_author_id']) || isset($payload['parent_comment_id'])
            ? 'comment_reply'
            : 'comment_on_topic';
        $this->dispatch($pdo, $eventKey, $recipientId, $actorId > 0 ? $actorId : null, 'comment', $commentId, [
            'actor_name' => (string) ($payload['actor_name'] ?? 'Bir kullanici'),
            'topic_title' => (string) ($payload['topic_title'] ?? 'Konu'),
            'comment_excerpt' => (string) ($payload['comment_excerpt'] ?? $payload['content_excerpt'] ?? ''),
            'type' => 'info',
            'link' => $this->topicLink($pdo, $topicId),
            'dedupe_key' => $eventKey . ':' . $recipientId . ':' . $commentId,
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function handleReportCreated(array $payload): void
    {
        $pdo = $this->payloadPdo($payload);
        $recipientId = (int) ($payload['recipient_user_id'] ?? 0);
        $reportId = (int) ($payload['report_id'] ?? $payload['id'] ?? 0);
        $eventKey = (string) ($payload['notification_event_key'] ?? '');
        if (!$pdo || $recipientId <= 0 || $reportId <= 0 || $eventKey === '') {
            return;
        }

        $actorId = (int) ($payload['actor_user_id'] ?? $payload['reporter_user_id'] ?? 0);
        $entityType = (string) ($payload['entity_type'] ?? 'report');
        $this->dispatch($pdo, $eventKey, $recipientId, $actorId > 0 ? $actorId : null, $entityType, $reportId, array_merge([
            'type' => 'info',
            'dedupe_key' => $eventKey . ':' . $recipientId . ':' . $reportId,
        ], $payload));
    }

    /** @param array<string,mixed> $payload */
    private function payloadPdo(array $payload): ?PDO
    {
        return ($payload['pdo'] ?? null) instanceof PDO ? $payload['pdo'] : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function dispatch(
        PDO $pdo,
        string $eventKey,
        int $recipientId,
        ?int $actorId,
        string $entityType,
        int $entityId,
        array $payload
    ): bool {
        if ($this->dispatcher) {
            return (bool) ($this->dispatcher)($pdo, $eventKey, $recipientId, $actorId, $entityType, $entityId, $payload);
        }

        return $this->notifications->dispatch($pdo, $eventKey, $recipientId, $actorId, $entityType, $entityId, $payload);
    }

    private function topicLink(PDO $pdo, int $topicId): string
    {
        if ($topicId <= 0) {
            return \routeCanonicalPath('topic');
        }

        try {
            $stmt = $pdo->prepare('SELECT id, slug FROM topics WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $topicId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && !empty($row['slug'])) {
                return \topicUrlForRow($row);
            }
        } catch (\Throwable $e) {
        }

        return \routeCanonicalPath('topic');
    }
}
