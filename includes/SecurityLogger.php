<?php

declare(strict_types=1);

/**
 * Security Event Logger
 * Logs security-related events for audit and monitoring
 */

function logSecurityEvent(PDO $pdo, string $eventType, array $context = []): void
{
    try {
        $userId = $_SESSION['_auth_user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO security_events
            (event_type, user_id, ip_address, user_agent, request_uri, context, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $eventType,
            $userId,
            $ipAddress,
            substr($userAgent, 0, 255),
            substr($requestUri, 0, 255),
            json_encode($context, JSON_UNESCAPED_UNICODE)
        ]);

        if ($eventType !== 'successful_login' && function_exists('userActivityLog')) {
            $eventUserId = isset($context['user_id']) && is_numeric($context['user_id']) ? (int) $context['user_id'] : ($userId ? (int) $userId : null);
            $activityEventType = $eventType === 'failed_login' ? 'user_login_failed' : $eventType;
            userActivityLog(
                $pdo,
                $eventUserId,
                $activityEventType,
                $eventType === 'failed_login' ? 'auth' : 'security',
                'security_event',
                null,
                function_exists('userActivityEventLabel') ? userActivityEventLabel($activityEventType) : $activityEventType,
                $context,
                $eventUserId
            );
        }
    } catch (Throwable $e) {
        // Silent fail - don't break application flow
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Log failed login attempt
 */
function logFailedLogin(PDO $pdo, string $email, string $reason = 'invalid_credentials'): void
{
    logSecurityEvent($pdo, 'failed_login', [
        'email' => $email,
        'reason' => $reason,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log successful login
 */
function logSuccessfulLogin(PDO $pdo, int $userId, string $email): void
{
    logSecurityEvent($pdo, 'successful_login', [
        'user_id' => $userId,
        'email' => $email,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log rate limit exceeded
 */
function logRateLimitExceeded(PDO $pdo, string $endpoint, string $limitType = 'general'): void
{
    logSecurityEvent($pdo, 'rate_limit_exceeded', [
        'endpoint' => $endpoint,
        'limit_type' => $limitType,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log CSRF token failure
 */
function logCsrfFailure(PDO $pdo, string $endpoint): void
{
    logSecurityEvent($pdo, 'csrf_failure', [
        'endpoint' => $endpoint,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log unauthorized access attempt
 */
function logUnauthorizedAccess(PDO $pdo, string $resource, string $action = 'access'): void
{
    logSecurityEvent($pdo, 'unauthorized_access', [
        'resource' => $resource,
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log suspicious activity
 */
function logSuspiciousActivity(PDO $pdo, string $activityType, array $details = []): void
{
    logSecurityEvent($pdo, 'suspicious_activity', array_merge([
        'activity_type' => $activityType,
        'timestamp' => date('Y-m-d H:i:s')
    ], $details));
}

/**
 * Ensure security_events table exists
 */
function ensureSecurityEventsTable(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute(['security_events']);
    if ((int) $stmt->fetchColumn() === 0) {
        throw new RuntimeException('Missing security_events; run Admin Panel > Database Synchronization.');
    }
}
