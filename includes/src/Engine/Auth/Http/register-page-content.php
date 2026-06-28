<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

if ($isLoggedIn) {
    authSessionUserContext()->regenerateSession();
    header('Location: ' . $baseUri . '/index.php');
    exit;
}

$loginUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('login')
    : ($baseUri . '/giris');
$registerUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('register')
    : ($baseUri . '/kayit');
$notificationsUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('notifications')
    : ($baseUri . '/notifications.php');

$errorMsg = '';
$successMsg = '';
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$allowRegistration = (string) ($settings['allow_registration'] ?? '1') === '1';
$registerRateLimit = max(1, (int) ($settings['register_rate_limit'] ?? 3));
$registerRateWindow = max(1, (int) ($settings['register_rate_window'] ?? 30));
$passwordPolicy = passwordPolicyConfig($settings);
$passwordMinLength = (int) $passwordPolicy['min_length'];
$passwordPolicyHint = passwordPolicyHint($settings);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regRateKey = 'register_' . getRealIp();
    if (!$allowRegistration) {
        $errorMsg = 'Yeni kayıtlar şu anda kapalı.';
    } elseif (!checkRateLimit($regRateKey, $registerRateLimit, $registerRateWindow)) {
        $remaining = getRateLimitRemainingSeconds($regRateKey, $registerRateWindow);
        $minutes = (int) ceil($remaining / 60);
        $errorMsg = "Çok fazla kayıt denemesi. Lütfen {$minutes} dakika sonra tekrar deneyin.";
    } elseif (!verify_csrf_token($_POST['_token'] ?? '')) {
        $errorMsg = 'Güvenlik doğrulaması başarısız.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if ($name === '' || mb_strlen($name) < 2) {
            $errorMsg = 'Isim en az 2 karakter olmalidir.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Gecerli bir e-posta adresi girin.';
        } elseif (($policyError = validatePasswordPolicy($password, $settings, 'Ã…Âifre')) !== '') {
            $errorMsg = $policyError;
        } elseif ($password !== $passwordConfirm) {
            $errorMsg = 'Sifreler eslesmiyor.';
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
                    $stmt->execute(['email' => $email]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $errorMsg = 'Bu e-posta adresi zaten kayitli.';
                    } else {
                        $memberRoleId = 3;
                        $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
                        $roleStmt->execute(['slug' => 'member']);
                        $resolvedRoleId = (int) $roleStmt->fetchColumn();
                        if ($resolvedRoleId > 0) {
                            $memberRoleId = $resolvedRoleId;
                        }

                        incrementRateLimit($regRateKey, $registerRateWindow);
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (role_id, name, email, password, status, created_at, updated_at) VALUES (:role_id, :name, :email, :password, 'active', NOW(), NOW())");
                        $stmt->execute([
                            'role_id' => $memberRoleId,
                            'name' => $name,
                            'email' => $email,
                            'password' => $hashedPassword,
                        ]);

                        $newUserId = (int) $pdo->lastInsertId();

                        // Grup Entegrasyonu: Yeni kayıt olan kullanıcıyı varsayılan gruba ata
                        if (function_exists('usersSyncUserGroups') && function_exists('usersDefaultGroupId')) {
                            $defaultGroupId = usersDefaultGroupId($pdo);
                            if ($defaultGroupId > 0) {
                                usersSyncUserGroups($pdo, $newUserId, [$defaultGroupId], 0, 'automatic_registration_group');
                            }
                        }

                        logActivity($pdo, 'user_registered', 'user', $newUserId, ['email' => $email]);
                        if (($settings['notif_center_enabled'] ?? '1') === '1' && ($settings['notif_welcome_enabled'] ?? '0') === '1') {
                            try {
                                $senderName = trim((string) ($settings['notif_system_sender'] ?? 'Sistem'));
                                $senderName = $senderName !== '' ? $senderName : 'Sistem';
                                $welcomeMessage = trim((string) ($settings['notif_welcome_msg'] ?? 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.'));
                                $welcomeMessage = $welcomeMessage !== '' ? $welcomeMessage : 'Aramıza hoş geldiniz! Kuralları okumayı unutmayın.';
                                $welcomeTitle = mb_substr($senderName . ' hoş geldin dedi', 0, 255);
                                $notificationStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, 'system', ?)");
                                $notificationStmt->execute([$newUserId, $welcomeTitle, $welcomeMessage, $notificationsUrl]);
                            } catch (Throwable $notificationError) {
                                // Kayıt akışı bildirim gönderilemedi diye bozulmasın.
                            }
                        }
                        if (is_file($projectRoot . '/includes/src/Modules/Events/init.php')) {
                            require_once $projectRoot . '/includes/src/Modules/Events/init.php';
                        }
                        if (function_exists('eventsRecordActivity')) {
                            eventsRecordActivity($pdo, $newUserId, 'user_registered', 'user', $newUserId, [
                                'is_approved' => true,
                                'dedupe_key' => 'user_registered:user:' . $newUserId,
                            ]);
                        }
                        flash('success', 'Hesabiniz olusturuldu. Simdi giris yapabilirsiniz.');
                        header('Location: ' . $loginUrl . '?registered=1');
                        exit;
                    }
                } catch (Throwable $e) {
                    $errorMsg = safeErrorMessage($e, 'Kayit sirasinda bir hata olustu.');
                }
            } else {
                $errorMsg = 'Veritabani baglantisi kurulamadi.';
            }
        }
    }
}

