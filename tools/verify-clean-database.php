<?php

declare(strict_types=1);

use App\Core\Database\DatabaseSyncService;
use App\Core\DatabaseConnection;
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
require_once $root . '/includes/autoloader.php';
require_once $root . '/includes/lib/logger.php';

if (!function_exists('safeErrorMessage')) {
    function safeErrorMessage(\Throwable $exception, string $fallback = 'Operation failed.'): string
    {
        return $exception->getMessage() !== '' ? $exception->getMessage() : $fallback;
    }
}

$env = DatabaseConnection::getEnvConfig();
$host = (string) ($env['DB_HOST'] ?? '127.0.0.1');
$port = (string) ($env['DB_PORT'] ?? '3306');
$username = (string) ($env['DB_USERNAME'] ?? 'root');
$password = (string) ($env['DB_PASSWORD'] ?? '');
$database = 'yenidosyalar_verify_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $server = new \PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES => false,
]);

$exitCode = 0;
try {
    $server->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $username, $password, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $schema = (string) file_get_contents($root . '/database/schema.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $reflection = new \ReflectionClass(DatabaseConnection::class);
    $envProperty = $reflection->getProperty('envCache');
    $envProperty->setAccessible(true);
    $envProperty->setValue(null, array_merge($env, ['DB_DATABASE' => $database]));
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdoProperty->setValue(null, $pdo);

    require_once $root . '/includes/helpers.php';
    require_once $root . '/includes/ErrorHandler.php';
    $report = (new DatabaseSyncService(projectRoot: $root))->run(true);
    if (($report['status'] ?? '') !== 'success' || (int) ($report['summary']['failed'] ?? 0) !== 0) {
        throw new \RuntimeException('Database Synchronization failed: ' . json_encode($report['errors'] ?? [], JSON_UNESCAPED_UNICODE));
    }

    $requiredTables = [
        'admin_settings', 'users', 'topics', 'comments', 'topic_reports',
        'message_threads', 'notification_templates', 'contact_messages',
        'ban_appeals', 'core_cache', 'events_migrations', 'events_tasks',
    ];
    $tableQuery = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    foreach ($requiredTables as $table) {
        $tableQuery->execute([$table]);
        if ((int) $tableQuery->fetchColumn() === 0) {
            throw new \RuntimeException('Required table missing after synchronization: ' . $table);
        }
    }

    foreach (['settings', 'ratings', 'reactions', 'reports', 'pages', 'permissions'] as $obsoleteTable) {
        $tableQuery->execute([$obsoleteTable]);
        if ((int) $tableQuery->fetchColumn() > 0) {
            throw new \RuntimeException('Obsolete table remains after synchronization: ' . $obsoleteTable);
        }
    }

    echo json_encode([
        'status' => 'ok',
        'database' => $database,
        'root_pending' => (int) ($report['summary']['root_pending'] ?? 0),
        'modules_pending' => (int) ($report['summary']['modules_pending'] ?? 0),
        'failed' => (int) ($report['summary']['failed'] ?? 0),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    $server->exec('DROP DATABASE IF EXISTS `' . $database . '`');
}

exit($exitCode);
