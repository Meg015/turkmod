<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use Throwable;

final class TopicUpdatedAuditLogListener implements Listener
{
    use TopicWorkflowListenerSupport;

    public function handle(Event $event): void
    {
        if ($event->name() !== 'topic.updated') {
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
            logActivity($pdo, 'topic_user_edited', 'topic', $topicId, [
                'title' => (string) ($payload['title'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            $this->logListenerException($exception, 'TopicWorkflow.TopicUpdatedAuditLogListener', [
                'topic_id' => $topicId,
            ]);
        }
    }
}

