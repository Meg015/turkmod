<?php

declare(strict_types=1);
/**
 * Dosya Yöneticisi — Controller
 * İş mantığı: includes/src/Engine/Media/Legacy/helpers.php
 */
require_once __DIR__ . '/init.php';
adminRequirePermission('media.view', 'Dosya yoneticisini goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Dosya Yöneticisi';
$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];
$uploadRoot = mediaNormalizeRelativePath((string) ($settings['upload_path'] ?? 'uploads'));
if ($uploadRoot === '' || str_contains($uploadRoot, ':')) {
    $uploadRoot = 'uploads';
}
$uploadRootLabel = trim($uploadRoot, '/');
$projectRoot = dirname(__DIR__);
$cacheFile = $projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'media_global_stats.json';
$uploadBaseCandidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadRootLabel);
$uploadBase = realpath($uploadBaseCandidate) ?: $uploadBaseCandidate;
$uploadWebBase = $baseUri . '/' . implode('/', array_map('rawurlencode', explode('/', $uploadRootLabel)));
$maxUploadMB = (int) ($settings['max_upload_size'] ?? 50);
$allowedFileExt = mediaNormalizeAllowedFileExtensions(array_values(array_filter(array_map('trim', explode(',', (string) ($settings['allowed_file_ext'] ?? 'jpg,jpeg,png,gif,webp,pdf,zip,rar,7z,txt,md,json,csv,docx,xlsx,pptx,mp4,webm,mp3,wav'))))));
$allowedImageExt = mediaNormalizeAllowedImageExtensions(array_values(array_filter(array_map('trim', explode(',', (string) ($settings['allowed_image_ext'] ?? 'png,jpg,jpeg,webp,gif'))))));
$allowedExt = array_values(array_unique(array_merge($allowedFileExt, $allowedImageExt)));
sort($allowedExt);
$acceptFileAttr = '.' . implode(',.', $allowedExt);
$configuredDefaultFolder = (string) ($settings['media_default_folder'] ?? 'genel');
if (!in_array($configuredDefaultFolder, ['konu', 'profil', 'genel'], true)) {
    $configuredDefaultFolder = 'genel';
}

