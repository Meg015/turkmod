<?php

declare(strict_types=1);

/**
 * CSRF Protection Manager
 * Token generation, validation, and management
 */
class CsrfProtection
{
    private static $instance = null;
    private const TOKEN_LENGTH = 32;
    private const TOKEN_SESSION_KEY = '_csrf_token';
    private const TOKEN_HISTORY_SESSION_KEY = '_csrf_token_history';
    private const TOKEN_HISTORY_LIMIT = 5;
    private const TOKEN_HEADER_NAME = 'X-CSRF-Token';

    private function __construct()
    {
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * CSRF token oluştur veya mevcut olanı al
     */
    public static function token(): string
    {
        self::ensureSession();

        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            $_SESSION[self::TOKEN_SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_SESSION_KEY];
    }

    /**
     * CSRF token doğrula
     */
    public static function verify(?string $token): bool
    {
        self::ensureSession();

        if ($token === null || $token === '') {
            return false;
        }

        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        $currentToken = (string) $_SESSION[self::TOKEN_SESSION_KEY];
        if ($currentToken !== '' && hash_equals($currentToken, $token)) {
            return true;
        }

        $history = $_SESSION[self::TOKEN_HISTORY_SESSION_KEY] ?? [];
        if (!is_array($history)) {
            return false;
        }

        foreach ($history as $historicalToken) {
            if (is_string($historicalToken) && $historicalToken !== '' && hash_equals($historicalToken, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Request'ten CSRF token'ı al
     * Header, POST, JSON body'den kontrol et
     */
    public static function getFromRequest(): ?string
    {
        $token = null;

        if (isset($_SERVER['HTTP_' . str_replace('-', '_', self::TOKEN_HEADER_NAME)])) {
            $token = $_SERVER['HTTP_' . str_replace('-', '_', self::TOKEN_HEADER_NAME)];
        } elseif (isset($_POST['_token'])) {
            $token = $_POST['_token'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && isset($input['_token'])) {
                $token = $input['_token'];
            }
        }

        return $token;
    }

    /**
     * CSRF token'ı doğrula ve request'ten al
     */
    public static function validateRequest(): bool
    {
        $token = self::getFromRequest();
        return self::verify($token);
    }

    /**
     * Token'ı yenile (login sonrası)
     */
    public static function refresh(): string
    {
        self::ensureSession();

        self::rememberToken(isset($_SESSION[self::TOKEN_SESSION_KEY]) ? (string) $_SESSION[self::TOKEN_SESSION_KEY] : null);
        $_SESSION[self::TOKEN_SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_SESSION_KEY];
    }

    private static function rememberToken(?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        $history = $_SESSION[self::TOKEN_HISTORY_SESSION_KEY] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $history = array_values(array_filter(
            $history,
            static fn ($historicalToken): bool => is_string($historicalToken) && $historicalToken !== '' && !hash_equals($historicalToken, $token)
        ));
        array_unshift($history, $token);

        $_SESSION[self::TOKEN_HISTORY_SESSION_KEY] = array_slice($history, 0, self::TOKEN_HISTORY_LIMIT);
    }

    /**
     * HTML form input olarak token'ı render et
     */
    public static function field(string $name = '_token'): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
        );
    }
}

// Global helper functions
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return CsrfProtection::token();
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token): bool
    {
        return CsrfProtection::verify($token);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(string $name = '_token'): string
    {
        return CsrfProtection::field($name);
    }
}
