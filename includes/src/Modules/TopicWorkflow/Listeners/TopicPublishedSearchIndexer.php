<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use App\Core\Events\Listener;
use App\Core\Queue\Queue;
use App\Engine\Search\SearchEngine;
use App\Modules\TopicWorkflow\Jobs\SearchIndexJob;
use Throwable;

final class TopicPublishedSearchIndexer implements Listener
{
    public function __construct(
        private readonly Queue $queue,
        private readonly SearchEngine $searchEngine,
    ) {
    }

    public function handle(Event $event): void
    {
        if ($event->name() !== 'topic.published') {
            return;
        }

        $payload = $event->payload();
        $topicId = (int) ($payload['topic_id'] ?? 0);
        if ($topicId <= 0) {
            return;
        }

        $document = [
            'topic_id' => $topicId,
            'status' => (string) ($payload['status'] ?? ''),
            'category_id' => (int) ($payload['category_id'] ?? 0),
            'author_id' => (int) ($payload['author_id'] ?? $payload['editor_user_id'] ?? 0),
            'slug' => (string) ($payload['slug'] ?? ''),
            'title' => (string) ($payload['title'] ?? ''),
            'source' => (string) ($payload['source'] ?? 'topic_workflow'),
        ];

        try {
            $this->queue->push(new SearchIndexJob($this->searchEngine, $topicId, $document));
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, [
                    'source' => 'TopicWorkflow.TopicPublishedSearchIndexer',
                    'topic_id' => $topicId,
                ]);
            } else {
                error_log($exception->getMessage());
            }
        }
    }
}