mediaEnsureDirectories($uploadBase);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$mediaCurrentPath = mediaNormalizeRelativePath((string) ($_GET['path'] ?? $_POST['path'] ?? ''));
$currentScanPath = mediaResolvePath($uploadBase, $mediaCurrentPath) ?: (realpath($uploadBase) ?: $uploadBase);
$mediaCurrentPath = mediaRelativeToUploads($uploadBase, $currentScanPath);
if ($mediaCurrentPath === '.') {
    $mediaCurrentPath = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    $isAjaxUpload = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        if ($isAjaxUpload) {
            sendCsrfError();
        }
        flash('error', 'Güvenlik hatası.');
        header('Location: media-manager.php');
        exit;
    }
    if (!adminCurrentUserCan('media.manage')) {
        adminDenyAction('Medya yuklemek icin gerekli izin hesabiniza tanimlanmamis.', 'media-manager.php');
    }

    $targetPath = trim(mediaNormalizeRelativePath((string) ($_POST['path'] ?? '')), '/');
    if ($targetPath === '') {
        $targetPath = $configuredDefaultFolder;
    }
    $pathParts = array_values(array_filter(explode('/', $targetPath), static fn (string $part): bool => $part !== ''));
    $pathParts = array_values(array_filter(array_map(static function (string $part): string {
        $safe = preg_replace('/[^a-z0-9\-_]/i', '-', strtolower(trim($part)));
        $safe = preg_replace('/-+/', '-', trim((string) $safe, '-'));
        return $safe ?: '';
    }, $pathParts), static fn (string $part): bool => $part !== '' && $part !== '.' && $part !== '..'));
    if ($pathParts === []) {
        $pathParts = [$configuredDefaultFolder];
    }
    $targetRoot = $pathParts[0];
    if (!in_array($targetRoot, ['konu', 'profil', 'genel'], true)) {
        $message = 'Yukleme yolu "konu", "profil" veya "genel" ile baslamalidir.';
        if ($isAjaxUpload) {
            sendValidationError($message);
        }
        flash('error', $message);
        $redirect = 'media-manager.php';
        if ($mediaCurrentPath !== '') {
            $redirect .= '?path=' . urlencode($mediaCurrentPath);
        }
        header('Location: ' . $redirect);
        exit;
    }
    $subFolder = implode('/', array_slice($pathParts, 1));
    $targetPath = $targetRoot . ($subFolder !== '' ? '/' . $subFolder : '');

    $result = mediaUploadFiles(
        $pdo,
        $_FILES['media_files'] ?? [],
        $uploadBase,
        $targetRoot,
        $subFolder,
        $allowedExt,
        $maxUploadMB,
        $_SESSION['_auth_user_id'] ?? null,
        $settings
    );

    if ($result['uploaded'] > 0) {
        logActivity($pdo, 'media_uploaded', 'media', null, ['count' => $result['uploaded'], 'path' => $targetPath]);
        flash('success', $result['uploaded'] . ' dosya başarıyla yüklendi.' . (!empty($result['errors']) ? ' (' . count($result['errors']) . ' hata)' : ''));
        @unlink($cacheFile);
    }
    if (!empty($result['errors']) && $result['uploaded'] === 0) {
        flash('error', implode(' ', $result['errors']));
    }

    if ($isAjaxUpload) {
        $message = $result['uploaded'] > 0 ? $result['uploaded'] . ' dosya başarıyla yüklendi.' . (!empty($result['errors']) ? ' (' . count($result['errors']) . ' hata)' : '') : implode(' ', $result['errors']);
        if ($result['uploaded'] > 0) {
            sendSuccess($message, [
                'uploaded' => $result['uploaded'],
                'errors' => $result['errors'],
            ]);
        }

        sendError('media_upload_failed', $message !== '' ? $message : 'Dosya yüklenemedi.', 422, [
            'uploaded' => $result['uploaded'],
            'errors' => $result['errors'],
        ]);
    }

    $redirect = 'media-manager.php';
    if ($targetPath !== '') {
        $redirect .= '?path=' . urlencode($targetPath);
    }
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'check_usage') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        sendCsrfError();
    }

    $url = (string)($_POST['url'] ?? '');
    $path = (string)($_POST['path'] ?? '');
    if ($url === '') {
        sendValidationError('URL bulunamadı');
    }

    $usages = [];
    $likeUrl = '%' . $url . '%';
    $likePath = $path !== '' ? '%' . $path . '%' : $likeUrl;
    $filename = basename($url);
    $likeFilename = $filename !== '' ? '%' . $filename . '%' : $likeUrl;
    
    $added = [];
    
    // Konu içerikleri (topic_descriptions)
    $stmt = $pdo->prepare("SELECT id, title FROM topics WHERE topic_descriptions LIKE ? OR topic_descriptions LIKE ? LIMIT 5");
    $stmt->execute([$likeUrl, $likeFilename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $topic) {
        $key = 'topic_' . $topic['id'];
        if (!isset($added[$key])) {
            $usages[] = ['type' => 'topic', 'name' => 'Konu İçeriği: ' . $topic['title'], 'link' => 'edit.php?id=' . $topic['id']];
            $added[$key] = true;
        }
    }

    // Media dosyaları
    $stmt = $pdo->prepare("SELECT m.id, t.id as topic_id, t.title FROM media_files m JOIN topics t ON m.topic_id = t.id WHERE m.path LIKE ? OR m.path LIKE ? OR m.path LIKE ? LIMIT 5");
    $stmt->execute([$likeUrl, $likePath, $likeFilename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = 'topic_' . $row['topic_id'];
        if (!isset($added[$key])) {
            $usages[] = ['type' => 'media', 'name' => 'Konu Dosyası: ' . $row['title'], 'link' => 'edit.php?id=' . $row['topic_id']];
            $added[$key] = true;
        }
    }
    
    // Bot içerikleri (bot_imports)
    $stmt = $pdo->prepare("SELECT id, source_title, status FROM bot_imports WHERE downloaded_images LIKE ? OR source_content LIKE ? OR translated_content LIKE ? LIMIT 5");
    $stmt->execute([$likeFilename, $likeFilename, $likeFilename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $bot) {
        $key = 'bot_' . $bot['id'];
        if (!isset($added[$key])) {
            $usages[] = ['type' => 'bot', 'name' => 'Bot İçeriği: ' . $bot['source_title'] . ' (' . $bot['status'] . ')', 'link' => 'scraper.php'];
            $added[$key] = true;
        }
    }
    
    // Kullanıcılar
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE avatar LIKE ? OR avatar LIKE ? LIMIT 5");
    $stmt->execute([$likeUrl, $likeFilename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $key = 'user_' . $user['id'];
        if (!isset($added[$key])) {
            $usages[] = ['type' => 'user', 'name' => 'Kullanıcı Profil: ' . $user['name'], 'link' => 'users.php?edit=' . $user['id']];
            $added[$key] = true;
        }
    }
    
    // Ayarlar
    $stmt = $pdo->prepare("SELECT id, `key` FROM settings WHERE value LIKE ? OR value LIKE ? LIMIT 5");
    $stmt->execute([$likeUrl, $likeFilename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        $key = 'setting_' . $setting['key'];
        if (!isset($added[$key])) {
            $usages[] = ['type' => 'setting', 'name' => 'Site Ayarı: ' . $setting['key'], 'link' => 'settings.php'];
            $added[$key] = true;
        }
    }

    sendSuccess('Kullanım bilgisi getirildi.', ['usages' => $usages]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: media-manager.php');
        exit;
    }
    if (!adminCurrentUserCan('media.manage')) {
        adminDenyAction('Medya silmek icin gerekli izin hesabiniza tanimlanmamis.', 'media-manager.php');
    }

    if (mediaDeleteFile($pdo, $uploadBase, (string) ($_POST['file_path'] ?? ''))) {
        logActivity($pdo, 'media_deleted', 'media', null, ['file_path' => (string) ($_POST['file_path'] ?? '')]);
        flash('success', 'Dosya silindi.');
        @unlink($cacheFile);
    } else {
        flash('error', 'Dosya bulunamadı veya silinemedi.');
    }

    $returnPath = mediaNormalizeRelativePath((string) ($_POST['return_path'] ?? ''));
    $redirect = 'media-manager.php' . ($returnPath !== '' ? '?path=' . urlencode($returnPath) : '');
    header('Location: ' . $redirect);
    exit;
}

$items = mediaScanDir($currentScanPath, $uploadWebBase, $mediaCurrentPath);

$cacheDuration = 3600; // 1 hour cache duration
$mediaStats = null;
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
    $mediaStats = json_decode((string)file_get_contents($cacheFile), true);
}
if (!is_array($mediaStats) || !isset($mediaStats['files'])) {
    $mediaStats = mediaGetGlobalStats($uploadBase);
    @file_put_contents($cacheFile, json_encode($mediaStats));
}

$totalFiles = $mediaStats['files'] ?? 0;
$totalImages = $mediaStats['images'] ?? 0;
$totalDirs = $mediaStats['dirs'] ?? 0;
$totalSize = $mediaStats['size'] ?? 0;

$breadcrumbs = [];
if ($mediaCurrentPath !== '') {
    $accumulated = [];
    foreach (explode('/', $mediaCurrentPath) as $segment) {
        $accumulated[] = $segment;
        $breadcrumbs[] = [
            'name' => $segment,
            'path' => implode('/', $accumulated),
        ];
    }
}

$parentPath = '';
if ($mediaCurrentPath !== '') {
    $parts = explode('/', $mediaCurrentPath);
    array_pop($parts);
    $parentPath = implode('/', $parts);
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

$pageCssFiles = ['assets/media-manager.css'];
require_once __DIR__ . '/header.php';
?>

<?php if ($successMsg): ?>
<div class="mm-alert mm-alert-success" role="alert">
    <div class="mm-alert-icon"><i class="bi bi-check-circle-fill"></i></div>
    <div class="mm-alert-content"><?= htmlspecialchars($successMsg) ?></div>
    <button type="button" class="mm-alert-close" aria-label="Kapat">&times;</button>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="mm-alert mm-alert-error" role="alert">
    <div class="mm-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div class="mm-alert-content"><?= htmlspecialchars($errorMsg) ?></div>
    <button type="button" class="mm-alert-close" aria-label="Kapat">&times;</button>
</div>
<?php endif; ?>

<?php
$gdLoaded = extension_loaded('gd');
$gdInfo = $gdLoaded ? gd_info() : [];
$webpActive = ($settings['webp_enabled'] ?? '1') === '1' && !empty($gdInfo['WebP Support']);
$defaultUploadPath = $mediaCurrentPath !== '' ? $mediaCurrentPath : $configuredDefaultFolder;
$currentDisplayPath = $uploadRootLabel . ($mediaCurrentPath !== '' ? '/' . $mediaCurrentPath : '');
?>
<div class="mm-page">
<div class="admin-stat-grid mm-stats-grid ui-grid">
    <div class="admin-stat-card stat-info mm-stat-card ui-card">
        <div class="stat-icon"><i class="bi bi-file-earmark"></i></div>
        <div class="stat-content">
            <span class="stat-label">Toplam Dosya</span>
            <span class="stat-value"><?= $totalFiles ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-success mm-stat-card ui-card">
        <div class="stat-icon"><i class="bi bi-image"></i></div>
        <div class="stat-content">
            <span class="stat-label">Görsel</span>
            <span class="stat-value"><?= $totalImages ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-warning mm-stat-card ui-card">
        <div class="stat-icon"><i class="bi bi-folder2"></i></div>
        <div class="stat-content">
            <span class="stat-label">Klasör</span>
            <span class="stat-value"><?= $totalDirs ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-info mm-stat-card ui-card">
        <div class="stat-icon"><i class="bi bi-hdd"></i></div>
        <div class="stat-content">
            <span class="stat-label">Toplam Boyut</span>
            <span class="stat-value"><?= mediaFormatBytes($totalSize) ?></span>
        </div>
    </div>
    <div class="admin-stat-card <?= $webpActive ? 'stat-success' : 'stat-warning' ?> mm-stat-card ui-card">
        <div class="stat-icon"><i class="bi bi-image-alt"></i></div>
        <div class="stat-content">
            <span class="stat-label">WebP Dönüşümü</span>
            <span class="stat-value"><?= $webpActive ? 'Aktif' : 'Kapalı' ?></span>
        </div>
    </div>
    <a href="settings.php#file_manager" class="admin-stat-card stat-info mm-stat-card mm-stat-link ui-card">
        <div class="stat-icon"><i class="bi bi-sliders"></i></div>
        <div class="stat-content">
            <span class="stat-label">Dosya Ayarları</span>
            <span class="stat-value">Yapılandır</span>
        </div>
    </a>
</div>

<div class="mm-container">
    <div class="mm-header">
        <div class="mm-breadcrumb">
            <a href="media-manager.php" class="mm-breadcrumb-item mm-breadcrumb-root">
                <i class="bi bi-folder-fill"></i> <?= htmlspecialchars($uploadRootLabel) ?>
            </a>
            <?php foreach ($breadcrumbs as $bc): ?>
                <span class="mm-breadcrumb-sep">/</span>
                <a href="media-manager.php?path=<?= urlencode($bc['path']) ?>" class="mm-breadcrumb-item">
                    <?= htmlspecialchars($bc['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="mm-header-actions">
            <?php if ($mediaCurrentPath !== ''): ?>
                <a href="media-manager.php<?= $parentPath !== '' ? '?path=' . urlencode($parentPath) : '' ?>" class="mm-btn mm-btn-secondary mm-btn-sm">
                    <i class="bi bi-arrow-up"></i> Üst Klasör
                </a>
            <?php endif; ?>
            <button type="button" class="mm-btn mm-btn-primary mm-btn-sm" id="mediaUploadToggle">
                <i class="bi bi-cloud-arrow-up"></i> Dosya Yükle
            </button>
        </div>
    </div>

    <div id="uploadPanel" class="mm-upload-panel mm-hidden">
        <form method="post" action="media-manager.php" enctype="multipart/form-data" class="mm-upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">

            <div class="mm-upload-info">
                <div class="mm-upload-info-item">
                    <label class="mm-label">Yükleme Yolu</label>
                    <div class="mm-path-input-wrap">
                        <span class="mm-path-prefix"><i class="bi bi-folder2-open"></i> <?= htmlspecialchars($uploadRootLabel) ?>/</span>
                        <input
                            type="text"
                            name="path"
                            class="mm-path-input ui-admin-form-control"
                            value="<?= htmlspecialchars($defaultUploadPath) ?>"
                            placeholder="<?= htmlspecialchars($configuredDefaultFolder) ?>"
                            autocomplete="off"
                            spellcheck="false"
                            aria-describedby="mmPathHelp">
                    </div>
                    <small id="mmPathHelp" class="mm-path-help">Yol "konu", "profil" veya "genel" ile başlamalıdır. Alt klasör için "/" kullanabilirsiniz.</small>
                </div>
                <div class="mm-upload-info-item">
                    <label class="mm-label">Kurallar</label>
                    <div class="mm-rules-text">
                        Maks. <?= $maxUploadMB ?> MB • İzin verilen: <?= htmlspecialchars(implode(', ', $allowedExt)) ?>
                    </div>
                </div>
            </div>

            <div class="mm-upload-section">
                <label class="mm-label">Dosyalar</label>
                <div class="mm-dropzone" id="dropZone">
                    <input type="file" name="media_files[]" multiple accept="<?= htmlspecialchars($acceptFileAttr, ENT_QUOTES, 'UTF-8') ?>" id="fileInput" class="mm-hidden">
                    <div class="mm-dropzone-content" id="mediaDropzoneTrigger">
                        <div class="mm-dropzone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                        <p class="mm-dropzone-title">Dosyaları sürükleyip bırakın</p>
                        <p class="mm-dropzone-subtitle">veya seçmek için tıklayın</p>
                    </div>
                    <div id="previewList" class="mm-preview-list"></div>
                </div>
                <div id="uploadProgressContainer" class="mm-progress-container mm-hidden">
                    <div class="mm-progress-header">
                        <span>Yükleniyor...</span>
                        <span id="uploadProgressText">0%</span>
                    </div>
                    <div class="mm-progress-bar">
                        <div id="uploadProgressBar" class="mm-progress-fill"></div>
                    </div>
                </div>
            </div>

            <button type="submit" id="uploadSubmitBtn" class="mm-btn mm-btn-primary">
                <i class="bi bi-upload"></i> Yüklemeyi Başlat
            </button>
        </form>
    </div>

    <div class="mm-content">
        <div class="mm-content-info">
            <span>Geçerli dizin: <strong><?= htmlspecialchars($currentDisplayPath) ?></strong></span>
            <span>Klasörler önce, ardından dosyalar alfabetik sıralanır.</span>
        </div>

        <?php if (empty($items)): ?>
            <div class="mm-empty-state ui-admin-empty ui-empty">
                <div class="mm-empty-icon ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-folder2-open"></i></div>
                <p class="mm-empty-title ui-admin-empty-title ui-empty">Bu dizin boş</p>
                <p class="mm-empty-text ui-admin-empty-desc ui-empty">Yeni dosyalar yükleyebilir veya üst klasöre dönebilirsiniz.</p>
            </div>
        <?php else: ?>
            <div class="mm-grid">
                <?php foreach ($items as $item): ?>
                    <?php if (($item['type'] ?? '') === 'dir'): ?>
                        <a href="media-manager.php?path=<?= urlencode((string) $item['path']) ?>" class="mm-grid-item mm-grid-folder">
                            <div class="mm-grid-thumb">
                                <i class="bi bi-folder-fill"></i>
                            </div>
                            <div class="mm-grid-info">
                                <span class="mm-grid-name" title="<?= htmlspecialchars((string) $item['name']) ?>">
                                    <?= htmlspecialchars((string) $item['name']) ?>
                                </span>
                                <span class="mm-grid-meta">
                                    <?= (int) ($item['count'] ?? 0) ?> dosya (<?= (int) ($item['images'] ?? 0) ?> görsel) • <?= htmlspecialchars(mediaFormatBytes((int) ($item['size'] ?? 0))) ?>
                                </span>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="mm-grid-item"
                             data-media-preview-card
                             data-url="<?= htmlspecialchars((string) $item['url']) ?>"
                             data-name="<?= htmlspecialchars((string) $item['name']) ?>"
                             data-size="<?= htmlspecialchars(mediaFormatBytes((int) ($item['size'] ?? 0))) ?>"
                             data-date="<?= htmlspecialchars(date('d.m.Y H:i', (int) ($item['modified'] ?? time()))) ?>"
                             data-ext="<?= htmlspecialchars((string) ($item['ext'] ?? '')) ?>"
                             data-path="<?= htmlspecialchars((string) ($item['path'] ?? '')) ?>">
                            <div class="mm-grid-thumb">
                                <?php if (!empty($item['is_image'])): ?>
                                    <img src="<?= htmlspecialchars((string) $item['url']) ?>" alt="<?= htmlspecialchars((string) $item['name']) ?>" loading="lazy" width="160" height="100">
                                <?php else: ?>
                                    <i class="bi bi-file-earmark"></i>
                                <?php endif; ?>
                            </div>
                            <div class="mm-grid-info">
                                <span class="mm-grid-name" title="<?= htmlspecialchars((string) $item['name']) ?>">
                                    <?= htmlspecialchars((string) $item['name']) ?>
                                </span>
                                <span class="mm-grid-meta">
                                    <?= htmlspecialchars(mediaFormatBytes((int) ($item['size'] ?? 0))) ?> • <?= htmlspecialchars(strtoupper((string) ($item['ext'] ?? ''))) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<div id="mediaPreviewModal" class="mm-modal-overlay ui-admin-modal-overlay media-modal-overlay" hidden aria-hidden="true">
    <div class="mm-modal media-modal ui-admin-modal-shell ui-panel" role="dialog" aria-modal="true" aria-labelledby="previewTitle">
        <div class="mm-modal-header media-modal-header ui-panel__head">
            <h3 id="previewTitle" class="mm-modal-title"></h3>
            <button type="button" id="mediaPreviewClose" class="mm-modal-close ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="mm-modal-body media-modal-body ui-panel__body">
            <div id="previewImageWrap" class="mm-preview-image-wrap">
                <img id="previewImage" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="" class="mm-preview-image" width="1200" height="800">
            </div>
            <div class="mm-preview-details">
                <div class="mm-preview-detail-item">
                    <label class="mm-label">Dosya Adı</label>
                    <div id="previewName" class="mm-detail-value"></div>
                </div>
                <div class="mm-preview-detail-item">
                    <label class="mm-label">Boyut / Tür</label>
                    <div id="previewSizeType" class="mm-detail-value"></div>
                </div>
                <div class="mm-preview-detail-item">
                    <label class="mm-label">Tarih</label>
                    <div id="previewDate" class="mm-detail-value"></div>
                </div>
                <div class="mm-preview-detail-item mm-preview-detail-full">
                    <label class="mm-label">URL</label>
                    <div class="mm-url-input-group">
                        <input type="text" id="previewUrl" class="mm-input" readonly>
                        <button type="button" class="mm-btn mm-btn-secondary mm-btn-sm" id="previewCopyButton" title="Kopyala">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="mm-preview-detail-item mm-preview-detail-full">
                    <label class="mm-label">Kullanıldığı Yerler</label>
                    <div id="previewUsage" class="mm-usage-container">
                        <i class="bi bi-hourglass-split"></i> Kontrol ediliyor...
                    </div>
                </div>
            </div>
        </div>
        <div class="mm-modal-footer media-modal-footer ui-panel__foot">
            <a id="previewDownload" href="" download class="mm-btn mm-btn-secondary mm-btn-sm ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">
                <i class="bi bi-download"></i> İndir
            </a>
            <form method="post" action="media-manager.php" class="ui-admin-inline-form" data-admin-confirm="Bu dosyayı silmek istediğinize emin misiniz?" data-admin-confirm-title="Dosya silinsin mi?" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="file_path" id="previewDeletePath" value="">
                <input type="hidden" name="return_path" value="<?= htmlspecialchars($mediaCurrentPath) ?>">
                <button type="submit" class="mm-btn mm-btn-danger mm-btn-sm ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm">
                    <i class="bi bi-trash"></i> Sil
                </button>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url('admin/assets/media-manager-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>

