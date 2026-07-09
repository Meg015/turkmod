<?php

declare(strict_types=1);

/**
 * Import XenForo users into the local users table.
 *
 * Preview:
 *   php cron/import-xenforo-users.php --dry-run
 *
 * Import:
 *   php cron/import-xenforo-users.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

set_time_limit(0);

$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require_once __DIR__ . '/../includes/init.php';

use App\Core\Database;

$options = getopt('', [
    'dry-run',
    'source-db::',
    'limit::',
    'help',
]);

if (isset($options['help'])) {
    echo "XenForo User Importer\n";
    echo "Usage: php cron/import-xenforo-users.php [--dry-run] [--source-db=turkmodxen] [--limit=100]\n";
    exit(0);
}

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Target database connection is not available.\n");
    exit(1);
}

$env = Database::getEnvConfig();
$sourceDb = (string)($options['source-db'] ?? 'turkmodxen');
$dryRun = isset($options['dry-run']);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 0;

try {
    $sourcePdo = xfImportSourceConnection($env, $sourceDb);
    $stats = xfImportUsers($sourcePdo, $pdo, $dryRun, $limit);

    echo "XenForo user import " . ($dryRun ? "dry-run" : "completed") . "\n";
    foreach ($stats as $key => $value) {
        echo $key . '=' . $value . "\n";
    }

    exit($stats['failed'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}

function xfImportSourceConnection(array $env, string $database): PDO
{
    $host = (string)($env['DB_HOST'] ?? '127.0.0.1');
    $port = (string)($env['DB_PORT'] ?? '3306');
    $username = (string)($env['DB_USERNAME'] ?? 'root');
    $password = (string)($env['DB_PASSWORD'] ?? '');
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ]);
    $pdo->exec('SET CHARACTER SET utf8mb4');

    return $pdo;
}

function xfImportUsers(PDO $sourcePdo, PDO $targetPdo, bool $dryRun, int $limit): array
{
    $memberGroupId = xfImportMemberGroupId($targetPdo);
    $rows = xfImportFetchUsers($sourcePdo, $limit);

    $stats = [
        'selected' => count($rows),
        'imported' => 0,
        'skipped_existing_email' => 0,
        'skipped_missing_hash' => 0,
        'active' => 0,
        'banned' => 0,
        'moderated' => 0,
        'failed' => 0,
    ];

    $emailExists = $targetPdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
    $insert = $targetPdo->prepare(
        "INSERT INTO users
            (name, email, email_verified_at, password, status, is_banned, banned_at, ban_reason,
             password_changed_at, created_at, updated_at, last_activity_at, public_profile, public_show_topics,
             public_show_comments, public_show_socials)
         VALUES
            (:name, :email, :email_verified_at, :password, :status, :is_banned, :banned_at, :ban_reason,
             NULL, :created_at, NOW(), :last_activity_at, 1, 1, 0, 1)"
    );
    $assignGroup = $targetPdo->prepare("INSERT IGNORE INTO user_group_members
        (user_id, group_id, is_primary, reason, created_at, updated_at)
        VALUES (?, ?, 1, 'xenforo_import', NOW(), NOW())");

    if (!$dryRun) {
        $targetPdo->beginTransaction();
    }

    try {
        foreach ($rows as $row) {
            try {
                $email = trim((string)($row['email'] ?? ''));
                if ($email === '') {
                    $stats['skipped_missing_hash']++;
                    continue;
                }

                $emailExists->execute([$email]);
                if ($emailExists->fetchColumn()) {
                    $stats['skipped_existing_email']++;
                    continue;
                }

                $hash = xfImportExtractPasswordHash($row['auth_data'] ?? null);
                if ($hash === '') {
                    $stats['skipped_missing_hash']++;
                    continue;
                }

                $mapped = xfImportMapUserState($row);
                $stats[$mapped['bucket']]++;

                if ($dryRun) {
                    $stats['imported']++;
                    continue;
                }

                $insert->execute([
                    'name' => xfImportCleanName((string)($row['username'] ?? '')),
                    'email' => $email,
                    'email_verified_at' => $mapped['email_verified_at'],
                    'password' => $hash,
                    'status' => $mapped['status'],
                    'is_banned' => $mapped['is_banned'],
                    'banned_at' => $mapped['banned_at'],
                    'ban_reason' => $mapped['ban_reason'],
                    'created_at' => xfImportTimestamp((int)($row['register_date'] ?? 0)),
                    'last_activity_at' => xfImportTimestamp((int)($row['last_activity'] ?? 0)),
                ]);
                $assignGroup->execute([(int)$targetPdo->lastInsertId(), $memberGroupId]);
                $stats['imported']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                fwrite(STDERR, "Failed user #" . (int)($row['user_id'] ?? 0) . ": " . $e->getMessage() . "\n");
            }
        }

        if (!$dryRun) {
            $targetPdo->commit();
        }
    } catch (Throwable $e) {
        if (!$dryRun && $targetPdo->inTransaction()) {
            $targetPdo->rollBack();
        }
        throw $e;
    }

    return $stats;
}

function xfImportMemberGroupId(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT id FROM user_groups WHERE slug = 'member' OR is_default = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
    $groupId = (int)$stmt->fetchColumn();
    if ($groupId <= 0) {
        throw new RuntimeException('Member group was not found.');
    }

    return $groupId;
}

function xfImportFetchUsers(PDO $pdo, int $limit): array
{
    $sql = "SELECT u.user_id, u.username, u.email, u.user_state, u.is_banned,
                   u.register_date, u.last_activity, a.scheme_class, a.data AS auth_data
            FROM xf_user u
            LEFT JOIN xf_user_authenticate a ON a.user_id = u.user_id
            ORDER BY u.user_id ASC";

    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function xfImportExtractPasswordHash(mixed $data): string
{
    if ($data === null || $data === '') {
        return '';
    }

    $raw = is_resource($data) ? stream_get_contents($data) : (string)$data;
    if ($raw === '') {
        return '';
    }

    $parsed = unserialize($raw, ['allowed_classes' => false]);
    if ($parsed === false && $raw !== serialize(false)) {
        return '';
    }
    if (!is_array($parsed)) {
        return '';
    }

    $hash = (string)($parsed['hash'] ?? '');
    return preg_match('/^\$2[ayb]\$/', $hash) === 1 ? $hash : '';
}

function xfImportMapUserState(array $row): array
{
    $isBanned = (int)($row['is_banned'] ?? 0) === 1;
    $state = (string)($row['user_state'] ?? 'valid');

    if ($isBanned) {
        return [
            'bucket' => 'banned',
            'status' => 'banned',
            'is_banned' => 1,
            'banned_at' => date('Y-m-d H:i:s'),
            'ban_reason' => 'XenForo import: banli hesap',
            'email_verified_at' => $state === 'valid' ? xfImportTimestamp((int)($row['register_date'] ?? 0)) : null,
        ];
    }

    if ($state !== 'valid') {
        return [
            'bucket' => 'moderated',
            'status' => 'pending',
            'is_banned' => 0,
            'banned_at' => null,
            'ban_reason' => null,
            'email_verified_at' => null,
        ];
    }

    return [
        'bucket' => 'active',
        'status' => 'active',
        'is_banned' => 0,
        'banned_at' => null,
        'ban_reason' => null,
        'email_verified_at' => xfImportTimestamp((int)($row['register_date'] ?? 0)),
    ];
}

function xfImportTimestamp(int $timestamp): ?string
{
    if ($timestamp <= 0) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function xfImportCleanName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    if ($name === '') {
        return 'XenForo User';
    }

    return mb_substr($name, 0, 255, 'UTF-8');
}
