<?php

declare(strict_types=1);

require_once $projectRoot . '/includes/init.php';
if (is_file($projectRoot . '/includes/src/Modules/Events/init.php')) {
    require_once $projectRoot . '/includes/src/Modules/Events/init.php';
}

function loginRedirectForAuthenticatedUser(bool $isAdminUser, string $requestedRedirect, string $baseUri): string
{
    $adminHome = rtrim($baseUri, '/') . '/admin/index.php';
    if (!$isAdminUser) {
        return $requestedRedirect;
    }

    $requestedPath = (string) parse_url($requestedRedirect, PHP_URL_PATH);
    $basePath = rtrim($baseUri, '/');
    $defaultPublicTargets = array_values(array_unique(array_filter([
        $basePath . '/index.php',
        $basePath . '/',
        '/index.php',
        '/',
    ])));

    if ($requestedPath === '' || in_array($requestedPath, $defaultPublicTargets, true)) {
        return $adminHome;
    }

    return $requestedRedirect;
}

$requestedRedirect = loginSafeRedirect((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''), $baseUri . '/index.php');
$appDebug = isset($appDebug) ? (bool) $appDebug : ((defined('APP_DEBUG') && APP_DEBUG) ? true : false);
$loginUrl = routePublicStaticUrl('login');
$loginPath = '/' . ltrim(routePublicStaticPath('login'), '/');
$registerUrl = routePublicStaticUrl('register');
$loginUrlWithRedirect = authUrlWithRedirect($loginUrl, $requestedRedirect, $baseUri . '/index.php');
$registerUrlWithRedirect = authUrlWithRedirect($registerUrl, $requestedRedirect, $baseUri . '/index.php');
$forgotPasswordUrl = routePublicStaticUrl('forgot_password');
$loginAuditPath = $loginPath;

// Zaten giriş yapmışsa yönlendir
if ($isLoggedIn) {
    $currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
    $isAdminUser = $currentUserId > 0
        && function_exists('userIsAdmin')
        && userIsAdmin($pdo, $currentUserId);
    $redirectUrl = loginRedirectForAuthenticatedUser($isAdminUser, $requestedRedirect, $baseUri);
    header('Location: ' . $redirectUrl);
    exit;
}

$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$loginIdentifierMode = strtolower(trim((string) ($settings['login_identifier_mode'] ?? 'email')));
if (!in_array($loginIdentifierMode, ['email', 'username', 'both'], true)) {
    $loginIdentifierMode = 'email';
}
$loginIdentifierLabel = match ($loginIdentifierMode) {
    'username' => 'Kullanıcı adı',
    'both' => 'E-posta veya kullanıcı adı',
    default => 'E-posta',
};
$loginIdentifierPlaceholder = match ($loginIdentifierMode) {
    'username' => 'kullanici_adi',
    'both' => 'ornek@email.com veya kullanici_adi',
    default => 'ornek@email.com',
};
$loginIdentifierType = $loginIdentifierMode === 'email' ? 'email' : 'text';
$loginIdentifierAutocomplete = $loginIdentifierMode === 'email' ? 'email' : 'username';
$loginIdentifierIcon = $loginIdentifierMode === 'email' ? 'bi-envelope' : 'bi-person';
$rememberIdentifierLabel = $loginIdentifierMode === 'email'
    ? 'E-postamı bu cihazda hatırla'
    : 'Giriş bilgimi bu cihazda hatırla';
$invalidCredentialMessage = $loginIdentifierMode === 'email'
    ? 'Geçersiz e-posta veya şifre.'
    : 'Geçersiz giriş bilgisi veya şifre.';

