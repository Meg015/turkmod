#!/usr/bin/env php
<?php

declare(strict_types=1);

exit(main($argv));

function main(array $argv): int
{
    $parsed = parseArguments($argv);
    if ($parsed['error'] !== null) {
        fwrite(STDERR, '[migration-guard] ' . $parsed['error'] . PHP_EOL);
        fwrite(STDERR, usageText());

        return 2;
    }

    if ($parsed['help']) {
        fwrite(STDOUT, usageText());

        return 0;
    }

    $repoRoot = gitRepoRoot();
    if ($repoRoot === null) {
        fwrite(STDERR, '[migration-guard] Git repo root bulunamadi.' . PHP_EOL);

        return 2;
    }

    if (!@chdir($repoRoot)) {
        fwrite(STDERR, '[migration-guard] Proje kokune gecilemedi: ' . $repoRoot . PHP_EOL);

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
        fwrite(STDOUT, '[migration-guard] OK: Degisiklik yok.' . PHP_EOL);

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
        fwrite(STDOUT, '[migration-guard] OK: Migration gerektiren DB degisikligi tespit edilmedi.' . PHP_EOL);

        return 0;
    }

    if ($migrationFiles !== []) {
        fwrite(STDOUT, '[migration-guard] OK: DB degisikligi migration ile birlikte geliyor.' . PHP_EOL);
        fwrite(STDOUT, '[migration-guard] Migration dosyalari:' . PHP_EOL);
        foreach ($migrationFiles as $file) {
            fwrite(STDOUT, '  - ' . $file . PHP_EOL);
        }

        return 0;
    }

    fwrite(STDERR, '[migration-guard] HATA: DB degisikligi var ama migration dosyasi yok.' . PHP_EOL);
    if ($dbSensitivePaths !== []) {
        fwrite(STDERR, '[migration-guard] DB-etkili dosyalar:' . PHP_EOL);
        foreach ($dbSensitivePaths as $file) {
            fwrite(STDERR, '  - ' . $file . PHP_EOL);
        }
    }
    if ($ddlSignals !== []) {
        fwrite(STDERR, '[migration-guard] DDL sinyalleri:' . PHP_EOL);
        foreach ($ddlSignals as $line) {
            fwrite(STDERR, '  - ' . $line . PHP_EOL);
        }
    }
    fwrite(STDERR, '[migration-guard] Lutfen yeni bir migration ekleyin:' . PHP_EOL);
    fwrite(STDERR, '  - Root: database/migrations/YYYY_MM_DD_*.sql|php' . PHP_EOL);
    fwrite(STDERR, '  - Modul: includes/src/Modules/<Modul>/Database/migrations/*.sql|php' . PHP_EOL);

    return 1;
}

/**
 * @return array{cached:bool,range:?string,help:bool,error:?string}
 */
function parseArguments(array $argv): array
{
    $options = [
        'cached' => false,
        'range' => null,
        'help' => false,
        'error' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--cached') {
            $options['cached'] = true;
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
  php scripts/guard/migration_guard.php [--cached]
  php scripts/guard/migration_guard.php --range=<git-range>

Examples:
  php scripts/guard/migration_guard.php --cached
  php scripts/guard/migration_guard.php --range=origin/main..HEAD

TXT;
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
    $patterns = [
        '/\b(CREATE|ALTER|DROP|RENAME|TRUNCATE)\s+TABLE\b/i',
        '/\b(ADD|DROP|MODIFY|CHANGE)\s+COLUMN\b/i',
        '/\b(CREATE|DROP)\s+(UNIQUE\s+)?INDEX\b/i',
        '/\bADD\s+(UNIQUE\s+)?KEY\b/i',
        '/\b(FOREIGN|PRIMARY)\s+KEY\b/i',
        '/\b(CREATE|ALTER|DROP)\s+DATABASE\b/i',
    ];

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        if (!(str_starts_with($line, '+') || str_starts_with($line, '-'))) {
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
                if (count($signals) >= 10) {
                    return $signals;
                }
                break;
            }
        }
    }

    return $signals;
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
        fwrite(STDERR, '[migration-guard] Git komutu basarisiz: ' . $command . PHP_EOL);
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
