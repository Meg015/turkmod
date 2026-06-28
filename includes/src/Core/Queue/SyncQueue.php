<?php

declare(strict_types=1);

namespace App\Core\Queue;

use Throwable;

final class SyncQueue implements Queue
{
    public function push(Job $job): void
    {
        $this->run($job);
    }

    public function later(int $delaySeconds, Job $job): void
    {
        $this->run($job);
    }

    private function run(Job $job): void
    {
        try {
            $job->handle();
        } catch (Throwable $exception) {
            error_log('SyncQueue job failed: ' . $exception->getMessage());
            throw $exception;
        }
    }
}
