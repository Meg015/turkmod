<?php

declare(strict_types=1);

namespace App\Engine\Auth;

use Closure;
use PDO;
use Throwable;

final class SessionUserContext
{
    private Closure $primaryGroupResolver;

    private Closure $timeResolver;

    private Closure $exceptionLogger;

    private Closure $sessionRegenerator;

    public function __construct(
        ?callable $primaryGroupResolver = null,
        ?callable $timeResolver = null,
        ?callable $exceptionLogger = null,
        ?callable $sessionRegenerator = null,
    ) {
        $this->primaryGroupResolver = $primaryGroupResolver !== null
            ? Closure::fromCallable($primaryGroupResolver)
            : self::defaultPrimaryGroupResolver();
        $this->timeResolver = $timeResolver !== null
            ? Closure::fromCallable($timeResolver)
            : static fn (): int => time();
        $this->exceptionLogger = $exceptionLogger !== null
            ? Closure::fromCallable($exceptionLogger)
            : self::defaultExceptionLogger();
        $this->sessionRegenerator = $sessionRegenerator !== null
            ? Closure::fromCallable($sessionRegenerator)
            : self::defaultSessionRegenerator();
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function populate(PDO $pdo, array $user, bool $remembered = false): array
    {
        $userId = (int) ($user['id'] ?? 0);
        [$roleId, $roleSlug, $roleName, $group] = $this->resolvePrimaryGroup($pdo, $userId);

        if ($group !== null) {
            $user['group_id'] = $roleId;
            $user['group_slug'] = $roleSlug;
            $user['group_name'] = $roleName;
        }

        $user['role_id'] = $roleId;
        $user['role_slug'] = $roleSlug;
        $user['role_name'] = $roleName !== '' ? $roleName : ($roleSlug !== '' ? $roleSlug : 'member');

        $sessionUsername = trim((string) ($user['username'] ?? ''));
        if ($sessionUsername === '') {
            $sessionUsername = 'user' . $userId;
        }
        $user['username'] = $sessionUsername;

        $now = (int) ($this->timeResolver)();
        $_SESSION['_auth_user_id'] = $userId;
        $_SESSION['_auth_user_name'] = $sessionUsername;
        $_SESSION['_auth_user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['_auth_login_time'] = $now;
        $_SESSION['_auth_last_activity'] = $now;
        $_SESSION['_auth_role_id'] = $roleId;
        $_SESSION['_auth_role_slug'] = $roleSlug;

        if ($remembered) {
            $_SESSION['_auth_remember_session'] = 1;
        } else {
            unset($_SESSION['_auth_remember_session']);
        }

        return $user;
    }

    public function regenerateSession(): void
    {
        ($this->sessionRegenerator)();
    }

    public function refresh(?PDO $pdo): bool
    {
        $userId = (int) ($_SESSION['_auth_user_id'] ?? 0);
        if (!$pdo instanceof PDO || $userId <= 0) {
            return false;
        }

        if (function_exists('usersEnsureUsernameSchema')) {
            usersEnsureUsernameSchema($pdo);
        }
        $stmt = $pdo->prepare("SELECT id, username, email, status, is_banned, password_changed_at
                               FROM users
                               WHERE id = ? AND deleted_at IS NULL
                               LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }

        $isBanned = (int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned';
        if (!$isBanned && (string) ($user['status'] ?? '') !== 'active') {
            return false;
        }

        $passwordChangedAt = !empty($user['password_changed_at'])
            ? strtotime((string) $user['password_changed_at'])
            : false;
        $loginTime = (int) ($_SESSION['_auth_login_time'] ?? 0);
        if ($passwordChangedAt !== false && $loginTime > 0 && $loginTime < $passwordChangedAt) {
            return false;
        }

        $sessionUsername = trim((string) ($user['username'] ?? ''));
        if ($sessionUsername === '') {
            $sessionUsername = 'user' . $userId;
        }
        $_SESSION['_auth_user_name'] = $sessionUsername;
        $_SESSION['_auth_user_email'] = (string) $user['email'];

        $previousRoleId = (int) ($_SESSION['_auth_role_id'] ?? 0);
        $previousRoleSlug = (string) ($_SESSION['_auth_role_slug'] ?? '');
        [$roleId, $roleSlug] = $this->resolvePrimaryGroup($pdo, $userId);
        if ($roleId !== $previousRoleId || $roleSlug !== $previousRoleSlug) {
            $this->regenerateSession();
        }
        $_SESSION['_auth_role_id'] = $roleId;
        $_SESSION['_auth_role_slug'] = $roleSlug;

        return true;
    }

    /**
     * @return array{0:int,1:string,2:string,3:?array<string,mixed>}
     */
    private function resolvePrimaryGroup(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return [0, '', '', null];
        }

        try {
            $group = ($this->primaryGroupResolver)($pdo, $userId);
            if (!is_array($group) || $group === []) {
                return [0, '', '', null];
            }

            return [
                (int) ($group['id'] ?? 0),
                (string) ($group['slug'] ?? ''),
                (string) ($group['name'] ?? ''),
                $group,
            ];
        } catch (Throwable $e) {
            ($this->exceptionLogger)($e, ['source' => 'SessionUserContext::resolvePrimaryGroup', 'user_id' => $userId]);
            return [0, '', '', null];
        }
    }

    private static function defaultPrimaryGroupResolver(): Closure
    {
        return static function (PDO $pdo, int $userId): ?array {
            if (!function_exists('usersPrimaryGroupForUser')) {
                return null;
            }

            $group = usersPrimaryGroupForUser($pdo, $userId);
            return is_array($group) ? $group : null;
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

    private static function defaultSessionRegenerator(): Closure
    {
        return static function (): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        };
    }
}
