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

        return hash_equals($_SESSION[self::TOKEN_SESSION_KEY], $token);
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

        $_SESSION[self::TOKEN_SESSION_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_SESSION_KEY];
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
