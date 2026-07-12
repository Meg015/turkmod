<?php

declare(strict_types=1);

use App\Engine\Auth\RememberMeService;
use App\Engine\Auth\SessionUserContext;
use App\Core\Http\RedirectResponse;

/**
 * Auth modülü - Kullanıcı kimlik doğrulama fonksiyonları
 */

if (!function_exists('authSessionUserContext')) {
    function authSessionUserContext(): SessionUserContext
    {
        static $context = null;
        if (!$context instanceof SessionUserContext) {
            $context = new SessionUserContext();
        }

        return $context;
    }
}

if (!function_exists('authRememberMeService')) {
    function authRememberMeService(): RememberMeService
    {
        static $service = null;
        if (!$service instanceof RememberMeService) {
            $service = new RememberMeService(authSessionUserContext());
        }

        return $service;
    }
}

if (!function_exists('authRememberCookieName')) {
    function authRememberCookieName(): string
    {
        return authRememberMeService()->cookieName();
    }
}

if (!function_exists('authRememberLifetimeSeconds')) {
    function authRememberLifetimeSeconds(?array $settings = null): int
    {
        return authRememberMeService()->lifetimeSeconds($settings);
    }
}

if (!function_exists('authRememberCookieOptions')) {
    function authRememberCookieOptions(int $expires): array
    {
        return authRememberMeService()->cookieOptions($expires);
    }
}

if (!function_exists('authEnsureRememberTokenColumn')) {
    function authEnsureRememberTokenColumn(PDO $pdo): void
    {
        authRememberMeService()->ensureRememberTokenColumn($pdo);
    }
}

if (!function_exists('authPopulateSessionUser')) {
    function authPopulateSessionUser(PDO $pdo, array $user, bool $remembered = false): array
    {
        return authSessionUserContext()->populate($pdo, $user, $remembered);
    }
}

if (!function_exists('loginSafeRedirect')) {
    function loginSafeRedirect(string $candidate, string $fallback): string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $fallback;
        }

        if (str_starts_with($candidate, '//') || str_contains($candidate, '\\')) {
            return $fallback;
        }

        if ($candidate[0] !== '/' && !preg_match('~^(?:https?:)?//~i', $candidate)) {
            $candidate = '/' . ltrim($candidate, '/');
        }

        if (!RedirectResponse::onlyTrusted($candidate)) {
            return $fallback;
        }

        $path = parse_url($candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || str_contains($path, "\0")) {
            return $fallback;
        }

        $authPaths = [];
        $canonicalAuthPaths = [
            'login' => '/giris',
            'register' => '/kayit',
            'forgot_password' => '/sifremi-unuttum',
            'reset_password' => '/sifre-sifirla',
            'logout' => '/cikis',
        ];
        if (function_exists('routePublicStaticPath')) {
            foreach (array_keys($canonicalAuthPaths) as $authRouteKey) {
                $cleanPath = trim((string) routePublicStaticPath($authRouteKey), '/');
                if ($cleanPath !== '') {
                    $authPaths[] = '/' . $cleanPath;
                }
            }
        } else {
            $authPaths = array_values($canonicalAuthPaths);
        }

        foreach (array_values(array_unique($authPaths)) as $authPath) {
            if ($authPath !== '' && str_ends_with($path, (string) $authPath)) {
                return $fallback;
            }
        }

        return $candidate;
    }
}

if (!function_exists('authIssueRememberToken')) {
    function authIssueRememberToken(PDO $pdo, int $userId, ?array $settings = null): void
    {
        authRememberMeService()->issue($pdo, $userId, $settings);
    }
}

if (!function_exists('authClearRememberToken')) {
    function authClearRememberToken(?PDO $pdo = null, ?int $userId = null): void
    {
        authRememberMeService()->clear($pdo, $userId);
    }
}

if (!function_exists('authAttemptRememberLogin')) {
    function authAttemptRememberLogin(?PDO $pdo, ?array $settings = null): bool
    {
        return authRememberMeService()->attempt($pdo, $settings);
    }
}

