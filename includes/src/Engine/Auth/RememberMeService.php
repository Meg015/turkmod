<?php

declare(strict_types=1);

namespace App\Engine\Auth;

use Closure;
use PDO;
use Throwable;

final class RememberMeService
{
    // 10 years: practically permanent for "remember me" while remaining timestamp-safe.
    private const PERSISTENT_REMEMBER_LIFETIME_SECONDS = 315360000;

    private Closure $settingsResolver;

    private Closure $columnExists;

    private Closure $cookieParamsResolver;

    private Closure $cookieSetter;

    private Closure $tokenGenerator;

    private Closure $sessionRegenerator;

    private Closure $activityLogger;

    private Closure $exceptionLogger;

    private bool $rememberTokenColumnChecked = false;

    public function __construct(
        private SessionUserContext $sessionUserContext,
        ?callable $settingsResolver = null,
        ?callable $columnExists = null,
        ?callable $cookieParamsResolver = null,
        ?callable $cookieSetter = null,
        ?callable $tokenGenerator = null,
        ?callable $sessionRegenerator = null,
        ?callable $activityLogger = null,
        ?callable $exceptionLogger = null,
    ) {
        $this->settingsResolver = $settingsResolver !== null
            ? Closure::fromCallable($settingsResolver)
            : self::defaultSettingsResolver();
        $this->columnExists = $columnExists !== null
            ? Closure::fromCallable($columnExists)
            : self::defaultColumnExists();
        $this->cookieParamsResolver = $cookieParamsResolver !== null
            ? Closure::fromCallable($cookieParamsResolver)
            : static fn (): array => session_get_cookie_params();
        $this->cookieSetter = $cookieSetter !== null
            ? Closure::fromCallable($cookieSetter)
            : static fn (string $name, string $value, array $options): bool => setcookie($name, $value, $options);
        $this->tokenGenerator = $tokenGenerator !== null
            ? Closure::fromCallable($tokenGenerator)
            : static fn (): string => bin2hex(random_bytes(32));
        $this->sessionRegenerator = $sessionRegenerator !== null
            ? Closure::fromCallable($sessionRegenerator)
            : static function (): void {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
            };
        $this->activityLogger = $activityLogger !== null
            ? Closure::fromCallable($activityLogger)
            : self::defaultActivityLogger();
        $this->exceptionLogger = $exceptionLogger !== null
            ? Closure::fromCallable($exceptionLogger)
            : self::defaultExceptionLogger();
    }

