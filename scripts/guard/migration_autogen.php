#!/usr/bin/env php
<?php

declare(strict_types=1);

exit(main($argv));

function main(array $argv): int
{
    $parsed = parseArguments($argv);
    if ($parsed['error'] !== null) {
        fwrite(STDERR, '[migration-autogen] ' . $parsed['error'] . PHP_EOL);
        fwrite(STDERR, usageText());

        return 2;
    }

    if ($parsed['help']) {
        fwrite(STDOUT, usageText());

        return 0;
    }

    $repoRoot = gitRepoRoot();
    if ($repoRoot === null) {
        fwrite(STDERR, '[migration-autogen] Git repo root bulunamadi.' . PHP_EOL);

        return 2;
    }

    if (!@chdir($repoRoot)) {
        fwrite(STDERR, '[migration-autogen] Proje kokune gecilemedi: ' . $repoRoot . PHP_EOL);

        return 2;
    }

    $diffArgs = ['--diff-filter=ACMR'];
    if ($parsed['cached']) {
        $diffArgs[] = '--cached';
    }
    if ($parsed['range'] !== null) {
        $diffArgs[] = $parsed['range'];
    } elseif (!$parsed['cached']) {
        $diffArgs[] = 'HEAD';
    }

    $changedFiles = gitOutput(array_merge(['diff', '--name-only'], $diffArgs));
    if ($changedFiles === null) {
        return 2;
    }

    $changedPaths = [];
    foreach ($changedFiles as $line) {
        $path = normalizePath($line);
        if ($path !== '') {
            $changedPaths[$path] = true;
        }
    }
    $changedPaths = array_keys($changedPaths);

    if ($changedPaths === []) {
        fwrite(STDOUT, '[migration-autogen] Degisiklik yok.' . PHP_EOL);

        return 0;
    }

    $migrationFiles = [];
    $dbSensitivePaths = [];
    foreach ($changedPaths as $path) {
        if (isMigrationPath($path)) {
            $migrationFiles[] = $path;
        }

        if (isDbSensitivePath($path)) {
            $dbSensitivePaths[] = $path;
        }
    }

    $diffPatch = gitOutput(array_merge(['diff', '--unified=0'], $diffArgs));
    if ($diffPatch === null) {
        return 2;
    }

    $ddlSignals = detectDdlSignals($diffPatch);
    $requiresMigration = $dbSensitivePaths !== [] || $ddlSignals !== [];

    if (!$requiresMigration) {
        fwrite(STDOUT, '[migration-autogen] Migration gerektiren DB degisikligi tespit edilmedi.' . PHP_EOL);

        return 0;
    }

    if ($migrationFiles !== []) {
        fwrite(STDOUT, '[migration-autogen] Zaten migration dosyasi var, otomatik olusturma atlandi.' . PHP_EOL);
        foreach ($migrationFiles as $migration) {
            fwrite(STDOUT, '  - ' . $migration . PHP_EOL);
        }

        return 0;
    }

    $target = chooseMigrationTarget($repoRoot, $dbSensitivePaths);
    $slug = sanitizeSlug((string) ($parsed['name'] ?? 'auto_db_change'));
    $migrationPath = uniqueMigrationPath($target['directory'], date('Y_m_d_His') . '_' . $slug . '.sql');
    $relativeMigrationPath = relativePath($repoRoot, $migrationPath);

    $autoStatements = extractStandaloneDdlStatements($diffPatch);
    $content = renderMigrationContent(
        $target['scope'],
        $changedPaths,
        $dbSensitivePaths,
        $ddlSignals,
        $autoStatements,
    );

    if (@file_put_contents($migrationPath, $content) === false) {
        fwrite(STDERR, '[migration-autogen] Migration dosyasi yazilamadi: ' . $migrationPath . PHP_EOL);

        return 2;
    }

    fwrite(STDOUT, '[migration-autogen] Migration dosyasi olusturuldu: ' . $relativeMigrationPath . PHP_EOL);

    if ($parsed['stage']) {
        $add = gitOutput(['add', '--', $relativeMigrationPath]);
        if ($add === null) {
            fwrite(STDERR, '[migration-autogen] Migration dosyasi stage edilemedi.' . PHP_EOL);

            return 2;
        }

        fwrite(STDOUT, '[migration-autogen] Migration dosyasi stage edildi.' . PHP_EOL);
    }

    fwrite(STDOUT, "[migration-autogen] Dosyayi gozden gecirip gerekirse SQL'i netlestirin." . PHP_EOL);

    return 0;
}

/**
 * @return array{cached:bool,range:?string,stage:bool,name:?string,help:bool,error:?string}
 */
