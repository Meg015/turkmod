<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';

$failures = [];
$catalog = \App\Engine\Email\AccountEmailService::catalog();
$required = ['welcome', 'verification_request', 'verification_completed', 'password_reset_request', 'password_reset_completed', 'password_changed', 'email_changed'];
foreach ($required as $key) {
    if (!isset($catalog[$key])) $failures[] = "missing template: {$key}";
    foreach (['enabled', 'subject', 'body'] as $field) {
        $settingKey = \App\Engine\Email\AccountEmailService::settingKey($key, $field);
        if (!isset(adminSettingDefinitions()[$settingKey])) $failures[] = "missing setting: {$settingKey}";
    }
}

$service = accountEmailService($pdo ?? null);
$rendered = $service->render('Merhaba {{username}} - {{site_name}}', ['username' => '<Test>', 'site_name' => 'Türk Mod']);
if (!str_contains($rendered, '&lt;Test&gt;') || !str_contains($rendered, 'Türk Mod')) $failures[] = 'template escaping failed';

$contracts = [
    'admin/settings.php' => ['email-tab-account', 'Hesap E-Posta Şablonları', 'account-email-body', 'send_account_email_test'],
    'admin/assets/settings-page-main.js' => ['ensureAccountEmailRichEditors', 'initAccountEmailQuill', 'initAccountEmailFallback', 'syncAllAccountEmailEditors', 'parseAccountEmailDocument', 'composeAccountEmailDocument', 'setAccountEmailEditorValue'],
    'includes/src/Engine/Auth/Http/register-page-content.php' => ["send('welcome'", 'issueVerification'],
    'api/auth-popup.php' => ["send('welcome'", 'issueVerification'],
    'includes/src/Engine/Auth/Http/reset-password-page-content.php' => ['password_reset_completed'],
    'includes/src/Engine/Users/Http/profile-page-content.php' => ['password_changed'],
    'admin/user-edit.php' => ['email_changed', 'password_changed'],
    'verify-email.php' => ['->verify('],
    'resend-verification.php' => ['issueVerification'],
];
foreach ($contracts as $file => $needles) {
    $content = file_get_contents(dirname(__DIR__) . '/' . $file);
    foreach ($needles as $needle) {
        if (!is_string($content) || !str_contains($content, $needle)) $failures[] = "missing contract {$needle} in {$file}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n");
    exit(1);
}

if (getenv('ACCOUNT_EMAIL_LIVE_TEST') === '1') {
    $recipient = trim((string) getenv('ACCOUNT_EMAIL_TEST_RECIPIENT'));
    $password = (string) getenv('ACCOUNT_EMAIL_SMTP_PASSWORD');
    if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false || $password === '') {
        fwrite(STDERR, "FAIL: live test environment is incomplete\n");
        exit(1);
    }
    $sent = $service->send('welcome', $recipient, ['username' => 'Test Kullanıcısı'], [
        'force' => true,
        'enabled' => '1',
        'settings' => [
            'mail_driver' => 'smtp',
            'smtp_host' => 'mail.turkmod.net',
            'smtp_port' => '465',
            'smtp_username' => 'info@turkmod.net',
            'smtp_password' => $password,
            'smtp_encryption' => 'ssl',
            'mail_from_name' => 'Türk Mod',
            'mail_from_address' => 'info@turkmod.net',
        ],
    ]);
    if (!$sent) {
        $result = function_exists('appLastMailResult') ? appLastMailResult() : [];
        fwrite(STDERR, 'FAIL: live account email delivery failed: ' . (string) ($result['error'] ?? 'unknown') . "\n");
        exit(1);
    }
    echo "account email live SMTP OK\n";
}

echo "account email smoke OK\n";
