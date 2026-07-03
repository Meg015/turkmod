<?php
/**
 * Popup Debug Page
 * Renders the popup exactly as index.php would, with the real theme CSS loaded.
 */
define('ALLOW_ADMIN_DEBUG', true);
chdir(dirname(__DIR__));
require_once __DIR__ . '/../includes/helpers.php';

// Load compiled settings
$settings = [];
$compiledPath = __DIR__ . '/../storage/cache/admin_settings_compiled.php';
if (file_exists($compiledPath)) {
    $settings = require $compiledPath;
}

// Force popup open
$settings['popup_announcement_enabled'] = '1';
$settings['popup_announcement_cookie_days'] = '0';

$popupHtml = renderPopupAnnouncementHtml(null, $settings);
?><!DOCTYPE html>
<html data-theme="dark" lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Popup Canlı Debug</title>
<!-- Same CSS as real site -->
<link rel="stylesheet" href="/yenidosyalar/assets/css/design-tokens.css">
<link rel="stylesheet" href="/yenidosyalar/themes/turkmod/css/bundle.css">
<style>
/* Force popup open for debugging */
.popup-announcement-overlay {
    opacity: 1 !important;
    visibility: visible !important;
    transition: none !important;
}
.popup-announcement-card {
    opacity: 1 !important;
    transform: none !important;
    animation: none !important;
}
body { background: #0f172a; min-height: 100vh; }
.debug-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #1e293b;
    border-bottom: 1px solid #334155;
    padding: 8px 20px;
    font-family: monospace;
    font-size: 13px;
    color: #94a3b8;
    z-index: 9999999;
    display: flex;
    gap: 20px;
    align-items: center;
}
.debug-bar strong { color: #f8fafc; }
</style>
</head>
<body>
<div class="debug-bar">
    <strong>🔍 Popup Canlı Debug</strong>
    <span>Tip: <strong style="color:#60a5fa"><?= htmlspecialchars($settings['popup_announcement_type'] ?? 'info') ?></strong></span>
    <span>Viewport: <strong style="color:#34d399" id="vpSize"></strong></span>
</div>
<?= $popupHtml ?>
<script>
document.getElementById('vpSize').textContent = window.innerWidth + 'x' + window.innerHeight;
window.addEventListener('resize', () => {
    document.getElementById('vpSize').textContent = window.innerWidth + 'x' + window.innerHeight;
});
</script>
</body>
</html>
