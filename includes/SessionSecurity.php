<?php

declare(strict_types=1);

/**
 * Session Security Manager
 * Session hijacking ve CSRF saldırılarına karşı koruma
 */
class SessionSecurity
{
    private static $instance = null;
    private const SESSION_TIMEOUT = 1800; // 30 dakika
    private const CSRF_TOKEN_LENGTH = 32;

    private function __construct()
    {
        $this->configureSession();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Session konfigürasyonunu ayarla
     */
    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', (string)self::SESSION_TIMEOUT);
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');

            session_start();
        }
    }

    /**
     * Session'ı başlat ve CSRF token oluştur
     */
    public function initializeSession(): void
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
        }

        if (!isset($_SESSION['_session_start'])) {
            $_SESSION['_session_start'] = time();
        }

        $_SESSION['_last_activity'] = time();
    }

    /**
     * Session timeout kontrolü
     */
    public function checkTimeout(): bool
    {
        if (!isset($_SESSION['_last_activity'])) {
            return true;
        }

        if (time() - $_SESSION['_last_activity'] > self::SESSION_TIMEOUT) {
            $this->destroySession();
            return false;
        }

        $_SESSION['_last_activity'] = time();
        return true;
    }

    /**
     * CSRF token al
     */
    public function getCSRFToken(): string
    {
        return $_SESSION['_csrf_token'] ?? '';
    }

    /**
     * CSRF token doğrula
     */
    public function validateCSRFToken(?string $token): bool
    {
        if (!isset($_SESSION['_csrf_token'])) {
            return false;
        }

        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Session'ı yenile (login sonrası)
     */
    public function regenerateSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
        $_SESSION['_session_start'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Session'ı yok et
     */
    public function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * User agent değişikliğini kontrol et (IP kontrolü kaldırıldı,
     * çünkü mobil/VPN kullanıcılarında yanlış pozitif üretiyordu).
     */
    public function validateSessionIntegrity(): bool
    {
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!isset($_SESSION['_user_agent'])) {
            $_SESSION['_user_agent'] = $currentUserAgent;
            return true;
        }

        if ($_SESSION['_user_agent'] !== $currentUserAgent) {
            Logger::getInstance()->security('User agent mismatch detected', [
                'expected' => $_SESSION['_user_agent'],
                'actual' => $currentUserAgent,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            return false;
        }

        return true;
    }
}
