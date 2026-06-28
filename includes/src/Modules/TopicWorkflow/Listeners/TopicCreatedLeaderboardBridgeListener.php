<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use Throwable;

final class TopicCreatedLeaderboardBridgeListener implements Listener
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
        if (!$pdo || $authorId <= 0) {
            return;
        }

        $triggerFile = $this->eventProjectRoot($payload) . '/includes/src/Modules/Leaderboard/Legacy/triggers.php';
        if (is_file($triggerFile)) {
            require_once $triggerFile;
        }

        if (!function_exists('leaderboardTriggerTopicCreated')) {
            return;
        }

        try {
            leaderboardTriggerTopicCreated($pdo, $authorId);
        } catch (Throwable $exception) {
            $this->logListenerException($exception, 'TopicWorkflow.TopicCreatedLeaderboardBridgeListener', [
                'author_id' => $authorId,
            ]);
        }
    }
}


