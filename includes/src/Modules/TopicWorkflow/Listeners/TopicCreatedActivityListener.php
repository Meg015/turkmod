<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use Throwable;

final class TopicCreatedActivityListener implements Listener
{
    use TopicWorkflowListenerSupport;

    public function handle(Event $event): void
    {
        if ($event->name() !== 'topic.created') {
            return;
        }

        $payload = $this->payload($event);
        $pdo = $this->eventPdo($payload);
        $authorId = (int) ($payload['author_id'] ?? 0);
        $topicId = (int) ($payload['topic_id'] ?? 0);
        if (!$pdo || $authorId <= 0 || $topicId <= 0) {
            return;
        }

        $eventsInit = $this->eventProjectRoot($payload) . '/includes/src/Modules/Events/init.php';
        if (is_file($eventsInit)) {
            require_once $eventsInit;
        }

        if (!function_exists('eventsRecordActivity')) {
            return;
        }

        try {
            eventsRecordActivity($pdo, $authorId, 'topic_created', 'topic', $topicId, [
                'status' => (string) ($payload['status'] ?? ''),
                'category_id' => (int) ($payload['category_id'] ?? 0),
            ]);
        } catch (Throwable $exception) {
            $this->logListenerException($exception, 'TopicWorkflow.TopicCreatedActivityListener', [
                'topic_id' => $topicId,
                'author_id' => $authorId,
            ]);
        }
    }
}


