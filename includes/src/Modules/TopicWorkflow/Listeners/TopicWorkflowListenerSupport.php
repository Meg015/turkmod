<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Listeners;

use App\Core\Events\Event;
use PDO;
use Throwable;

trait TopicWorkflowListenerSupport
{
    /**
     * @return array<string,mixed>
     */
    private function payload(Event $event): array
    {
        $payload = $event->payload();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function eventPdo(array $payload): ?PDO
    {
        $pdo = $payload['pdo'] ?? null;

        return $pdo instanceof PDO ? $pdo : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function eventProjectRoot(array $payload): string
    {
        $projectRoot = trim((string) ($payload['project_root'] ?? ''));
        if ($projectRoot !== '') {
            return rtrim($projectRoot, '/\\');
        }

        return dirname(__DIR__, 5);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logListenerException(Throwable $exception, string $source, array $context = []): void
    {
        if (function_exists('appLogException')) {
            appLogException($exception, array_merge(['source' => $source], $context));

            return;
        }

        error_log($exception->getMessage());
    }
}

