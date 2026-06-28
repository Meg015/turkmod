<?php

declare(strict_types=1);

function analyticsEnvInt(array $env, string $key, int $default, int $min, int $max): int
{
    $value = filter_var($env[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

function analyticsSanitizeValue(mixed $value, int $depth = 0): mixed
{
    if ($depth >= 3) {
        return null;
    }

    if (is_array($value)) {
        $clean = [];
        foreach (array_slice($value, 0, 30, true) as $key => $item) {
            $cleanKey = is_int($key)
                ? $key
                : mb_substr((string) preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string) $key), 0, 80);
            if ($cleanKey === '') {
                continue;
            }
            $clean[$cleanKey] = analyticsSanitizeValue($item, $depth + 1);
        }

        return $clean;
    }

    if (is_string($value)) {
        return mb_substr($value, 0, 500);
    }

    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
        return $value;
    }

    return mb_substr((string) $value, 0, 500);
}

function analyticsSanitizeUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return '';
    }

    $path = mb_substr((string) ($parts['path'] ?? '/'), 0, 1024);
    if (isset($parts['host'])) {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . $host . $port . $path;
    }

    return str_starts_with($path, '/') ? $path : '/' . $path;
}

function analyticsLogPath(string $storageDir, ?int $timestamp = null): string
{
    return rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR . 'analytics-events-' . date('Y-m-d', $timestamp ?? time()) . '.log';
}

function analyticsPruneOldLogs(string $storageDir, int $retentionDays, ?int $timestamp = null): int
{
    $cutoff = strtotime('-' . max(1, $retentionDays) . ' days', $timestamp ?? time());
    $deleted = 0;

    foreach (glob(rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR . 'analytics-events-*.log') ?: [] as $path) {
        if (!preg_match('/analytics-events-(\d{4}-\d{2}-\d{2})\.log$/', (string) $path, $matches)) {
            continue;
        }
        $logDate = strtotime($matches[1] . ' 00:00:00');
        if ($logDate !== false && $logDate < $cutoff && is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }

    return $deleted;
}
