<?php

declare(strict_types=1);

/**
 * Standardized API Response Helper
 * Provides consistent error and success response formats across all API endpoints
 */

/**
 * Send a standardized JSON response
 */
function sendJsonResponse(int $statusCode, bool $success, ?string $message = null, array $data = [], ?string $errorCode = null): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => $success];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($errorCode !== null) {
        $response['error'] = $errorCode;
        $response['code'] = $errorCode;
    }

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $shouldIncludeCsrf = $requestMethod !== 'GET' || str_contains($requestUri, '/admin/');
    if ($shouldIncludeCsrf && function_exists('csrf_token') && session_status() === PHP_SESSION_ACTIVE) {
        $response['_token'] = csrf_token();
        $response['csrf_token'] = $response['_token'];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send success response
 */
function sendSuccess(string $message = 'Operation successful', array $data = [], int $statusCode = 200): void
{
    sendJsonResponse($statusCode, true, $message, $data);
}

/**
 * Send error response
 */
function sendError(string $errorCode, string $message, int $statusCode = 400, array $data = []): void
{
    sendJsonResponse($statusCode, false, $message, $data, $errorCode);
}

/**
 * Send validation error response
 */
function sendValidationError(string $message = 'Validation failed', array $errors = []): void
{
    sendError('validation_error', $message, 422, ['errors' => $errors]);
}

/**
 * Send unauthorized error
 */
function sendUnauthorized(string $message = 'Authentication required'): void
{
    sendError('unauthorized', $message, 401);
}

/**
 * Send forbidden error
 */
function sendForbidden(string $message = 'Access denied'): void
{
    sendError('forbidden', $message, 403);
}

/**
 * Send not found error
 */
function sendNotFound(string $message = 'Resource not found'): void
{
    sendError('not_found', $message, 404);
}

/**
 * Send method not allowed error
 */
function sendMethodNotAllowed(array $allowedMethods = ['GET', 'POST']): void
{
    header('Allow: ' . implode(', ', $allowedMethods));
    sendError('method_not_allowed', 'Method not allowed', 405, ['allowed_methods' => $allowedMethods]);
}

/**
 * Send rate limit error
 */
function sendRateLimitError(int $retryAfter = 60): void
{
    header('Retry-After: ' . $retryAfter);
    sendError('rate_limit_exceeded', 'Too many requests', 429, ['retry_after' => $retryAfter]);
}

/**
 * Send database unavailable error.
 */
function sendDatabaseUnavailable(?string $detail = null): void
{
    $data = [];
    $env = class_exists(\App\Core\Database::class) ? \App\Core\Database::getEnvConfig() : [];
    $appDebug = (($env['APP_DEBUG'] ?? 'false') === 'true');
    if ($appDebug && $detail !== null && $detail !== '') {
        $data['detail'] = $detail;
    }

    sendError('database_unavailable', 'Veritabanı bağlantısı kurulamadı.', 503, $data);
}

/**
 * Require a live PDO connection for API endpoints that depend on the database.
 */
function requireDatabaseConnection(?PDO $pdo = null): PDO
{
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $detail = class_exists(\App\Core\Database::class) ? \App\Core\Database::lastError() : null;
    sendDatabaseUnavailable($detail);
}

/**
 * Send server error (sanitized for production)
 */
function sendServerError(string $message = 'Internal server error', ?Throwable $exception = null): void
{
    // Log the full exception details
    if ($exception !== null && function_exists('appLogException')) {
        appLogException($exception, ['source' => $_SERVER['REQUEST_URI'] ?? 'unknown']);
    }

    // In production, don't expose internal error details
    $appDebug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
    $errorMessage = $appDebug && $exception !== null
        ? $message . ': ' . $exception->getMessage()
        : $message;

    sendError('server_error', $errorMessage, 500);
}

/**
 * Send CSRF error
 */
function sendCsrfError(): void
{
    if (function_exists('logCsrfFailure') && isset($GLOBALS['pdo'])) {
        logCsrfFailure($GLOBALS['pdo'], $_SERVER['REQUEST_URI'] ?? 'unknown');
    }
    sendError('csrf_token_invalid', 'Invalid CSRF token', 419);
}
