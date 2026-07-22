<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

$homeUrl = $baseUri . '/index.php';
$requestedRedirect = loginSafeRedirect((string) ($_GET['redirect'] ?? $_POST['redirect'] ?? ''), $homeUrl);
$registrationOldInput = authRegistrationPreservedInput($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : []);
$loginUrl = routePublicStaticUrl('login');
$registerUrl = routePublicStaticUrl('register');
$loginUrlWithRedirect = authUrlWithRedirect($loginUrl, $requestedRedirect, $homeUrl);
$loginRegisteredUrl = authUrlWithRedirect($loginUrl . '?registered=1', $requestedRedirect, $homeUrl);
$registerUrlWithRedirect = authUrlWithRedirect($registerUrl, $requestedRedirect, $homeUrl);
$notificationsUrl = routePublicStaticUrl('notifications');

if ($isLoggedIn) {
    authSessionUserContext()->regenerateSession();
    header('Location: ' . $requestedRedirect);
    exit;
}

$errorMsg = '';
$successMsg = '';
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$allowRegistration = (string) ($settings['allow_registration'] ?? '1') === '1';
$usernameBounds = function_exists('usersUsernameLengthBounds')
    ? usersUsernameLengthBounds($settings)
    : ['min' => 3, 'max' => 30];
$registrationRequiresAdminApproval = function_exists('usersRegistrationRequiresAdminApproval')
    ? usersRegistrationRequiresAdminApproval($settings)
    : false;
