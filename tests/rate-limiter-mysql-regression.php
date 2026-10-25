<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$config = App\Core\DatabaseConnection::getEnvConfig();
if (!in_array(strtolower((string) ($config['DB_HOST'] ?? '127.0.0.1')), ['localhost', '127.0.0.1', '::1'], true)) {
    echo 'SKIP: rate-limit integration requires a local MySQL connection.' . PHP_EOL;
    exit;
}
$pdo = App\Core\DatabaseConnection::connection();
if (!$pdo) { throw new RuntimeException('Local MySQL connection unavailable.'); }
$table = 'regression_rate_' . bin2hex(random_bytes(8));
$pdo->exec("CREATE TEMPORARY TABLE `{$table}` (
    scope VARCHAR(100) NOT NULL, rate_key VARCHAR(191) NOT NULL, attempt_count INT NOT NULL,
    first_attempt_at TIMESTAMP NULL, last_attempt_at TIMESTAMP NULL, expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, UNIQUE KEY (scope,rate_key)
) ENGINE=InnoDB");
$phpTimezone = date_default_timezone_get();
$databaseTimezone = $pdo->query('SELECT @@session.time_zone')->fetchColumn();
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
try {
    foreach (['+00:00', '+03:00'] as $databaseZone) {
        $pdo->exec('SET SESSION time_zone = ' . $pdo->quote($databaseZone));
        foreach (['UTC', 'Europe/Istanbul', 'America/New_York'] as $phpZone) {
            date_default_timezone_set($phpZone);
            $pdo->exec("DELETE FROM `{$table}`");
            $limiter = new App\Core\Security\DatabaseRateLimiter($pdo, 'regression', $table);
            $assert(!$limiter->tooManyAttempts('client', 2, 60), 'Fresh client was blocked');
            $limiter->hit('client', 60);
            $limiter->hit('client', 60);
            $assert($limiter->tooManyAttempts('client', 2, 60), 'Two hits did not enforce the limit');
            $remaining = $limiter->availableIn('client', 60);
            $assert($remaining >= 55 && $remaining <= 60, 'Remaining seconds depended on the PHP timezone');
            $assert((int) $pdo->query("SELECT attempt_count FROM `{$table}`")->fetchColumn() === 2, 'Counter unexpectedly reset');
            $pdo->exec("UPDATE `{$table}` SET expires_at=DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $assert(!$limiter->tooManyAttempts('client', 2, 60) && $limiter->availableIn('client', 60) === 0, 'Expired key remained blocked');
            $assert((int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() === 1, 'Read operation deleted state that could be concurrently renewed');
            $limiter->hit('client', 60);
            $assert((int) $pdo->query("SELECT attempt_count FROM `{$table}`")->fetchColumn() === 1, 'Expired counter was not reset on hit');
            $pdo->exec("UPDATE `{$table}` SET expires_at=NULL, attempt_count=5");
            $assert(!$limiter->tooManyAttempts('client', 2, 60), 'NULL expiry blocked a client indefinitely');
            $limiter->hit('client', 60);
            $assert((int) $pdo->query("SELECT attempt_count FROM `{$table}`")->fetchColumn() === 1, 'NULL-expiry counter was not reset');
            $limiter->clear('client');
            $assert($limiter->availableIn('client', 60) === 0, 'Cleared state remained active');
        }
    }
    echo 'PASS: ' . $checks . ' MySQL rate-limit checks across six PHP/database timezone combinations.' . PHP_EOL;
} finally {
    date_default_timezone_set($phpTimezone);
    $pdo->exec('SET SESSION time_zone = ' . $pdo->quote((string) $databaseTimezone));
    $pdo->exec("DROP TEMPORARY TABLE `{$table}`");
}
