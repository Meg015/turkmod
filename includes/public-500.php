<?php
/**
 * 500 Hata Sayfası
 * ErrorHandler tarafından include edilir.
 * Tek başına çağrılırsa güvenli fallback gösterir.
 */

http_response_code(500);

$projectRoot = dirname(__DIR__);

// ErrorHandler üzerinden çağrılmadıysa temel CSS'i yükleyelim
$baseUri = $GLOBALS['baseUri'] ?? '';
$fallbackCss = rtrim($baseUri, '/') . '/assets/css/system-fallback.css';
$cssVersion = is_file($projectRoot . '/assets/css/system-fallback.css')
    ? (string) filemtime($projectRoot . '/assets/css/system-fallback.css')
    : '1';
$fallbackCss .= '?v=' . rawurlencode($cssVersion);

$appDebug = $GLOBALS['appDebug'] ?? false;
$errorInfo = $GLOBALS['_last_error'] ?? null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Sistem Hatası</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($fallbackCss, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="error-fallback-page">
    <div class="error-container">
        <h1>Sistem Hatası</h1>
        <p>Üzgünüz, beklenmeyen bir teknik sorun oluştu. Lütfen biraz sonra tekrar deneyin.</p>
        <p>Sorun devam ederse site yöneticisiyle iletişime geçin.</p>
        <?php if ($appDebug && $errorInfo !== null): ?>
            <pre class="dev-error-box" style="text-align:left;background:#f8d7da;color:#721c24;padding:16px;border-radius:8px;overflow:auto;font-size:13px;margin-top:16px"><?= htmlspecialchars((string) $errorInfo, ENT_QUOTES, 'UTF-8') ?></pre>
        <?php endif; ?>
        <p><a href="<?= htmlspecialchars(rtrim($baseUri, '/') ?: '/', ENT_QUOTES, 'UTF-8') ?>">Ana Sayfaya Dön</a></p>
    </div>
</body>
</html>