$showRememberSession = (string) ($settings['login_show_remember_session'] ?? '1') === '1';
$rememberSessionDefault = (string) ($settings['login_remember_session_default'] ?? '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        logCsrfFailure($pdo, $loginAuditPath);
        csrf_token();
        header('Location: ' . $loginUrlWithRedirect);
        exit;
    }

    $rateLimitKey = 'login_' . getRealIp();
    $loginRateLimit = (int)($settings['login_rate_limit'] ?? 5) ?: 5;
    $loginRateWindow = max(1, (int)($settings['login_rate_window'] ?? 15));
    if (!checkRateLimit($rateLimitKey, $loginRateLimit, $loginRateWindow)) {
        logRateLimitExceeded($pdo, $loginAuditPath, 'login_attempts');
        $remaining = getRateLimitRemainingSeconds($rateLimitKey, $loginRateWindow);
        $minutes = (int) ceil($remaining / 60);
        flash('error', "Çok fazla giriş denemesi. Lütfen {$minutes} dakika sonra tekrar deneyin.");
        header('Location: ' . $loginUrlWithRedirect);
        exit;
    }

    $loginIdentifier = trim((string) ($_POST['identifier'] ?? $_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    try {
        $authResult = authenticateUser($pdo, $loginIdentifier, $password, $loginIdentifierMode);
    } catch (Throwable $e) {
        if (str_starts_with($e->getMessage(), 'ban:')) {
            logFailedLogin($pdo, $loginIdentifier, 'account_banned');
            flash('error', substr($e->getMessage(), 4));
            header('Location: ' . $loginUrlWithRedirect);
            exit;
        }
        $authResult = ['success' => false];
    }

    if (($authResult['success'] ?? false) && !empty($authResult['user']) && is_array($authResult['user'])) {
        $user = $authResult['user'];
        resetRateLimit($rateLimitKey);
        $_SESSION['_auth_user_id'] = $user['id'];
        $_SESSION['_auth_user_name'] = (string) ($user['username'] ?? '');
        $_SESSION['_auth_role_id'] = (int) ($user['role_id'] ?? 0);
        $_SESSION['_auth_role_slug'] = (string) ($user['role_slug'] ?? '');
        $rememberSession = $showRememberSession
            ? !empty($_POST['remember_session'])
            : $rememberSessionDefault;

        if ($rememberSession && $pdo instanceof PDO) {
            $_SESSION['_auth_remember_session'] = 1;
            if (function_exists('authIssueRememberToken')) {
                authIssueRememberToken($pdo, (int)$user['id'], $settings);
            }
        } else {
            unset($_SESSION['_auth_remember_session']);
            if (function_exists('authClearRememberToken')) {
                authClearRememberToken($pdo instanceof PDO ? $pdo : null, (int)$user['id']);
            }
        }
        logActivity($pdo, 'user_login', 'user', $user['id']);
        logSuccessfulLogin($pdo, $user['id'], (string) ($user['email'] ?? $loginIdentifier));
        if (function_exists('eventsRecordActivity')) {
            eventsRecordActivity($pdo, (int)$user['id'], 'daily_login', 'user', (int)$user['id'], [
                'dedupe_key' => 'daily_login:user:' . (int)$user['id'] . ':' . date('Y-m-d'),
            ]);
        }
        $isBannedUser = (int) ($user['is_banned'] ?? 0) === 1 || (string) ($user['status'] ?? '') === 'banned';
        $isAdminUser = function_exists('userIsAdmin') && userIsAdmin($pdo, (int) $user['id']);
        $redirectUrl = $isBannedUser
            ? routePublicStaticUrl('ban_appeals')
            : loginRedirectForAuthenticatedUser($isAdminUser, $requestedRedirect, $baseUri);
        header('Location: ' . $redirectUrl);
        exit;
    }

    incrementRateLimit($rateLimitKey, $loginRateWindow);
    logFailedLogin($pdo, $loginIdentifier, 'invalid_credentials');
    flash('error', $invalidCredentialMessage);
    header('Location: ' . $loginUrlWithRedirect);
    exit;
}

$pageTitle = 'Giriş Yap';
$errorMsg = get_flash('error');
$successMsg = get_flash('success');
$showOnboarding = ($_GET['registered'] ?? '') === '1';
$auth_error = $errorMsg;
$auth_success = $successMsg;
$auth_show_onboarding = $showOnboarding;
$auth_redirect = $requestedRedirect;
$auth_login_url = $loginUrlWithRedirect;
$auth_register_url = $registerUrlWithRedirect;
$auth_csrf_token = csrf_token();
$auth_demo_visible = $appDebug && getRealIp() === '127.0.0.1';
$auth_login_identifier_mode = $loginIdentifierMode;
$auth_login_label = $loginIdentifierLabel;
$auth_login_placeholder = $loginIdentifierPlaceholder;
$auth_login_type = $loginIdentifierType;
$auth_login_autocomplete = $loginIdentifierAutocomplete;
$auth_login_icon = $loginIdentifierIcon;
$auth_login_remember_label = $rememberIdentifierLabel;
require_once $projectRoot . '/includes/public-header.php';
if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
    require_once $projectRoot . '/includes/public-footer.php';
    return;
}
?>

