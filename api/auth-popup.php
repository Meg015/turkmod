<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/ApiResponse.php';
require_once __DIR__ . '/../admin/helpers.php';

if (function_exists('sendNoStoreHeaders')) {
    sendNoStoreHeaders();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$pdo = requireDatabaseConnection($pdo ?? null);
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
if (function_exists('usersEnsureUsernameSchema')) {
    usersEnsureUsernameSchema($pdo);
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if (!is_array($payload) || $payload === []) {
    $payload = $_POST;
}

$action = strtolower(trim((string) ($payload['action'] ?? '')));
if ($action === '') {
    sendValidationError('Islem tipi zorunludur.', ['action' => 'login veya register bekleniyor.']);
}

$token = (string) ($payload['_token'] ?? ($payload['csrf_token'] ?? ''));
if (!verify_csrf_token($token)) {
    sendCsrfError();
}

$baseUri = rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/');
$redirectHint = loginSafeRedirect(
    (string) ($payload['redirect'] ?? ''),
    $baseUri . '/index.php'
);

$loginIdentifierMode = strtolower(trim((string) ($settings['login_identifier_mode'] ?? 'email')));
if (!in_array($loginIdentifierMode, ['email', 'username', 'both'], true)) {
    $loginIdentifierMode = 'email';
}

$respondAuthSuccess = static function (array $user, string $message) use ($redirectHint): void {
    sendSuccess($message, [
        'auth' => [
            'logged_in' => true,
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'name' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role_id' => (int) ($user['role_id'] ?? 0),
                'role_slug' => (string) ($user['role_slug'] ?? ''),
            ],
            'redirect' => $redirectHint,
        ],
    ]);
};

try {
    if ($action === 'login') {
        if (!empty($_SESSION['_auth_user_id'])) {
            $existingUser = [
                'id' => (int) ($_SESSION['_auth_user_id'] ?? 0),
                'username' => (string) ($_SESSION['_auth_user_name'] ?? ''),
                'name' => (string) ($_SESSION['_auth_user_name'] ?? ''),
                'email' => (string) ($_SESSION['_auth_user_email'] ?? ''),
                'role_id' => (int) ($_SESSION['_auth_role_id'] ?? 0),
                'role_slug' => (string) ($_SESSION['_auth_role_slug'] ?? ''),
            ];
            $respondAuthSuccess($existingUser, 'Oturum zaten acik.');
        }

        $identifier = trim((string) ($payload['identifier'] ?? ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        if ($identifier === '' || $password === '') {
            sendValidationError('Giris bilgileri eksik.', [
                'identifier' => 'Kullanici adi veya e-posta zorunludur.',
                'password' => 'Sifre zorunludur.',
            ]);
        }

        $rateLimitKey = 'login_' . getRealIp();
        $loginRateLimit = (int) ($settings['login_rate_limit'] ?? 5);
        if ($loginRateLimit <= 0) {
            $loginRateLimit = 5;
        }
        $loginRateWindow = max(1, (int) ($settings['login_rate_window'] ?? 15));
        if (!checkRateLimit($rateLimitKey, $loginRateLimit, $loginRateWindow)) {
            $remaining = getRateLimitRemainingSeconds($rateLimitKey, $loginRateWindow);
            $minutes = (int) ceil($remaining / 60);
            sendError('rate_limit', "Cok fazla giris denemesi. Lutfen {$minutes} dakika sonra tekrar deneyin.", 429, ['retry_after' => $remaining]);
        }

        $authResult = ['success' => false];
        try {
            $authResult = authenticateUser($pdo, $identifier, $password, $loginIdentifierMode);
        } catch (Throwable $e) {
            if (str_starts_with($e->getMessage(), 'ban:')) {
                if (function_exists('logFailedLogin')) {
                    logFailedLogin($pdo, $identifier, 'account_banned');
                }
                sendError('account_banned', substr($e->getMessage(), 4), 403);
            }
            throw $e;
        }

        if (empty($authResult['success']) || empty($authResult['user']) || !is_array($authResult['user'])) {
            incrementRateLimit($rateLimitKey, $loginRateWindow);
            if (function_exists('logFailedLogin')) {
                logFailedLogin($pdo, $identifier, 'invalid_credentials');
            }
            sendError('invalid_credentials', 'Giris bilgileri hatali.', 401);
        }

        resetRateLimit($rateLimitKey);
        $user = $authResult['user'];
        $rememberRequested = !empty($payload['remember_session']);

        if ($rememberRequested && function_exists('authIssueRememberToken')) {
            $_SESSION['_auth_remember_session'] = 1;
            authIssueRememberToken($pdo, (int) ($user['id'] ?? 0), $settings);
        } else {
            unset($_SESSION['_auth_remember_session']);
            if (function_exists('authClearRememberToken')) {
                authClearRememberToken($pdo, (int) ($user['id'] ?? 0));
            }
        }

        if (function_exists('logSuccessfulLogin')) {
            logSuccessfulLogin($pdo, (int) ($user['id'] ?? 0), (string) ($user['email'] ?? $identifier));
        }
        if (function_exists('eventsRecordActivity')) {
            eventsRecordActivity($pdo, (int) ($user['id'] ?? 0), 'daily_login', 'user', (int) ($user['id'] ?? 0), [
                'dedupe_key' => 'daily_login:user:' . (int) ($user['id'] ?? 0) . ':' . date('Y-m-d'),
            ]);
        }

        $respondAuthSuccess($user, 'Oturum basariyla acildi.');
    }

    if ($action === 'register') {
        if (!empty($_SESSION['_auth_user_id'])) {
            $existingUser = [
                'id' => (int) ($_SESSION['_auth_user_id'] ?? 0),
                'username' => (string) ($_SESSION['_auth_user_name'] ?? ''),
                'name' => (string) ($_SESSION['_auth_user_name'] ?? ''),
                'email' => (string) ($_SESSION['_auth_user_email'] ?? ''),
                'role_id' => (int) ($_SESSION['_auth_role_id'] ?? 0),
                'role_slug' => (string) ($_SESSION['_auth_role_slug'] ?? ''),
            ];
            $respondAuthSuccess($existingUser, 'Oturum zaten acik.');
        }

        $allowRegistration = (string) ($settings['allow_registration'] ?? '1') === '1';
        if (!$allowRegistration) {
            sendError('registration_closed', 'Yeni kayitlar su anda kapali.', 403);
        }

        $regRateKey = 'register_' . getRealIp();
        $registerRateLimit = max(1, (int) ($settings['register_rate_limit'] ?? 3));
        $registerRateWindow = max(1, (int) ($settings['register_rate_window'] ?? 30));
        if (!checkRateLimit($regRateKey, $registerRateLimit, $registerRateWindow)) {
            $remaining = getRateLimitRemainingSeconds($regRateKey, $registerRateWindow);
            $minutes = (int) ceil($remaining / 60);
            sendError('rate_limit', "Cok fazla kayit denemesi. Lutfen {$minutes} dakika sonra tekrar deneyin.", 429, ['retry_after' => $remaining]);
        }

        $usernameRaw = trim((string) ($payload['username'] ?? ''));
        $username = function_exists('usersValidateUsernameInput')
            ? usersValidateUsernameInput($usernameRaw)
            : '';
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirm'] ?? '');

        if ($username === '') {
            sendValidationError('Kayit bilgileri gecersiz.', ['username' => 'Kullanici adi 3-30 karakter olmali ve sadece harf, rakam, _ veya - icermelidir.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendValidationError('Kayit bilgileri gecersiz.', ['email' => 'Gecerli bir e-posta adresi girin.']);
        }
        if (function_exists('validatePasswordPolicy')) {
            $policyError = validatePasswordPolicy($password, $settings, 'Sifre');
            if ($policyError !== '') {
                sendValidationError('Kayit bilgileri gecersiz.', ['password' => $policyError]);
            }
        } elseif (mb_strlen($password) < 8) {
            sendValidationError('Kayit bilgileri gecersiz.', ['password' => 'Sifre en az 8 karakter olmalidir.']);
        }
        if ($password !== $passwordConfirm) {
            sendValidationError('Kayit bilgileri gecersiz.', ['password_confirm' => 'Sifreler eslesmiyor.']);
        }

        $emailStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $emailStmt->execute(['email' => $email]);
        if ((int) $emailStmt->fetchColumn() > 0) {
            sendValidationError('Kayit bilgileri gecersiz.', ['email' => 'Bu e-posta adresi zaten kayitli.']);
        }

        $usernameCheckSql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $usernameStmt = $pdo->prepare($usernameCheckSql);
        $usernameStmt->execute(['username' => $username]);
        if ((int) $usernameStmt->fetchColumn() > 0) {
            sendValidationError('Kayit bilgileri gecersiz.', ['username' => 'Bu kullanici adi zaten kayitli.']);
        }

        incrementRateLimit($regRateKey, $registerRateWindow);

        $memberRoleId = 3;
        try {
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
            $roleStmt->execute(['slug' => 'member']);
            $resolvedRoleId = (int) $roleStmt->fetchColumn();
            if ($resolvedRoleId > 0) {
                $memberRoleId = $resolvedRoleId;
            }
        } catch (Throwable $roleError) {
            if (function_exists('appLogException')) {
                appLogException($roleError, ['source' => 'auth-popup register role lookup']);
            }
        }

        $userColumns = [];
        try {
            foreach ($pdo->query('SHOW COLUMNS FROM users') as $columnMeta) {
                $columnName = strtolower((string) ($columnMeta['Field'] ?? ''));
                if ($columnName !== '') {
                    $userColumns[$columnName] = true;
                }
            }
        } catch (Throwable $schemaError) {
            if (function_exists('appLogException')) {
                appLogException($schemaError, ['source' => 'auth-popup register users schema']);
            }
        }

        if (empty($userColumns['username']) || empty($userColumns['email']) || empty($userColumns['password'])) {
            sendError('registration_unavailable', 'Kayit semasi eksik oldugu icin kayit islemi tamamlanamadi.', 500);
        }

        $insertColumns = [];
        $insertValues = [];
        $insertParams = [];
        $pushParam = static function (string $column, string $placeholder, mixed $value) use (&$insertColumns, &$insertValues, &$insertParams): void {
            $insertColumns[] = $column;
            $insertValues[] = $placeholder;
            $insertParams[$column] = $value;
        };

        if (!empty($userColumns['role_id'])) {
            $pushParam('role_id', ':role_id', $memberRoleId);
        }
        $pushParam('username', ':username', $username);
        $pushParam('email', ':email', $email);
        $pushParam('password', ':password', password_hash($password, PASSWORD_DEFAULT));
        if (!empty($userColumns['status'])) {
            $pushParam('status', ':status', 'active');
        }
        if (!empty($userColumns['created_at'])) {
            $insertColumns[] = 'created_at';
            $insertValues[] = 'NOW()';
        }
        if (!empty($userColumns['updated_at'])) {
            $insertColumns[] = 'updated_at';
            $insertValues[] = 'NOW()';
        }

        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $insertColumns);
        $insertSql = 'INSERT INTO users (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($insertParams);

        $newUserId = (int) $pdo->lastInsertId();

        if (function_exists('usersSyncUserGroups') && function_exists('usersDefaultGroupId')) {
            $defaultGroupId = usersDefaultGroupId($pdo);
            if ($defaultGroupId > 0) {
                usersSyncUserGroups($pdo, $newUserId, [$defaultGroupId], 0, 'automatic_registration_group');
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, 'user_registered', 'user', $newUserId, ['email' => $email]);
        }
        if (function_exists('eventsRecordActivity')) {
            eventsRecordActivity($pdo, $newUserId, 'user_registered', 'user', $newUserId, [
                'is_approved' => true,
                'dedupe_key' => 'user_registered:user:' . $newUserId,
            ]);
        }

        $freshUserSql = 'SELECT id, username, email, status, password_changed_at FROM users WHERE id = :id LIMIT 1';
        $freshUserStmt = $pdo->prepare($freshUserSql);
        $freshUserStmt->execute(['id' => $newUserId]);
        $freshUser = $freshUserStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($freshUser) || !$freshUser) {
            sendError('registration_failed', 'Kayit tamamlandi ancak oturum acilamadi. Lutfen giris yapin.', 500);
        }

        if (function_exists('authSessionUserContext')) {
            authSessionUserContext()->regenerateSession();
        }
        if (function_exists('authPopulateSessionUser')) {
            $freshUser = authPopulateSessionUser($pdo, $freshUser, false);
        }

        $rememberRequested = !empty($payload['remember_session']);
        if ($rememberRequested && function_exists('authIssueRememberToken')) {
            $_SESSION['_auth_remember_session'] = 1;
            authIssueRememberToken($pdo, $newUserId, $settings);
        } else {
            unset($_SESSION['_auth_remember_session']);
        }

        if (function_exists('logSuccessfulLogin')) {
            logSuccessfulLogin($pdo, $newUserId, $email);
        }
        if (function_exists('eventsRecordActivity')) {
            eventsRecordActivity($pdo, $newUserId, 'daily_login', 'user', $newUserId, [
                'dedupe_key' => 'daily_login:user:' . $newUserId . ':' . date('Y-m-d'),
            ]);
        }

        $respondAuthSuccess($freshUser, 'Hesap olusturuldu ve oturum acildi.');
    }

    sendValidationError('Gecersiz islem.', ['action' => 'Sadece login veya register desteklenir.']);
} catch (Throwable $e) {
    sendServerError('Popup kimlik dogrulama islemi basarisiz.', $e);
}
