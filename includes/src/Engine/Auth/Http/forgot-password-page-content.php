<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

if ($isLoggedIn) {
    header('Location: ' . $baseUri . '/index.php');
    exit;
}

$loginUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('login')
    : (rtrim((string) $baseUri, '/') . '/giris');
$forgotPasswordUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('forgot_password')
    : (rtrim((string) $baseUri, '/') . '/sifremi-unuttum');

$errorMsg = '';
$successMsg = '';
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$passwordResetRateLimit = max(1, (int)($settings['password_reset_rate_limit'] ?? 3));
$passwordResetRateWindow = max(1, (int)($settings['password_reset_rate_window'] ?? 30));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forgotRateKey = 'forgot_pw_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($forgotRateKey, $passwordResetRateLimit, $passwordResetRateWindow)) {
        $remaining = getRateLimitRemainingSeconds($forgotRateKey, $passwordResetRateWindow);
        $minutes = (int) ceil($remaining / 60);
        $errorMsg = "Çok fazla istek. Lütfen {$minutes} dakika sonra tekrar deneyin.";
    } elseif (!verify_csrf_token($_POST['_token'] ?? '')) {
        $errorMsg = 'Güvenlik doğrulaması başarısız.';
    } else {
        incrementRateLimit($forgotRateKey, $passwordResetRateWindow);
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Geçerli bir e-posta adresi girin.';
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = :email LIMIT 1');
                    $stmt->execute(['email' => $email]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        $pdo->prepare('UPDATE users SET password_reset_token = :token, password_reset_expires_at = :expires_at, updated_at = NOW() WHERE id = :id')
                            ->execute([
                                'token' => hash('sha256', $token),
                                'expires_at' => $expiry,
                                'id' => $user['id'],
                            ]);

                        logActivity($pdo, 'password_reset_requested', 'user', (int) $user['id']);

                        $publicBaseUrl = function_exists('appPublicBaseUrl')
                            ? appPublicBaseUrl(false, $baseUri, $envConfig ?? [])
                            : rtrim((string) $baseUri, '/');
                        if ($publicBaseUrl === '') {
                            $publicBaseUrl = rtrim((string) $baseUri, '/');
                        }
                        $resetUrl = rtrim($publicBaseUrl, '/') . '/' . ltrim(routePublicStaticPath('reset_password'), '/') . '?token=' . $token . '&email=' . urlencode($email);

                        require_once $projectRoot . '/includes/src/Engine/Email/Legacy/helpers.php';
                        sendPasswordResetEmail($email, (string) ($user['username'] ?? ''), $resetUrl);

                        if ($appDebug) {
                            $successMsg = 'Şifre sıfırlama bağlantısı: ' . $resetUrl;
                        } else {
                            $successMsg = 'Şifre sıfırlama bağlantısı e-posta adresinize gönderildi.';
                        }
                    } else {
                        $successMsg = 'Eğer bu e-posta kayıtlıysa, şifre sıfırlama bağlantısı gönderildi.';
                    }
                } catch (Throwable $e) {
                    $errorMsg = safeErrorMessage($e, 'İşlem sırasında bir hata oluştu.');
                }
            } else {
                $errorMsg = 'Veritabanı bağlantısı kurulamadı.';
            }
        }
    }
}

$pageTitle = 'Şifremi Unuttum';
$auth_error = $errorMsg;
$auth_success = $successMsg;
$auth_csrf_token = csrf_token();
$auth_email_value = (string) ($_POST['email'] ?? '');
require_once $projectRoot . '/includes/public-header.php';
if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
    require_once $projectRoot . '/includes/public-footer.php';
    return;
}
?>

<?= renderPublicBreadcrumb([
    ['label' => 'Ana Sayfa', 'url' => $baseUri . '/index.php'],
    ['label' => 'Şifremi Unuttum'],
], 'auth-breadcrumb') ?>

<div class="auth-wrapper auth-screen auth-screen-forgot ui-panel">
    <section class="auth-stage auth-stage-compact" aria-labelledby="forgotTitle">
        <aside class="auth-visual" aria-label="Şifre sıfırlama bilgileri">
            <span class="auth-kicker">Hesap kurtarma</span>
            <h2>Hesabına güvenli şekilde geri dön.</h2>
            <p>E-posta adresini doğrulayalım, ardından sana geçici ve güvenli bir şifre sıfırlama bağlantısı gönderelim.</p>
            <div class="auth-benefits">
                <span><i class="bi bi-envelope-check" aria-hidden="true"></i> E-posta ile doğrulama</span>
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> Süreli sıfırlama bağlantısı</span>
                <span><i class="bi bi-shield-lock" aria-hidden="true"></i> Güvenli parola yenileme</span>
            </div>
        </aside>

        <div class="auth-box ui-panel">
            <div class="auth-header ui-panel__head">
                <span class="auth-header-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">Şifre yenileme</span>
                <h1 id="forgotTitle">Şifremi Unuttum</h1>
                <p>E-posta adresini gir, şifre sıfırlama bağlantısını gönderelim.</p>
            </div>

            <?php if ($errorMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert" aria-live="assertive"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>
            <?php if ($successMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="alert"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= htmlspecialchars($forgotPasswordUrl, ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-group auth-field">
                    <label for="email">E-posta</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="ornek@email.com" required aria-required="true" autocomplete="email">
                    </span>
                </div>
                <button class="btn-auth" type="submit">
                    <span>Sıfırlama Bağlantısı Gönder</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="auth-footer-actions">
                <p class="form-options"><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş sayfasına dön</a></p>
            </div>
        </div>
    </section>
</div>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>


