<?php

declare(strict_types=1);

require_once dirname(__DIR__, 6) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/init.php';

function eventsApiMethod(array $methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        sendMethodNotAllowed($methods);
    }
}

function eventsApiRequireAuth(): int
{
    $userId = (int)($_SESSION['_auth_user_id'] ?? 0);
    if ($userId <= 0) {
        sendUnauthorized('Etkinlik işlemleri için giriş yapmalısınız.');
    }
    return $userId;
}

function eventsApiRequireAdmin(): int
{
    $userId = eventsApiRequireAuth();
    if (!function_exists('userHasPermission') || !userHasPermission($GLOBALS['pdo'] ?? null, $userId, 'events.manage')) {
        sendForbidden('Bu işlem için etkinlik yönetimi yetkisi (events.manage) gereklidir.');
    }
    return $userId;
}

function eventsApiPayload(): array
{
    $payload = [];
    $raw = (string)file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    return array_merge($_POST, $payload);
}

function eventsApiVerifyCsrf(array $payload): void
{
    $token = (string)($payload['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!verify_csrf_token($token)) {
        sendCsrfError();
    }
}

function eventsApiEnsureReady(?PDO $pdo, ?string $feature = null): void
{
    if (!$pdo || !eventsTablesReady($pdo)) {
        sendError('events_schema_missing', 'Etkinlik tablolari hazir degil. database/schema.sql kurulumu tamamlanmali.', 503);
    }
    
    $config = eventsGetConfig($pdo, true);

    if ($feature !== null) {
        $gate = eventsFeatureGate($config, $feature);
        if (!$gate['enabled']) {
            sendError((string)$gate['reason'], (string)$gate['message'], 503);
        }
    }
    
    $userId = (int)($_SESSION['_auth_user_id'] ?? 0);
    if ($userId > 0) {
        $nowTime = time();
        $rateLimitWindow = max(1, (int)($config['api_rate_limit_window'] ?? 60)); // Default 60 seconds
        $rateLimitMax = max(1, (int)($config['api_rate_limit_max'] ?? 45)); // Default 45 requests per window

        if (!isset($_SESSION['events_api_hits']) || !is_array($_SESSION['events_api_hits'])) {
            $_SESSION['events_api_hits'] = [];
        }
        
        // Clean old hits
        $_SESSION['events_api_hits'] = array_filter($_SESSION['events_api_hits'], static function($timestamp) use ($nowTime, $rateLimitWindow) {
            return $nowTime - $timestamp < $rateLimitWindow;
        });
        
        if (count($_SESSION['events_api_hits']) >= $rateLimitMax) {
            sendError('rate_limit_exceeded', 'Çok fazla işlem yaptınız. Lütfen 1 dakika bekleyip tekrar deneyin.', 429);
        }
        
        $_SESSION['events_api_hits'][] = $nowTime;

        $minAge = max(0, (int)($config['events_min_account_age_days'] ?? 0));
        $minMessages = max(0, (int)($config['events_min_messages'] ?? 0));
        
        if ($minAge > 0 || $minMessages > 0) {
            $stmt = $pdo->prepare("SELECT created_at, total_topics, total_comments FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                if ($minAge > 0) {
                    $createdAt = new DateTimeImmutable((string)$user['created_at']);
                    $now = new DateTimeImmutable();
                    $diff = $now->diff($createdAt)->days;
                    if ($diff < $minAge) {
                        sendError('account_too_new', "Etkinlik sistemini kullanabilmek için üyeliğinizin en az $minAge günlük olması gereklidir.", 403);
                    }
                }
                
                if ($minMessages > 0) {
                    $totalMessages = (int)($user['total_topics'] ?? 0) + (int)($user['total_comments'] ?? 0);
                    if ($totalMessages < $minMessages) {
                        sendError('not_enough_messages', "Etkinlik sistemini kullanabilmek için en az $minMessages mesaja (konu/yorum) sahip olmalısınız.", 403);
                    }
                }
            }
        }
    }
}