if (!function_exists('authenticateUser')) {
    function authNormalizeLoginIdentifierMode(?array $settings = null): string
    {
        $mode = strtolower(trim((string) ($settings['login_identifier_mode'] ?? 'email')));
        return in_array($mode, ['email', 'username', 'both'], true) ? $mode : 'email';
    }

    /**
     * Kullanıcı giriş işlemi - Session fixation korumalı
     */
    function authenticateUser(?PDO $pdo, string $identifier, string $password, ?string $loginIdentifierMode = null): array
    {
        if (!$pdo) {
            return ['success' => false, 'error' => 'Veritabanı bağlantısı yok'];
        }

        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return ['success' => false, 'error' => 'Giriş bilgisi ve şifre zorunludur'];
        }

        try {
            $settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
            $mode = $loginIdentifierMode !== null && in_array($loginIdentifierMode, ['email', 'username', 'both'], true)
                ? $loginIdentifierMode
                : authNormalizeLoginIdentifierMode($settings);

            if (function_exists('usersEnsureUsernameSchema')) {
                usersEnsureUsernameSchema($pdo);
            }
            $sql = "SELECT id, username, email, email_verified_at, password, status, password_changed_at
                    FROM users
                    WHERE deleted_at IS NULL";
            $params = [];
            if ($mode === 'username') {
                $sql .= " AND username = ?";
                $params[] = $identifier;
            } elseif ($mode === 'both') {
                $sql .= " AND (email = ? OR username = ?)";
                $params[] = $identifier;
                $params[] = $identifier;
            } else {
                $sql .= " AND email = ?";
                $params[] = $identifier;
            }
            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'Kullanıcı bulunamadı'];
            }

            if ((string)($user['status'] ?? '') !== 'active') {
                return ['success' => false, 'error' => 'Hesabınız aktif değil'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'error' => 'Şifre hatalı'];
            }

            if (($settings['account_email_verification_enabled'] ?? '0') === '1'
                && ($settings['account_email_verification_required'] ?? '0') === '1'
                && empty($user['email_verified_at'])) {
                return ['success' => false, 'error' => 'Giriş için e-posta adresinizi doğrulamanız gerekiyor. Yeni bağlantı isteyebilirsiniz.'];
            }

            $expiryDays = (int)($settings['password_expiry_days'] ?? 0);
            if ($expiryDays > 0) {
                $changedAt = !empty($user['password_changed_at']) ? strtotime($user['password_changed_at']) : time();
                if ((time() - $changedAt) > ($expiryDays * 86400)) {
                    throw new Exception('ban:Şifrenizin geçerlilik süresi dolmuş. Lütfen şifrenizi sıfırlayın.');
                }
            }

            // Session fixation koruması: Başarılı girişte session ID'yi yenile
            authSessionUserContext()->regenerateSession();

            $user = authPopulateSessionUser($pdo, $user, false);

            // Activity log
            if (function_exists('logActivity')) {
                logActivity($pdo, 'user_login', 'user', (int) $user['id']);
            }

            return ['success' => true, 'user' => $user];

        } catch (Throwable $e) {
            if (str_starts_with($e->getMessage(), 'ban:')) {
                throw $e;
            }
            if (function_exists('appLogException')) {
                appLogException($e, ['source' => 'authenticateUser']);
            }
            return ['success' => false, 'error' => 'Giriş işlemi sırasında hata oluştu'];
        }
    }
}

if (!function_exists('refreshAuthenticatedSession')) {
    function refreshAuthenticatedSession(?PDO $pdo): bool
    {
        return authSessionUserContext()->refresh($pdo);
    }
}

if (!function_exists('requireAuth')) {
    /**
     * Kullanıcının giriş yapmış olmasını zorunlu kılar
     */
    function requireAuth(string $redirectUrl = ''): void
    {
        if (empty($_SESSION['_auth_user_id'])) {
            global $baseUri;
            $baseUri = $baseUri ?? '';
            $loginPath = function_exists('routePublicStaticPath')
                ? '/' . ltrim(routePublicStaticPath('login'), '/')
                : '/giris';
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $target = $redirectUrl !== '' ? $redirectUrl : $loginPath;
            if ($target === $loginPath && $requestUri !== '') {
                $target .= '?redirect=' . rawurlencode($requestUri);
            }
            header('Location: ' . $baseUri . $target);
            exit;
        }
    }
}

if (!function_exists('requireAdmin')) {
    /**
     * Kullanıcının admin olmasını zorunlu kılar
     */
    function requireAdmin(string $redirectUrl = '/index.php'): void
    {
        requireAuth();

        global $pdo, $baseUri;
        if (!refreshAuthenticatedSession($pdo ?? null)) {
            logoutUser($pdo ?? null, false);
            $loginUrl = routePublicStaticUrl('login');
            header('Location: ' . $loginUrl);
            exit;
        }
        
        $userId = (int)($_SESSION['_auth_user_id'] ?? 0);
        $hasAdminAccess = function_exists('userHasPermission')
            && userHasPermission($pdo ?? null, $userId, 'admin.access');

        if (!$hasAdminAccess) {
            global $baseUri;
            $baseUri = $baseUri ?? '';
            flash('error', 'Bu sayfaya erişim yetkiniz yok');
            header('Location: ' . $baseUri . $redirectUrl);
            exit;
        }
    }
}

if (!function_exists('logoutUser')) {
    /**
     * Kullanıcı çıkış işlemi
     */
    function logoutUser(?PDO $pdo = null, bool $clearRememberToken = true): void
    {
        $userId = $_SESSION['_auth_user_id'] ?? null;
        
        if ($pdo && $userId && function_exists('logActivity')) {
            logActivity($pdo, 'user_logout', 'user', (int) $userId);
        }

        if ($clearRememberToken) {
            authClearRememberToken($pdo, $userId ? (int)$userId : null);
        }

        // Session'ı tamamen temizle
        $_SESSION = [];
        
        // Session cookie'sini sil
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Session'ı yok et
        session_destroy();
    }
}