$registrationPendingMessage = function_exists('usersRegistrationPendingMessage')
    ? usersRegistrationPendingMessage($settings)
    : 'Hesabınız oluşturuldu. Yönetici onayından sonra giriş yapabilirsiniz.';
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
        $errorMsg = 'Güvenlik doğrulaması geçersiz oldu. Bilgileriniz korundu, lütfen tekrar deneyin.';
        csrf_token();
    } else {
        $usernameRaw = trim((string) ($_POST['username'] ?? ''));
        $username = function_exists('usersValidateUsernameInput')
            ? usersValidateUsernameInput($usernameRaw, $settings)
            : '';
        $usernamePolicyError = function_exists('usersValidateUsernamePolicy')
            ? usersValidateUsernamePolicy($username, $settings)
            : '';
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($username === '') {
            $errorMsg = "Kullanıcı adı {$usernameBounds['min']}-{$usernameBounds['max']} karakter olmalı ve sadece harf, rakam, _ veya - içermelidir.";
        } elseif ($usernamePolicyError !== '') {
            $errorMsg = $usernamePolicyError;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Geçerli bir e-posta adresi girin.';
        } elseif (($emailPolicyError = function_exists('usersValidateEmailDomainPolicy') ? usersValidateEmailDomainPolicy($email, $settings) : '') !== '') {
            $errorMsg = $emailPolicyError;
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
                        incrementRateLimit($regRateKey, $registerRateWindow);
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        $insertColumns = ['username', 'email', 'password', 'status', 'created_at', 'updated_at'];
                        $insertValues = [':username', ':email', ':password', ':status', 'NOW()', 'NOW()'];
                        $insertParams = [
                            'username' => $username,
                            'email' => $email,
                            'password' => $hashedPassword,
                            'status' => $registrationRequiresAdminApproval ? 'inactive' : 'active',
                        ];

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
                        if (function_exists('usersNotifyAdminsOnRegistration')) {
                            usersNotifyAdminsOnRegistration($pdo, $newUserId, $username, $email, $registrationRequiresAdminApproval);
                        }
                        if (function_exists('usersCreateWelcomeNotification')) {
                            usersCreateWelcomeNotification($pdo, $newUserId, $settings, $notificationsUrl);
                        }
                        $emailVerificationEnabled = authEmailVerificationEnabled($settings);
                        $verificationEmailSent = false;
                        try {
                            $accountMailer = accountEmailService($pdo);
                            $welcomeEmailSent = $accountMailer->send('welcome', $email, ['username' => $username, 'login_url' => $loginUrl, 'user_id' => (string) $newUserId]);
                            if (!$welcomeEmailSent) {
                                $welcomeEnabledKey = \App\Engine\Email\AccountEmailService::settingKey('welcome', 'enabled');
                                $welcomeTemplate = \App\Engine\Email\AccountEmailService::catalog()['welcome'] ?? ['enabled' => '1'];
                                $accountEmailSystemActive = function_exists('authSettingEnabled')
                                    ? authSettingEnabled($settings, 'account_email_system_enabled', '1')
                                    : in_array(strtolower(trim((string) ($settings['account_email_system_enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
                                $welcomeTemplateActive = function_exists('authSettingEnabled')
                                    ? authSettingEnabled($settings, $welcomeEnabledKey, (string) ($welcomeTemplate['enabled'] ?? '1'))
                                    : in_array(strtolower(trim((string) ($settings[$welcomeEnabledKey] ?? $welcomeTemplate['enabled'] ?? '1'))), ['1', 'true', 'yes', 'on'], true);
                                if ($accountEmailSystemActive && $welcomeTemplateActive) {
                                    $mailResult = function_exists('appLastMailResult') ? appLastMailResult() : [];
                                    $context = [
                                        'source' => 'registration_welcome_email_failed',
                                        'user_id' => $newUserId,
                                        'template' => 'welcome',
                                        'recipient_email' => $email,
                                        'reason' => function_exists('appSendMail') ? 'send_returned_false' : 'mail_helper_missing',
                                        'mail_error' => (string) ($mailResult['error'] ?? ''),
                                        'smtp_code' => $mailResult['smtp_code'] ?? null,
                                        'smtp_response' => (string) ($mailResult['smtp_response'] ?? ''),
                                    ];
                                    if (function_exists('appFileLog')) {
                                        appFileLog('warning', 'Registration welcome email was not sent.', $context);
                                    } else {
                                        error_log('Registration welcome email was not sent: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                    }
                                }
                            }
                            $verificationEmailSent = $accountMailer->issueVerification($newUserId, $email, $username);
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
                                'is_approved' => !$registrationRequiresAdminApproval,
                                'dedupe_key' => 'user_registered:user:' . $newUserId,
                            ]);
                        }
                        if (function_exists('usersNotifyAdminsOnSuspiciousRegistrations')) {
                            usersNotifyAdminsOnSuspiciousRegistrations($pdo, $settings);
                        }

                        $emailVerificationRequiredForLogin = authEmailVerificationRequiredForLogin($settings);
                        if (authRegistrationAutoLoginAllowed($settings, $registrationRequiresAdminApproval)) {
                            authSessionUserContext()->regenerateSession();
                            authPopulateSessionUser($pdo, [
                                'id' => $newUserId,
                                'username' => $username,
                                'email' => $email,
                                'status' => 'active',
                            ], false);
                            logActivity($pdo, 'user_login', 'user', $newUserId);
                            if (function_exists('logSuccessfulLogin')) {
                                logSuccessfulLogin($pdo, $newUserId, $email);
                            }
                            if (function_exists('eventsRecordActivity')) {
                                eventsRecordActivity($pdo, $newUserId, 'daily_login', 'user', $newUserId, [
                                    'dedupe_key' => 'daily_login:user:' . $newUserId . ':' . date('Y-m-d'),
                                ]);
                            }
                            $autoLoginMessage = 'Hesabınız oluşturuldu. Oturumunuz açıldı.';
                            if ($emailVerificationEnabled && !$emailVerificationRequiredForLogin) {
                                $autoLoginMessage .= $verificationEmailSent
                                    ? ' E-posta doğrulama bağlantısı gönderildi.'
                                    : ' E-posta doğrulama bağlantısı şu anda gönderilemedi; hesabını kullanmaya devam edebilirsin.';
                            }
                            flash('success', $autoLoginMessage);
                            header('Location: ' . $requestedRedirect);
                            exit;
                        }

                        $registrationSuccessMessage = $registrationPendingMessage;
                        if (!$registrationRequiresAdminApproval) {
                            if ($emailVerificationRequiredForLogin) {
                                $registrationSuccessMessage = $verificationEmailSent
                                    ? 'Hesabınız oluşturuldu. Giriş yapmadan önce e-posta adresinize gönderilen doğrulama bağlantısını açmanız gerekiyor.'
                                    : 'Hesabınız oluşturuldu. Giriş yapmadan önce e-posta adresinizi doğrulamanız gerekiyor. Yeni bağlantı isteyebilirsiniz.';
                            } else {
                                $registrationSuccessMessage = 'Hesabınız oluşturuldu. Şimdi giriş yapabilirsiniz.';
                            }
                        }
                        flash('success', $registrationSuccessMessage);
                        header('Location: ' . $loginRegisteredUrl);
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
$auth_redirect = $requestedRedirect;
$auth_login_url = $loginUrlWithRedirect;
$auth_register_url = $registerUrlWithRedirect;
$auth_csrf_token = csrf_token();
$auth_allow_registration = $allowRegistration;
$auth_username_value = $registrationOldInput['username'];
$auth_email_value = $registrationOldInput['email'];
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
                <?= uiRenderAlert((string) $errorMsg, 'danger', ['attrs' => ['aria-live' => 'assertive']]) ?>
            <?php endif; ?>
            <?php if ($successMsg): ?>
                <?= uiRenderAlert((string) $successMsg, 'success', ['role' => 'alert']) ?>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>" novalidate data-auth-csrf-refresh>
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($requestedRedirect, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group auth-field">
                    <label for="username">Kullanıcı Adı</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <input id="username" name="username" type="text" value="<?= htmlspecialchars($registrationOldInput['username'], ENT_QUOTES, 'UTF-8') ?>" required minlength="<?= (int) $usernameBounds['min'] ?>" maxlength="<?= (int) $usernameBounds['max'] ?>" pattern="[A-Za-z0-9_-]{<?= (int) $usernameBounds['min'] ?>,<?= (int) $usernameBounds['max'] ?>}" aria-required="true" autocomplete="username">
                    </span>
                </div>
                <div class="form-group auth-field">
                    <label for="email">E-posta</label>
                    <span class="auth-input-shell ui-section">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input id="email" name="email" type="email" value="<?= htmlspecialchars($registrationOldInput['email'], ENT_QUOTES, 'UTF-8') ?>" required aria-required="true" autocomplete="email">
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
                <p class="form-options">Zaten hesabın var mı? <a href="<?= htmlspecialchars($loginUrlWithRedirect, ENT_QUOTES, 'UTF-8') ?>">Giriş Yap</a></p>
            </div>
        </div>
    </section>
</div>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>

