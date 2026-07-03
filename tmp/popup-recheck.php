<?php
declare(strict_types=1);

$settings = [
    'popup_announcement_enabled' => '1',
    'popup_announcement_title' => 'selam',
    'popup_announcement_content' => '<p><s>asfasfsaf</s></p>',
    'popup_announcement_button_text' => 'Kapat',
    'popup_announcement_action_text' => '',
    'popup_announcement_action_url' => '',
    'popup_announcement_cookie_days' => '0',
    'popup_announcement_type' => 'info',
    'popup_announcement_strict' => '0',
    'popup_announcement_timer' => '0',
    'popup_announcement_target' => 'all',
];

require __DIR__ . '/../includes/helpers.php';
$html = renderPopupAnnouncementHtml(null, $settings);
$preview = '<!doctype html><html data-theme="dark"><head><meta charset="utf-8"><title>Popup Recheck</title><link rel="stylesheet" href="../assets/css/design-tokens.css"></head><body style="margin:0;min-height:100vh;background:#f0f2f5;font-family:Roboto,Arial,sans-serif"><main style="padding:24px"><div style="height:56px;background:#0f172a;border-radius:8px;margin-bottom:18px"></div><div style="display:grid;grid-template-columns:240px 1fr 260px;gap:16px"><aside style="height:420px;background:white;border:1px solid #d8dee8;border-radius:8px"></aside><section><div style="height:128px;background:white;border:1px solid #d8dee8;border-radius:8px;margin-bottom:12px"></div><div style="height:128px;background:white;border:1px solid #d8dee8;border-radius:8px"></div></section><aside style="height:420px;background:white;border:1px solid #d8dee8;border-radius:8px"></aside></div></main>' . $html . '</body></html>';
file_put_contents(__DIR__ . '/popup-recheck-preview.html', $preview);
echo __DIR__ . '/popup-recheck-preview.html' . PHP_EOL;