    public function cookieName(): string
    {
        return 'tm_remember';
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public function lifetimeSeconds(?array $settings = null): int
    {
        if ($settings === null) {
            $pdo = $GLOBALS['pdo'] ?? null;
            $settings = ($this->settingsResolver)($pdo instanceof PDO ? $pdo : null);
        }

        $minutes = (int) ($settings['remember_session_timeout_minutes'] ?? 43200);
        $configuredLifetime = max(1, $minutes) * 60;

        return min(self::PERSISTENT_REMEMBER_LIFETIME_SECONDS, $configuredLifetime);
    }

    /**
     * @return array<string,mixed>
     */
    public function cookieOptions(int $expires): array
    {
        $params = ($this->cookieParamsResolver)();
        $options = [
            'expires' => $expires,
            'path' => (string) ($params['path'] ?? '/'),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
        ];

        $sameSite = trim((string) ($params['samesite'] ?? ''));
        $options['samesite'] = $sameSite !== '' ? $sameSite : 'Lax';

        $domain = (string) ($params['domain'] ?? '');
        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        return $options;
    }

    public function ensureRememberTokenColumn(PDO $pdo): void
    {
        if ($this->rememberTokenColumnChecked) {
            return;
        }
        $this->rememberTokenColumnChecked = true;

        try {
            $exists = ($this->columnExists)($pdo, 'users', 'remember_token');
            if ($exists === false) {
                throw new \RuntimeException('Missing users.remember_token; run Admin Panel > Database Synchronization.');
            }
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e, ['source' => 'RememberMeService::ensureRememberTokenColumn']);
        }
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public function issue(PDO $pdo, int $userId, ?array $settings = null): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->ensureRememberTokenColumn($pdo);
            $token = (string) ($this->tokenGenerator)();
            $tokenHash = hash('sha256', $token);
            $stmt = $pdo->prepare("UPDATE users SET remember_token = :token, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute(['token' => $tokenHash, 'id' => $userId]);

            $cookieValue = $userId . ':' . $token;
            ($this->cookieSetter)(
                $this->cookieName(),
                $cookieValue,
                $this->cookieOptions(time() + $this->lifetimeSeconds($settings))
            );
            $_COOKIE[$this->cookieName()] = $cookieValue;
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e, ['source' => 'RememberMeService::issue', 'user_id' => $userId]);
        }
    }

    public function clear(?PDO $pdo = null, ?int $userId = null): void
    {
        $cookieName = $this->cookieName();
        if ($userId === null || $userId <= 0) {
            $userId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        }
        if (($userId === null || $userId <= 0) && isset($_COOKIE[$cookieName])) {
            $parts = explode(':', (string) $_COOKIE[$cookieName], 2);
            $userId = (int) ($parts[0] ?? 0);
        }

        if ($pdo instanceof PDO && $userId > 0) {
            try {
                $this->ensureRememberTokenColumn($pdo);
                $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$userId]);
            } catch (Throwable $e) {
                ($this->exceptionLogger)($e, ['source' => 'RememberMeService::clear', 'user_id' => $userId]);
            }
        }

        ($this->cookieSetter)($cookieName, '', $this->cookieOptions(time() - 42000));
        unset($_COOKIE[$cookieName]);
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public function attempt(?PDO $pdo, ?array $settings = null): bool
    {
        if (!$pdo instanceof PDO || !empty($_SESSION['_auth_user_id'])) {
            return false;
        }

        $cookieName = $this->cookieName();
        $cookieValue = (string) ($_COOKIE[$cookieName] ?? '');
        if ($cookieValue === '') {
            return false;
        }

        if (!preg_match('/^([1-9][0-9]*):([a-f0-9]{64})$/i', $cookieValue, $matches)) {
            $this->clear(null, null);
            return false;
        }

        $userId = (int) $matches[1];
        $tokenHash = hash('sha256', $matches[2]);

        try {
            $this->ensureRememberTokenColumn($pdo);
            if (function_exists('usersEnsureUsernameSchema')) {
                usersEnsureUsernameSchema($pdo);
            }
            $stmt = $pdo->prepare("SELECT id, username, email, status, remember_token
                                   FROM users
                                   WHERE id = ? AND deleted_at IS NULL
                                   LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $storedHash = is_array($user) ? (string) ($user['remember_token'] ?? '') : '';

            if (
                !$user
                || (string) ($user['status'] ?? '') !== 'active'
                || $storedHash === ''
                || !hash_equals($storedHash, $tokenHash)
            ) {
                $this->clear($pdo, $userId);
                return false;
            }

            ($this->sessionRegenerator)();
            $this->sessionUserContext->populate($pdo, $user, true);
            $this->issue($pdo, $userId, $settings);
            ($this->activityLogger)($pdo, 'user_remember_login', 'user', $userId);

            return true;
        } catch (Throwable $e) {
            $this->clear($pdo, $userId);
            ($this->exceptionLogger)($e, ['source' => 'RememberMeService::attempt', 'user_id' => $userId]);
            return false;
        }
    }

    private static function defaultSettingsResolver(): Closure
    {
        return static function (?PDO $pdo): array {
            if (!$pdo instanceof PDO || !function_exists('getAdminSettings')) {
                return [];
            }

            $settings = getAdminSettings($pdo);
            return is_array($settings) ? $settings : [];
        };
    }

    private static function defaultColumnExists(): Closure
    {
        return static function (PDO $pdo, string $table, string $column): bool {
            if (!function_exists('adminColumnExists')) {
                return true;
            }

            return (bool) adminColumnExists($pdo, $table, $column);
        };
    }

    private static function defaultActivityLogger(): Closure
    {
        return static function (PDO $pdo, string $action, string $entityType, int $entityId): void {
            if (function_exists('logActivity')) {
                logActivity($pdo, $action, $entityType, $entityId);
            }
        };
    }

    private static function defaultExceptionLogger(): Closure
    {
        return static function (Throwable $e, array $context): void {
            if (function_exists('appLogException')) {
                appLogException($e, $context);
                return;
            }

            error_log($e->getMessage());
        };
    }
}
