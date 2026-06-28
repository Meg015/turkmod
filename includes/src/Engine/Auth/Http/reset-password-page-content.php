<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

if ($isLoggedIn) {
    header('Location: ' . $baseUri . '/index.php');
    exit;
}

$loginUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('login')
    : ($baseUri . '/giris');
$forgotPasswordUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('forgot_password')
    : ($baseUri . '/sifremi-unuttum');
$resetPasswordBaseUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('reset_password')
    : ($baseUri . '/sifre-sifirla');

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');
$errorMsg = '';
$successMsg = '';
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$passwordResetRateLimit = max(1, (int) ($settings['password_reset_rate_limit'] ?? 3));
$passwordResetRateWindow = max(1, (int) ($settings['password_reset_rate_window'] ?? 30));
$passwordPolicy = passwordPolicyConfig();
$passwordMinLength = (int) $passwordPolicy['min_length'];
$passwordPolicyHint = passwordPolicyHint();

if ($token === '' || $email === '') {
    header('Location: ' . $forgotPasswordUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resetIp = function_exists('getRealIp') ? getRealIp() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $resetRateKey = 'reset_pw_' . $resetIp . '_' . hash('sha256', mb_strtolower($email, 'UTF-8'));

    if (!checkRateLimit($resetRateKey, $passwordResetRateLimit, $passwordResetRateWindow)) {
        $remaining = getRateLimitRemainingSeconds($resetRateKey, $passwordResetRateWindow);
        $minutes = (int) ceil($remaining / 60);
        $errorMsg = "Cok fazla istek. Lutfen {$minutes} dakika sonra tekrar deneyin.";
    } elseif (!verify_csrf_token($_POST['_token'] ?? '')) {
        $errorMsg = 'Guvenlik dogrulamasi basarisiz.';
    } else {
        incrementRateLimit($resetRateKey, $passwordResetRateWindow);
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (($policyError = validatePasswordPolicy($password, null, 'Şifre')) !== '') {
            $errorMsg = $policyError;
        } elseif ($password !== $passwordConfirm) {
            $errorMsg = 'Sifreler eslesmiyor.';
        } elseif ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND password_reset_token = :token AND password_reset_expires_at IS NOT NULL AND password_reset_expires_at >= NOW() LIMIT 1');
                $stmt->execute([
                    'email' => $email,
                    'token' => hash('sha256', $token),
                ]);
                $user = $stmt->fetch();

                if (!$user) {
                    $errorMsg = 'Sifre sifirlama baglantisi gecersiz veya suresi dolmus.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW(), password_reset_token = NULL, password_reset_expires_at = NULL, remember_token = NULL, updated_at = NOW() WHERE id = :id')
                        ->execute(['password' => $hashedPassword, 'id' => $user['id']]);

                    logActivity($pdo, 'password_reset', 'user', (int) $user['id']);
                    resetRateLimit($resetRateKey);
                    $successMsg = 'Sifreniz basariyla degistirildi. Simdi giris yapabilirsiniz.';
                }
            } catch (Throwable $e) {
                $errorMsg = safeErrorMessage($e, 'Islem sirasinda bir hata olustu.');
            }
        } else {
            $errorMsg = 'Veritabani baglantisi kurulamadi.';
        }
    }
}

$pageTitle = 'Sifre Sifirla';
$auth_error = $errorMsg;
$auth_success = $successMsg;
$auth_csrf_token = csrf_token();
$auth_reset_action = $resetPasswordBaseUrl . '?token=' . urlencode($token) . '&email=' . urlencode($email);
$auth_password_min_length = $passwordMinLength;
$auth_password_policy_hint = $passwordPolicyHint;
$auth_password_require_uppercase = !empty($passwordPolicy['require_uppercase']);
$auth_password_require_numbers = !empty($passwordPolicy['require_numbers']);
$auth_password_require_special = !empty($passwordPolicy['require_special']);
require_once $projectRoot . '/includes/public-header.php';
if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
    require_once $projectRoot . '/includes/public-footer.php';
    return;
}
?>

<div class="auth-wrapper ui-panel">
    <div class="auth-box ui-panel">
        <div class="auth-header ui-panel__head">
            <i class="bi bi-lock" aria-hidden="true"></i>
            <h1>Sifre Sifirla</h1>
            <p>Yeni sifreni belirle ve hesabina tekrar eris.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert" aria-live="assertive"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>
        <?php if ($successMsg): ?>
            <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="alert"><?= htmlspecialchars($successMsg) ?></div>
            <p class="form-options"><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giris Yap</a></p>
        <?php else: ?>
            <form class="auth-form" method="post" action="<?= htmlspecialchars($auth_reset_action, ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="password">Yeni Sifre</label>
                    <input id="password" name="password" type="password" required minlength="<?= $passwordMinLength ?>" aria-required="true" autocomplete="new-password" data-password-strength data-password-confirm="#password_confirm" data-password-require-uppercase="<?= $passwordPolicy['require_uppercase'] ? '1' : '0' ?>" data-password-require-numbers="<?= $passwordPolicy['require_numbers'] ? '1' : '0' ?>" data-password-require-special="<?= $passwordPolicy['require_special'] ? '1' : '0' ?>">
                    <small><?= htmlspecialchars($passwordPolicyHint) ?></small>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Yeni Sifre Tekrar</label>
                    <input id="password_confirm" name="password_confirm" type="password" required minlength="<?= $passwordMinLength ?>" aria-required="true" autocomplete="new-password">
                </div>
                <button class="btn-auth" type="submit">Sifreyi Degistir</button>
            </form>
        <?php endif; ?>

        <p class="form-options"><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giris sayfasina don</a></p>
    </div>
</div>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>