function parseArguments(array $argv): array
{
    $options = [
        'cached' => false,
        'range' => null,
        'stage' => false,
        'name' => null,
        'help' => false,
        'error' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--cached') {
            $options['cached'] = true;
            continue;
        }

        if ($arg === '--stage') {
            $options['stage'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if (str_starts_with($arg, '--range=')) {
            $value = trim((string) substr($arg, 8));
            if ($value === '') {
                $options['error'] = 'Bos --range degeri kullanilamaz.';

                return $options;
            }

            $options['range'] = $value;
            continue;
        }

        if (str_starts_with($arg, '--name=')) {
            $value = trim((string) substr($arg, 7));
            if ($value === '') {
                $options['error'] = 'Bos --name degeri kullanilamaz.';

                return $options;
            }

            $options['name'] = $value;
            continue;
        }

        $options['error'] = 'Bilinmeyen arguman: ' . $arg;

        return $options;
    }

    if ($options['cached'] && $options['range'] !== null) {
        $options['error'] = '--cached ve --range ayni anda kullanilamaz.';
    }

    return $options;
}

function usageText(): string
{
    return <<<TXT
Usage:
  php scripts/guard/migration_autogen.php [--cached] [--stage] [--name=<slug>]
  php scripts/guard/migration_autogen.php --range=<git-range> [--stage] [--name=<slug>]

Examples:
  php scripts/guard/migration_autogen.php --cached --stage
  php scripts/guard/migration_autogen.php --range=origin/main..HEAD --name=events_scope_update

TXT;
}

/**
 * @param array<int,string> $changedPaths
 * @return array{directory:string,scope:string}
 */
function chooseMigrationTarget(string $repoRoot, array $changedPaths): array
{
    $moduleIds = [];
    foreach ($changedPaths as $path) {
        if (preg_match('#^includes/src/Modules/([^/]+)/#', $path, $matches) === 1) {
            $moduleIds[strtolower($matches[1])] = $matches[1];
        }
    }

    if (count($moduleIds) === 1) {
        $moduleName = array_values($moduleIds)[0];
        $moduleMigrationDir = $repoRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'migrations';
        if (is_dir($moduleMigrationDir) || @mkdir($moduleMigrationDir, 0775, true) || is_dir($moduleMigrationDir)) {
            return [
                'directory' => $moduleMigrationDir,
                'scope' => 'module:' . $moduleName,
            ];
        }
    }

    $rootMigrationDir = $repoRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    if (!is_dir($rootMigrationDir) && !@mkdir($rootMigrationDir, 0775, true) && !is_dir($rootMigrationDir)) {
        throw new RuntimeException('Root migration dizini olusturulamadi: ' . $rootMigrationDir);
    }

    return [
        'directory' => $rootMigrationDir,
        'scope' => 'root',
    ];
}

function sanitizeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? $value : 'auto_db_change';
}

function uniqueMigrationPath(string $directory, string $fileName): string
{
    $directory = rtrim($directory, DIRECTORY_SEPARATOR);
    $path = $directory . DIRECTORY_SEPARATOR . $fileName;
    if (!file_exists($path)) {
        return $path;
    }

    $base = pathinfo($fileName, PATHINFO_FILENAME);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $index = 2;
    while (true) {
        $candidate = $directory . DIRECTORY_SEPARATOR . $base . '_v' . $index . ($extension !== '' ? '.' . $extension : '');
        if (!file_exists($candidate)) {
            return $candidate;
        }
        $index++;
    }
}

/**
 * @param array<int,string> $allPaths
 * @param array<int,string> $dbSensitivePaths
 * @param array<int,string> $ddlSignals
 * @param array<int,string> $autoStatements
 */
function renderMigrationContent(
    string $scope,
    array $allPaths,
    array $dbSensitivePaths,
    array $ddlSignals,
    array $autoStatements,
): string {
    $lines = [];
    $lines[] = '-- ============================================================================';
    $lines[] = '-- AUTO-GENERATED MIGRATION STUB';
    $lines[] = '-- ============================================================================';
    $lines[] = '-- Generated by: scripts/guard/migration_autogen.php';
    $lines[] = '-- Generated at: ' . date('c');
    $lines[] = '-- Scope: ' . $scope;
    $lines[] = '--';
    $lines[] = '-- IMPORTANT: Review and adjust SQL before push/deploy.';
    $lines[] = '-- This file is generated to keep migration discipline automatic.';
    $lines[] = '-- ============================================================================';
    $lines[] = '';

    if ($dbSensitivePaths !== []) {
        $lines[] = '-- DB-sensitive file changes:';
        foreach ($dbSensitivePaths as $path) {
            $lines[] = '--   - ' . $path;
        }
        $lines[] = '';
    }

    if ($ddlSignals !== []) {
        $lines[] = '-- Detected DDL signals in diff (preview):';
        foreach ($ddlSignals as $signal) {
            $lines[] = '--   - ' . $signal;
        }
        $lines[] = '';
    } else {
        $lines[] = '-- No direct DDL signal line was detected in the diff.';
        $lines[] = '-- Changed files were still flagged as DB-sensitive:';
        foreach ($allPaths as $path) {
            $lines[] = '--   - ' . $path;
        }
        $lines[] = '';
    }

    if ($autoStatements !== []) {
        $lines[] = '-- Candidate SQL statements auto-detected from added lines:';
        foreach ($autoStatements as $statement) {
            $lines[] = $statement;
        }
        $lines[] = '';
    } else {
        $lines[] = '-- TODO: Add SQL statements below.';
        $lines[] = '-- Example:';
        $lines[] = '-- ALTER TABLE `example_table` ADD COLUMN `example_column` VARCHAR(191) NULL;';
        $lines[] = '-- ALTER TABLE `example_table` ADD INDEX `example_index` (`example_column`);';
        $lines[] = '';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function normalizePath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '.' || $path === './') {
        return '';
    }

    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    return ltrim($path, '/');
}

