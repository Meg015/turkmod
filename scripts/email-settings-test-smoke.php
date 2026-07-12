<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$javascript = file_get_contents($root . '/admin/assets/settings-page-main.js');
$settingsController = file_get_contents($root . '/admin/settings.php');

if (!is_string($javascript) || !is_string($settingsController)) {
    fwrite(STDERR, "Email settings smoke failed: source files could not be read.\n");
    exit(1);
}

$assertions = [
    'submitter action is detected' => str_contains($javascript, "submitter.name === 'action'"),
    'AJAX payload receives explicit action' => str_contains($javascript, "formData.set('action', submitAction)"),
    'test action bypasses save-only validation' => str_contains($javascript, "if (isSettingsSave)"),
    'server has a dedicated test branch' => str_contains($settingsController, "if (\$postAction === 'send_email_test')"),
    'test branch calls the shared mail sender' => str_contains($settingsController, '$mailOk = appSendMail('),
    'test failure returns its own API code' => str_contains($settingsController, "sendError('email_test_failed'"),
    'test success returns operation-specific text' => str_contains($settingsController, "Test e-postası gönderildi:"),
];

$failures = array_keys(array_filter($assertions, static fn (bool $passed): bool => !$passed));
if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "email settings test smoke OK\n";
