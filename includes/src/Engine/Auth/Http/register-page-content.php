<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

if ($isLoggedIn) {
    authSessionUserContext()->regenerateSession();
    header('Location: ' . $baseUri . '/index.php');
    exit;
}

$loginUrl = routePublicStaticUrl('login');
$registerUrl = routePublicStaticUrl('register');
$notificationsUrl = routePublicStaticUrl('notifications');

$errorMsg = '';
$successMsg = '';
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$allowRegistration = (string) ($settings['allow_registration'] ?? '1') === '1';
$registerRateLimit = max(1, (int) ($settings['register_rate_limit'] ?? 3));
$registerRateWindow = max(1, (int) ($settings['register_rate_window'] ?? 30));
$passwordPolicy = passwordPolicyConfig($settings);
$passwordMinLength = (int) $passwordPolicy['min_length'];
$passwordPolicyHint = passwordPolicyHint($settings);

if ($pdo && function_exists('usersEnsureUsernameSchema')) {
    usersEnsureUsernameSchema($pdo);
}

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
        $usernameRaw = trim((string) ($_POST['username'] ?? ''));
        $username = function_exists('usersValidateUsernameInput')
            ? usersValidateUsernameInput($usernameRaw)
            : '';
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($username === '') {
            $errorMsg = 'Kullanıcı adı 3-30 karakter olmalı ve sadece harf, rakam, _ veya - içermelidir.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Geçerli bir e-posta adresi girin.';
        } elseif (($policyError = validatePasswordPolicy($password, $settings, 'Şifre')) !== '') {
            $errorMsg = $policyError;
        } elseif ($password !== $passwordConfirm) {
            $errorMsg = 'Şifreler eslesmiyor.';
        } else {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
                    $stmt->execute(['email' => $email]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $errorMsg = 'Bu e-posta adresi zaten kayıtlı.';
                    }

                    if ($errorMsg === '') {
                        $usernameCheckSql = 'SELECT COUNT(*) FROM users WHERE username = :username';
                        $usernameStmt = $pdo->prepare($usernameCheckSql);
                        $usernameStmt->execute(['username' => $username]);
                        if ((int) $usernameStmt->fetchColumn() > 0) {
                            $errorMsg = 'Bu kullanıcı adı zaten kayıtlı.';
                        }
                    }

                    if ($errorMsg === '') {
                        $memberRoleId = 3;
                        $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
                        $roleStmt->execute(['slug' => 'member']);
                        $resolvedRoleId = (int) $roleStmt->fetchColumn();
                        if ($resolvedRoleId > 0) {
                            $memberRoleId = $resolvedRoleId;
                        }

                        incrementRateLimit($regRateKey, $registerRateWindow);
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        $insertColumns = ['username', 'email', 'password'];
                        $insertValues = [':username', ':email', ':password'];
                        $insertParams = [
                            'username' => $username,
                            'email' => $email,
                            'password' => $hashedPassword,
                        ];
                        if (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'role_id')) {
                            $insertColumns[] = 'role_id';
                            $insertValues[] = ':role_id';
                            $insertParams['role_id'] = $memberRoleId;
                        }
                        if (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'status')) {
                            $insertColumns[] = 'status';
                            $insertValues[] = ':status';
                            $insertParams['status'] = 'active';
                        }
                        if (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'created_at')) {
                            $insertColumns[] = 'created_at';
                            $insertValues[] = 'NOW()';
                        }
                        if (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'updated_at')) {
                            $insertColumns[] = 'updated_at';
                            $insertValues[] = 'NOW()';
                        }

                        $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $insertColumns);
                        $insertSql = 'INSERT INTO users (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
                        $stmt = $pdo->prepare($insertSql);
                        $stmt->execute($insertParams);

                        $newUserId = (int) $pdo->lastInsertId();

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
                        try {
                            $accountMailer = accountEmailService($pdo);
                            $accountMailer->send('welcome', $email, ['username' => $username, 'login_url' => $loginUrl]);
                            $accountMailer->issueVerification($newUserId, $email, $username);
                        } catch (Throwable $mailError) {
                            if (function_exists('appLogException')) {
                                appLogException($mailError, ['source' => 'registration_account_email', 'user_id' => $newUserId]);
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
                        flash('success', 'Hesabınız oluşturuldu. Şimdi giriş yapabilirsiniz.');
                        header('Location: ' . $loginUrl . '?registered=1');
                        exit;
                    }
                } catch (Throwable $e) {
                    $errorMsg = safeErrorMessage($e, 'Kayıt sırasında bir hata oluştu.');
                }
            } else {
                $errorMsg = 'Veritabanı bağlantısı kurulamadı.';
            }
        }
    }
}

$pageTitle = 'Kayıt Ol';
$auth_error = $errorMsg;
$auth_success = $successMsg;
$auth_csrf_token = csrf_token();
$auth_allow_registration = $allowRegistration;
$auth_username_value = (string) ($_POST['username'] ?? '');
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
            <h2>Topluluğa katıl, iceriklerini gorunur hale getir.</h2>
            <p>Kendi modlarini yayinla, profilini olustur ve topluluk icindeki tum etkilesimlerini guvenle sakla.</p>
            <div class="auth-benefits">
                <span><i class="bi bi-upload" aria-hidden="true"></i> Icerik yayinlama</span>
                <span><i class="bi bi-person-badge" aria-hidden="true"></i> Profil yonetimi</span>
                <span><i class="bi bi-lightning-charge" aria-hidden="true"></i> Hizli baslangic</span>
            </div>
        </aside>

        <div class="auth-box ui-panel">
            <div class="auth-header ui-panel__head">
                <span class="auth-header-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                <span class="auth-eyebrow">Hesabını oluştur</span>
                <h1 id="registerTitle">Kayıt Ol</h1>
                <p>Topluluğa katıl ve kendi iceriklerini yayinlamaya basla.</p>
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
                    <label for="username">Kullanıcı Adı</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <input id="username" name="username" type="text" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" aria-required="true" autocomplete="username">
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
                    <label for="password">Şifre</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input id="password" name="password" type="password" required minlength="<?= (int) $passwordMinLength ?>" aria-required="true" autocomplete="new-password" data-password-strength data-password-confirm="#password_confirm" data-password-require-uppercase="<?= $passwordPolicy['require_uppercase'] ? '1' : '0' ?>" data-password-require-numbers="<?= $passwordPolicy['require_numbers'] ? '1' : '0' ?>" data-password-require-special="<?= $passwordPolicy['require_special'] ? '1' : '0' ?>">
                    </span>
                    <small><?= htmlspecialchars($passwordPolicyHint) ?></small>
                </div>
                <div class="form-group auth-field">
                    <label for="password_confirm">Şifre Tekrar</label>
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

