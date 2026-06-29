<?php

declare(strict_types=1);

namespace App\Core\Database;

use App\Core\Modules\ModuleLoader;
use PDO;
use RuntimeException;
use Throwable;

final class DatabaseSyncService
{
    public function __construct(
        private ?ModuleLoader $moduleLoader = null,
        private ?string $projectRoot = null,
    ) {
        $this->moduleLoader ??= new ModuleLoader();
    }

    public function run(bool $apply = true): array
    {
        $startedAt = microtime(true);
        $projectRoot = $this->projectRoot();
        $pdo = $this->pdo();

        $report = [
            'status' => 'running',
            'apply' => $apply,
            'started_at' => date('c'),
            'finished_at' => null,
            'duration_ms' => 0,
            'project_root' => $projectRoot,
            'lock' => [
                'name' => 'database_sync',
                'acquired' => false,
            ],
            'summary' => [
                'modules_total' => 0,
                'modules_pending' => 0,
                'modules_applied' => 0,
                'modules_skipped' => 0,
                'root_total' => 0,
                'root_pending' => 0,
                'root_applied' => 0,
                'root_skipped' => 0,
                'failed' => 0,
            ],
            'modules' => [],
            'root_migrations' => [],
            'warnings' => [],
            'errors' => [],
        ];

        $lockHandle = null;
        try {
            $lockHandle = $this->acquireLock($projectRoot);
            $report['lock']['acquired'] = is_resource($lockHandle);
            if (!is_resource($lockHandle)) {
                throw new RuntimeException('Another database sync run is already in progress.');
            }

            $modules = $this->discoverModules($pdo, $projectRoot, $report['warnings']);
            $report['modules'] = $modules;
            $report['summary']['modules_total'] = count($modules);
            $report['summary']['modules_pending'] = count(array_filter($modules, static fn (array $module): bool => ($module['status'] ?? '') === 'pending'));
            $report['summary']['modules_skipped'] = count(array_filter($modules, static fn (array $module): bool => in_array((string) ($module['status'] ?? ''), ['disabled', 'up_to_date'], true)));

            if ($apply) {
                $this->applyModules($pdo, $report['modules'], $report['summary'], $report['warnings']);
            }

            $rootMigrations = $this->discoverRootMigrations($pdo, $projectRoot, $report['warnings']);
            $report['root_migrations'] = $rootMigrations;
            $report['summary']['root_total'] = count($rootMigrations);
            $report['summary']['root_pending'] = count(array_filter($rootMigrations, static fn (array $migration): bool => ($migration['status'] ?? '') === 'pending'));
            $report['summary']['root_skipped'] = count(array_filter($rootMigrations, static fn (array $migration): bool => ($migration['status'] ?? '') === 'applied'));

            if ($apply) {
                $this->applyRootMigrations($pdo, $report['root_migrations'], $report['summary'], $report['warnings']);
            }

            $this->refreshSummaryCounts($report['modules'], $report['root_migrations'], $report['summary']);

            if ($report['summary']['failed'] > 0) {
                $report['status'] = 'error';
            } elseif ($apply) {
                $report['status'] = 'success';
            } else {
                $report['status'] = 'preview';
            }
        } catch (Throwable $exception) {
            $report['status'] = 'error';
            $report['summary']['failed']++;
            $report['errors'][] = [
                'message' => safeErrorMessage($exception, 'Veritabanı senkronizasyonu başarısız oldu.'),
                'detail' => $exception->getMessage(),
                'type' => get_class($exception),
            ];
            appLogException($exception, [
                'source' => __CLASS__,
                'apply' => $apply,
            ]);
        } finally {
            if (is_resource($lockHandle)) {
                $this->releaseLock($lockHandle);
            }

            $report['finished_at'] = date('c');
            $report['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        }

        return $report;
    }

    private function applyModules(PDO $pdo, array &$modules, array &$summary, array &$warnings): void
    {
        foreach ($modules as &$module) {
            if (($module['status'] ?? '') !== 'pending') {
                continue;
            }

            try {
                $runner = new MigrationRunner($pdo, (string) $module['migration_table']);
                $applied = 0;
                foreach ($module['migrations'] as &$migration) {
                    if (($migration['status'] ?? '') === 'applied') {
                        $summary['modules_skipped']++;
                        continue;
                    }

                    $runner->apply($migration['object']);
                    $migration['status'] = 'applied';
                    $migration['message'] = 'Applied successfully';
                    $applied++;
                    $summary['modules_applied']++;
                }
                unset($migration);

                if ((string) ($module['id'] ?? '') === 'events' && function_exists('eventsEnsureSchema')) {
                    try {
                        eventsEnsureSchema($pdo);
                        $module['legacy_seed'] = 'applied';
                    } catch (Throwable $exception) {
                        $module['legacy_seed'] = 'failed';
                        $warnings[] = 'eventsEnsureSchema failed: ' . $exception->getMessage();
                        appLogException($exception, ['source' => __CLASS__, 'module' => 'eventsEnsureSchema']);
                    }
                }

                $module['status'] = $applied > 0 ? 'applied' : 'up_to_date';
                $module['message'] = $applied > 0
                    ? $applied . ' migration applied'
                    : 'No pending migrations';
            } catch (Throwable $exception) {
                $module['status'] = 'failed';
                $module['message'] = $exception->getMessage();
                appLogException($exception, [
                    'source' => __CLASS__,
                    'module_id' => (string) ($module['id'] ?? ''),
                ]);
                throw $exception;
            }
        }
        unset($module);
    }

    private function applyRootMigrations(PDO $pdo, array &$migrations, array &$summary, array &$warnings): void
    {
        $runner = new MigrationRunner($pdo, 'database_migrations');

        foreach ($migrations as &$migration) {
            if (($migration['status'] ?? '') !== 'pending') {
                continue;
            }

            try {
                $runner->apply($migration['object']);
                $migration['status'] = 'applied';
                $migration['message'] = 'Applied successfully';
                $summary['root_applied']++;
            } catch (Throwable $exception) {
                $migration['status'] = 'failed';
                $migration['message'] = $exception->getMessage();
                appLogException($exception, [
                    'source' => __CLASS__,
                    'migration' => (string) ($migration['name'] ?? ''),
                ]);
                throw $exception;
            }
        }
        unset($migration);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function discoverModules(PDO $pdo, string $projectRoot, array &$warnings): array
    {
        $modulesRoot = $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Modules';
        if (!is_dir($modulesRoot)) {
            return [];
        }

        $modules = [];
        foreach ($this->moduleLoader->discover($modulesRoot) as $moduleId => $metadata) {
            $enabled = $this->moduleEnabled($metadata);
            $migrationDir = trim((string) ($metadata['migrations'] ?? ''));
            $entry = [
                'id' => (string) $moduleId,
                'name' => (string) ($metadata['name'] ?? $moduleId),
                'enabled' => $enabled,
                'status' => $enabled ? 'pending' : 'disabled',
                'message' => $enabled ? '' : 'Module disabled',
                'migration_table' => '-',
                'requires_modules' => array_values(array_map('strval', $metadata['requires_modules'] ?? [])),
                'migrations' => [],
                'legacy_seed' => null,
            ];

            if (!$enabled) {
                $modules[] = $entry;
                continue;
            }

            if ($migrationDir === '' || !is_dir($migrationDir)) {
                $entry['status'] = 'up_to_date';
                $entry['message'] = 'No migration directory';
                $modules[] = $entry;
                continue;
            }

            $migrationTable = $this->moduleMigrationTable((string) $moduleId);
            $entry['migration_table'] = $migrationTable;
            $runner = new MigrationRunner($pdo, $migrationTable);
            $migrationFiles = array_merge(
                glob(rtrim($migrationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [],
                glob(rtrim($migrationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [],
            );
            sort($migrationFiles, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($migrationFiles as $filePath) {
                $migration = $this->loadMigrationFile($filePath, $projectRoot, $moduleId);
                $applied = $runner->isApplied($migration);
                $entry['migrations'][] = [
                    'name' => $migration->name(),
                    'path' => $this->relativePath($projectRoot, $filePath),
                    'kind' => strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)),
                    'status' => $applied ? 'applied' : 'pending',
                    'message' => $applied ? 'Already applied' : 'Pending',
                    'object' => $migration,
                ];
            }

            $pendingCount = count(array_filter($entry['migrations'], static fn (array $migration): bool => ($migration['status'] ?? '') === 'pending'));
            if ($pendingCount === 0) {
                $entry['status'] = 'up_to_date';
                $entry['message'] = 'No pending migrations';
            } else {
                $entry['status'] = 'pending';
                $entry['message'] = $pendingCount . ' pending migration(s)';
            }

            if ($moduleId === 'events' && !function_exists('eventsEnsureSchema')) {
                $warnings[] = 'Events legacy schema seeding helper is unavailable.';
            }

            $modules[] = $entry;
        }

        return $this->sortModulesByDependency($modules);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function discoverRootMigrations(PDO $pdo, string $projectRoot, array &$warnings): array
    {
        $migrationsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        if (!is_dir($migrationsRoot)) {
            return [];
        }

        $files = array_merge(
            glob(rtrim($migrationsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [],
            glob(rtrim($migrationsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [],
        );
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $runner = new MigrationRunner($pdo, 'database_migrations');
        $entries = [];

        foreach ($files as $filePath) {
            $migration = $this->loadMigrationFile($filePath, $projectRoot, 'database');
            $applied = $runner->isApplied($migration);
            $entries[] = [
                'name' => $migration->name(),
                'path' => $this->relativePath($projectRoot, $filePath),
                'kind' => strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)),
                'status' => $applied ? 'applied' : 'pending',
                'message' => $applied ? 'Already applied' : 'Pending',
                'object' => $migration,
            ];
        }

        return $entries;
    }

    private function loadMigrationFile(string $filePath, string $projectRoot, string $namespace): Migration
    {
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            $migration = require $filePath;
            if (!$migration instanceof Migration) {
                throw new RuntimeException('Migration file must return a Migration instance: ' . $filePath);
            }

            return $migration;
        }

        if ($extension === 'sql') {
            $name = $namespace . ':' . $this->relativePath($projectRoot, $filePath);
            return new SqlFileMigration($filePath, $name);
        }

        throw new RuntimeException('Unsupported migration file type: ' . $filePath);
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     * @return array<int,array<string,mixed>>
     */
    private function sortModulesByDependency(array $modules): array
    {
        $byId = [];
        foreach ($modules as $module) {
            $moduleId = (string) ($module['id'] ?? '');
            if ($moduleId === '') {
                continue;
            }

            $byId[$moduleId] = $module;
        }

        $enabled = [];
        foreach ($byId as $moduleId => $module) {
            if (($module['enabled'] ?? false) === true) {
                $enabled[$moduleId] = $module;
            }
        }

        $orderedEnabled = [];
        $temporary = [];
        $permanent = [];
        $visit = function (string $moduleId) use (&$visit, &$byId, &$enabled, &$orderedEnabled, &$temporary, &$permanent): void {
            if (isset($permanent[$moduleId])) {
                return;
            }

            if (isset($temporary[$moduleId])) {
                throw new RuntimeException('Module dependency cycle detected around: ' . $moduleId);
            }

            if (!isset($enabled[$moduleId])) {
                throw new RuntimeException('Enabled module dependency missing: ' . $moduleId);
            }

            $temporary[$moduleId] = true;
            $dependencies = $byId[$moduleId]['requires_modules'] ?? [];
            foreach ($dependencies as $dependencyId) {
                $dependencyId = trim((string) $dependencyId);
                if ($dependencyId === '') {
                    continue;
                }

                if (!isset($enabled[$dependencyId])) {
                    throw new RuntimeException('Required module is missing or disabled: ' . $moduleId . ' -> ' . $dependencyId);
                }

                $visit($dependencyId);
            }

            $permanent[$moduleId] = true;
            $orderedEnabled[] = $byId[$moduleId];
        };

        foreach (array_keys($enabled) as $moduleId) {
            $visit($moduleId);
        }

        $disabled = [];
        foreach ($byId as $moduleId => $module) {
            if (($module['enabled'] ?? false) !== true) {
                $disabled[] = $module;
            }
        }

        return array_merge($orderedEnabled, $disabled);
    }

    private function moduleEnabled(array $metadata): bool
    {
        $enabled = $metadata['enabled'] ?? false;
        if ($enabled === true || $enabled === false) {
            return $enabled;
        }

        if (is_callable($enabled)) {
            try {
                return (bool) $enabled();
            } catch (Throwable $exception) {
                appLogException($exception, [
                    'source' => __CLASS__,
                    'module_enabled_callback' => true,
                ]);

                return false;
            }
        }

        return false;
    }

    private function moduleMigrationTable(string $moduleId): string
    {
        $moduleId = strtolower(trim($moduleId));
        $moduleId = preg_replace('/[^a-z0-9_]+/', '_', $moduleId) ?? '';
        $moduleId = trim($moduleId, '_');
        if ($moduleId === '') {
            throw new RuntimeException('Invalid module id: ' . $moduleId);
        }

        return $moduleId . '_migrations';
    }

    private function relativePath(string $projectRoot, string $path): string
    {
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $path = str_replace('\\', '/', $path);
        if ($projectRoot !== '' && str_starts_with($path, $projectRoot . '/')) {
            return ltrim(substr($path, strlen($projectRoot)), '/');
        }

        return ltrim($path, '/');
    }

    private function pdo(): PDO
    {
        $pdo = \App\Core\Database::connection();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Database sync requires an active PDO connection.');
        }

        return $pdo;
    }

    private function projectRoot(): string
    {
        if (is_string($this->projectRoot) && $this->projectRoot !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->projectRoot), DIRECTORY_SEPARATOR);
        }

        return rtrim(dirname(__DIR__, 4), DIRECTORY_SEPARATOR);
    }

    /**
     * @return resource|false
     */
    private function acquireLock(string $projectRoot)
    {
        $lockDir = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            return false;
        }

        $lockPath = $lockDir . DIRECTORY_SEPARATOR . 'database-sync.lock';
        $handle = @fopen($lockPath, 'c+');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /**
     * @param resource|false $handle
     */
    private function releaseLock($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @param array<int,array<string,mixed>> $modules
     * @param array<int,array<string,mixed>> $rootMigrations
     * @param array<string,int> $summary
     */
    private function refreshSummaryCounts(array $modules, array $rootMigrations, array &$summary): void
    {
        $summary['modules_total'] = count($modules);
        $summary['modules_pending'] = 0;
        $summary['modules_applied'] = 0;
        $summary['modules_skipped'] = 0;
        $summary['root_total'] = count($rootMigrations);
        $summary['root_pending'] = 0;
        $summary['root_applied'] = 0;
        $summary['root_skipped'] = 0;

        foreach ($modules as $module) {
            $moduleStatus = (string) ($module['status'] ?? '');
            if ($moduleStatus === 'disabled' || $moduleStatus === 'up_to_date') {
                $summary['modules_skipped']++;
            } elseif ($moduleStatus === 'failed') {
                $summary['modules_pending']++;
            }

            foreach (($module['migrations'] ?? []) as $migration) {
                $migrationStatus = (string) ($migration['status'] ?? '');
                if ($migrationStatus === 'pending') {
                    $summary['modules_pending']++;
                } elseif ($migrationStatus === 'applied') {
                    $summary['modules_applied']++;
                }
            }
        }

        foreach ($rootMigrations as $migration) {
            $migrationStatus = (string) ($migration['status'] ?? '');
            if ($migrationStatus === 'pending') {
                $summary['root_pending']++;
            } elseif ($migrationStatus === 'applied') {
                $summary['root_applied']++;
            } else {
                $summary['root_skipped']++;
            }
        }
    }
}