function relativePath(string $repoRoot, string $path): string
{
    $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
    $path = str_replace('\\', '/', $path);
    if ($repoRoot !== '' && str_starts_with($path, $repoRoot . '/')) {
        return ltrim(substr($path, strlen($repoRoot)), '/');
    }

    return ltrim($path, '/');
}

function isMigrationPath(string $path): bool
{
    if (preg_match('#^database/migrations/.+\.(sql|php)$#i', $path) === 1) {
        return true;
    }

    return preg_match('#^includes/src/Modules/[^/]+/Database/migrations/.+\.(sql|php)$#i', $path) === 1;
}

function isDbSensitivePath(string $path): bool
{
    if (preg_match('#^database/(?!migrations/).+\.(sql|php)$#i', $path) === 1) {
        return true;
    }

    return preg_match('#^includes/src/Modules/[^/]+/Database/(?!migrations/).+\.(sql|php)$#i', $path) === 1;
}

/**
 * @param array<int,string> $lines
 * @return array<int,string>
 */
function detectDdlSignals(array $lines): array
{
    $signals = [];
    $patterns = ddlPatterns();

    foreach ($lines as $line) {
        if ($line === '' || !(str_starts_with($line, '+') || str_starts_with($line, '-'))) {
            continue;
        }

        if (str_starts_with($line, '+++') || str_starts_with($line, '---')) {
            continue;
        }

        $payload = trim((string) substr($line, 1));
        if ($payload === '') {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                $signals[] = $payload;
                if (count($signals) >= 15) {
                    return $signals;
                }
                break;
            }
        }
    }

    return $signals;
}

/**
 * @param array<int,string> $lines
 * @return array<int,string>
 */
function extractStandaloneDdlStatements(array $lines): array
{
    $statements = [];
    $seen = [];
    $patterns = ddlPatterns();

    foreach ($lines as $line) {
        if (!str_starts_with($line, '+') || str_starts_with($line, '+++')) {
            continue;
        }

        $payload = trim((string) substr($line, 1));
        if ($payload === '' || str_starts_with($payload, '--') || str_starts_with($payload, '#')) {
            continue;
        }

        // If the line is a PHP $pdo->exec("..."); call, extract the inner SQL string.
        if (preg_match('/^\$\w+\s*->\s*\w+\s*\(\s*["\'](.+?)["\']\s*\)\s*;$/', $payload, $m) === 1) {
            $payload = rtrim(trim($m[1]), ';') . ';';
        }

        if (!str_ends_with($payload, ';')) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                if (!isset($seen[$payload])) {
                    $seen[$payload] = true;
                    $statements[] = $payload;
                }
                break;
            }
        }
    }

    return $statements;
}

/**
 * @return array<int,string>
 */
function ddlPatterns(): array
{
    return [
        '/\b(CREATE|ALTER|DROP|RENAME|TRUNCATE)\s+TABLE\b/i',
        '/\b(ADD|DROP|MODIFY|CHANGE)\s+COLUMN\b/i',
        '/\b(CREATE|DROP)\s+(UNIQUE\s+)?INDEX\b/i',
        '/\bADD\s+(UNIQUE\s+)?KEY\b/i',
        '/\b(FOREIGN|PRIMARY)\s+KEY\b/i',
        '/\b(CREATE|ALTER|DROP)\s+DATABASE\b/i',
    ];
}

/**
 * @param array<int,string> $args
 * @return array<int,string>|null
 */
function gitOutput(array $args): ?array
{
    $command = 'git';
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
    $command .= ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, '[migration-autogen] Git komutu basarisiz: ' . $command . PHP_EOL);
        foreach ($output as $line) {
            fwrite(STDERR, '  ' . $line . PHP_EOL);
        }

        return null;
    }

    return array_map('strval', $output);
}

function gitRepoRoot(): ?string
{
    $output = gitOutput(['rev-parse', '--show-toplevel']);
    if ($output === null || $output === []) {
        return null;
    }

    $root = trim($output[0]);

    return $root !== '' ? $root : null;
}