<?= renderPublicBreadcrumb([
    ['label' => 'Ana Sayfa', 'url' => $baseUri . '/index.php'],
    ['label' => 'Giriş Yap'],
], 'auth-breadcrumb') ?>

<div class="auth-wrapper auth-screen auth-screen-login ui-panel">
    <section class="auth-stage" aria-labelledby="loginTitle">
        <aside class="auth-visual" aria-label="Üyelik avantajları">
            <span class="auth-kicker">Topluluk hesabı</span>
            <h2>Modlarını, favorilerini ve profilini tek yerden yönet.</h2>
            <p>Giriş yaptıktan sonra içerik yükleyebilir, indirme geçmişini takip edebilir ve topluluk profilini güçlendirebilirsin.</p>
            <div class="auth-benefits">
                <span><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Hızlı içerik yükleme</span>
                <span><i class="bi bi-heart" aria-hidden="true"></i> Favori listesi</span>
                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Güvenli oturum</span>
            </div>
        </aside>

        <div class="auth-box ui-panel">
            <div class="auth-header ui-panel__head">
                <span class="auth-header-icon"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">Tekrar hoş geldin</span>
                <h1 id="loginTitle">Giriş Yap</h1>
                <p>Hesabına giriş yap ve içeriklerini yönetmeye devam et.</p>
            </div>

            <?php if ($errorMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert" aria-live="assertive"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>
            <?php if ($successMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="status"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>
            <?php if ($showOnboarding): ?>
                <div class="auth-onboarding" role="status" aria-live="polite">
                    <strong>Hesabın hazır. Girişten sonra hızlıca başlayabilirsin.</strong>
                    <span><i class="bi bi-person-lines-fill" aria-hidden="true"></i> Profilini tamamla</span>
                    <span><i class="bi bi-heart" aria-hidden="true"></i> Favorilerini sakla</span>
                    <span><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> İlk içeriğini yükle</span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>" novalidate data-remember-email-form data-auth-csrf-refresh>
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($requestedRedirect, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group auth-field">
                    <label for="identifier"><?= htmlspecialchars($loginIdentifierLabel) ?></label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi <?= htmlspecialchars($loginIdentifierIcon) ?>" aria-hidden="true"></i>
                        <input
                            id="identifier"
                            name="identifier"
                            type="<?= htmlspecialchars($loginIdentifierType) ?>"
                            placeholder="<?= htmlspecialchars($loginIdentifierPlaceholder) ?>"
                            required
                            aria-required="true"
                            autocomplete="<?= htmlspecialchars($loginIdentifierAutocomplete) ?>"
                            data-remember-email-input>
                    </span>
                </div>
                <div class="form-group auth-field">
                    <label for="password">Şifre</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" required aria-required="true" autocomplete="current-password">
                    </span>
                </div>
                <div class="auth-form-options">
                    <label class="auth-checkbox">
                        <input type="checkbox" name="remember_email" value="1" data-remember-email-check>
                        <span><?= htmlspecialchars($rememberIdentifierLabel) ?></span>
                    </label>
                    <?php if ($showRememberSession): ?>
                        <label class="auth-checkbox auth-checkbox-stacked">
                            <input type="checkbox" name="remember_session" value="1" <?= $rememberSessionDefault ? 'checked' : '' ?>>
                            <span>Oturumumu bu cihazda hatırla</span>
                            <small>Paylaşılan cihazlarda kullanmayın.</small>
                        </label>
                    <?php endif; ?>
                </div>
                <button class="btn-auth" type="submit">
                    <span>Giriş Yap</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="auth-footer-actions">
                <a class="btn-auth-link" href="<?= htmlspecialchars($forgotPasswordUrl, ENT_QUOTES, 'UTF-8') ?>">Şifremi unuttum</a>
                <p class="form-options">Hesabın yok mu? <a href="<?= htmlspecialchars($registerUrlWithRedirect, ENT_QUOTES, 'UTF-8') ?>">Kayıt Ol</a></p>
            </div>

            <?php if ($appDebug && getRealIp() === '127.0.0.1'): ?>
                <div class="settings-info"><strong>Demo bilgileri:</strong> admin@topic.test / password</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>



