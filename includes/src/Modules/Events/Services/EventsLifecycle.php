<?php

declare(strict_types=1);

namespace App\Modules\Events\Services;

use App\Core\Database;
use App\Core\Database\Migration;
use App\Core\Database\MigrationRunner;
use App\Core\Modules\ModuleLifecycle;
use PDO;
use RuntimeException;
use Throwable;

final class EventsLifecycle implements ModuleLifecycle
{
    public function onInstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'events_migrations');
        foreach ($this->migrations() as $migration) {
            $runner->apply($migration);
        }

        $this->seedLegacySchema();
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function onUninstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'events_migrations');
        $migrations = array_reverse($this->migrations());
        foreach ($migrations as $migration) {
            $runner->rollback($migration);
        }
    }

    private function seedLegacySchema(): void
    {
        $bootstrap = dirname(__DIR__) . '/init.php';
        if (!is_file($bootstrap)) {
            return;
        }

        require_once $bootstrap;
        if (!function_exists('eventsEnsureSchema')) {
            return;
        }

        try {
            eventsEnsureSchema($this->pdo());
        } catch (Throwable $exception) {
            error_log('EventsLifecycle schema seed failed: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<int,Migration>
     */
    private function migrations(): array
    {
        $directory = dirname(__DIR__) . '/Database/migrations';
        if (!is_dir($directory)) {
            return [];
        }

        $migrations = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $candidate = require $file;
            if ($candidate instanceof Migration) {
                $migrations[] = $candidate;
            }
        }

        usort($migrations, static fn (Migration $a, Migration $b): int => strcmp($a->name(), $b->name()));

        return $migrations;
    }

    private function pdo(): PDO
    {
        $pdo = Database::connection();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Events lifecycle requires an active PDO connection.');
        }

        return $pdo;
    }
}
