<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Services;

use App\Core\Cache\TaggableCache;
use App\Core\DatabaseConnection;
use App\Core\Database\Migration;
use App\Core\Database\MigrationRunner;
use App\Core\Modules\ModuleLifecycle;
use PDO;
use RuntimeException;

final class LeaderboardLifecycle implements ModuleLifecycle
{
    public function __construct(private ?TaggableCache $cache = null)
    {
    }

    public function onInstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'leaderboard_migrations');
        foreach ($this->migrations() as $migration) {
            $runner->apply($migration);
        }
    }

    public function onEnable(): void
    {
        $this->invalidateLeaderboardCache();
    }

    public function onDisable(): void
    {
        $this->invalidateLeaderboardCache();
    }

    public function onUninstall(): void
    {
        $runner = new MigrationRunner($this->pdo(), 'leaderboard_migrations');
        $migrations = array_reverse($this->migrations());
        foreach ($migrations as $migration) {
            $runner->rollback($migration);
        }

        $this->invalidateLeaderboardCache();
    }

    private function invalidateLeaderboardCache(): void
    {
        (new LeaderboardCacheInvalidator($this->cache))->invalidate();
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
        $pdo = DatabaseConnection::connection();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Leaderboard lifecycle requires an active PDO connection.');
        }

        return $pdo;
    }
}
