<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use Throwable;

final class TopicCreatedAuditLogListener implements Listener
{
    use TopicWorkflowListenerSupport;

    public function handle(Event $event): void
    {
        if ($event->name() !== 'topic.created') {
            return;
        }

        if (!function_exists('logActivity')) {
            return;
        }

        $payload = $this->payload($event);
        $pdo = $this->eventPdo($payload);
        $topicId = (int) ($payload['topic_id'] ?? 0);
        if (!$pdo || $topicId <= 0) {
            return;
        }

        try {
            logActivity($pdo, 'topic_uploaded', 'topic', $topicId, [
                'title' => (string) ($payload['title'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            $this->logListenerException($exception, 'TopicWorkflow.TopicCreatedAuditLogListener', [
                'topic_id' => $topicId,
            ]);
        }
    }
}

