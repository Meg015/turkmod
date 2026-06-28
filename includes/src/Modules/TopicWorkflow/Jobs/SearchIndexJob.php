<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Jobs;

use App\Core\Queue\Job;
use App\Engine\Search\SearchEngine;
use Throwable;

final class SearchIndexJob implements Job
{
    /**
     * @param array<string,mixed> $document
     */
    public function __construct(
        private readonly SearchEngine $searchEngine,
        private readonly int $topicId,
        private readonly array $document = [],
    ) {
    }

    public function handle(): void
    {
        if ($this->topicId <= 0) {
            return;
        }

        try {
            $this->searchEngine->index('topic', $this->topicId, $this->document);
        } catch (Throwable $exception) {
            if (function_exists('appLogException')) {
                appLogException($exception, [
                    'source' => 'TopicWorkflow.SearchIndexJob',
                    'topic_id' => $this->topicId,
                ]);
            } else {
                error_log($exception->getMessage());
            }
        }
    }
}

