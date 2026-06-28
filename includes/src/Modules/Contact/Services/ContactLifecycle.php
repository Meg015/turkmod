<?php

declare(strict_types=1);

namespace App\Modules\Contact\Services;

use App\Core\Database;
use App\Core\Database\Migration;
use App\Core\Database\MigrationRunner;
use App\Core\Modules\ModuleLifecycle;
use PDO;
use RuntimeException;

final class ContactLifecycle implements ModuleLifecycle
{
    public function onInstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'contact_migrations');
        foreach ($this->migrations() as $migration) {
            $runner->apply($migration);
        }
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }

    public function onUninstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'contact_migrations');
        foreach (array_reverse($this->migrations()) as $migration) {
            $runner->rollback($migration);
        }
    }

    /**
     * @return list<Migration>
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
            throw new RuntimeException('Contact lifecycle requires an active PDO connection.');
        }

        return $pdo;
    }
}