$pageTitle = 'Kayıt Ol';
$auth_error = $errorMsg;
$auth_success = $successMsg;
$auth_csrf_token = csrf_token();
$auth_allow_registration = $allowRegistration;
$auth_name_value = (string) ($_POST['name'] ?? '');
$auth_email_value = (string) ($_POST['email'] ?? '');
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

<?= renderPublicBreadcrumb([
    ['label' => 'Ana Sayfa', 'url' => $baseUri . '/index.php'],
    ['label' => 'Kayıt Ol'],
], 'auth-breadcrumb') ?>

<div class="auth-wrapper auth-screen auth-screen-register ui-panel">
    <section class="auth-stage" aria-labelledby="registerTitle">
        <aside class="auth-visual" aria-label="Kayıt avantajları">
            <span class="auth-kicker">Yeni üyelik</span>
            <h2>Topluluğa katıl, içeriklerini görünür hale getir.</h2>
            <p>Kendi modlarını yayınla, profilini oluştur ve topluluk içindeki tüm etkileşimlerini güvenle sakla.</p>
            <div class="auth-benefits">
                <span><i class="bi bi-upload" aria-hidden="true"></i> İçerik yayınlama</span>
                <span><i class="bi bi-person-badge" aria-hidden="true"></i> Profil yönetimi</span>
                <span><i class="bi bi-lightning-charge" aria-hidden="true"></i> Hızlı başlangıç</span>
            </div>
        </aside>

        <div class="auth-box ui-panel">
            <div class="auth-header ui-panel__head">
                <span class="auth-header-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">Hesabını oluştur</span>
                <h1 id="registerTitle">Kayıt Ol</h1>
                <p>Topluluğa katıl ve kendi içeriklerini yayınlamaya başla.</p>
            </div>

            <?php if ($errorMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert" aria-live="assertive"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>
            <?php if ($successMsg): ?>
                <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="alert"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="form-group auth-field">
                    <label for="name">İsim</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <input id="name" name="name" type="text" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required minlength="2" maxlength="255" aria-required="true" autocomplete="name">
                    </span>
                </div>
                <div class="form-group auth-field">
                    <label for="email">E-posta</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required aria-required="true" autocomplete="email">
                    </span>
                </div>
                <div class="form-group auth-field">
                    <label for="password">Ã…Âifre</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" required minlength="<?= (int) $passwordMinLength ?>" aria-required="true" autocomplete="new-password" data-password-strength data-password-confirm="#password_confirm" data-password-require-uppercase="<?= $passwordPolicy['require_uppercase'] ? '1' : '0' ?>" data-password-require-numbers="<?= $passwordPolicy['require_numbers'] ? '1' : '0' ?>" data-password-require-special="<?= $passwordPolicy['require_special'] ? '1' : '0' ?>">
                    </span>
                    <small><?= htmlspecialchars($passwordPolicyHint) ?></small>
                </div>
                <div class="form-group auth-field">
                    <label for="password_confirm">Ã…Âifre Tekrar</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <input id="password_confirm" name="password_confirm" type="password" required minlength="<?= (int) $passwordMinLength ?>" aria-required="true" autocomplete="new-password">
                    </span>
                </div>
                <button class="btn-auth" type="submit">
                    <span>Kayıt Ol</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="auth-footer-actions">
                <p class="form-options">Zaten hesabın var mı? <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a></p>
            </div>
        </div>
    </section>
</div>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>


