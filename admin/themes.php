<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
adminRequirePermission('themes.view', 'Temalari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Tema Merkezi';

if (!isset($themeManager) || !$themeManager instanceof ThemeManager) {
    $themeManager = new ThemeManager(dirname(__DIR__), $baseUri ?? '', false);
    $themeManager->setActiveTheme($themeManager->defaultThemeId());
}

function adminThemesSaveSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
        VALUES (:key, :value, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->execute(['key' => $key, 'value' => $value]);

    try {
        $legacy = $pdo->prepare("INSERT INTO settings (`key`, value, type, created_at, updated_at)
            VALUES (:key, :value, 'string', NOW(), NOW())
            ON DUPLICATE KEY UPDATE value = VALUES(value), type = VALUES(type), updated_at = NOW()");
        $legacy->execute(['key' => $key, 'value' => $value]);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

    if (function_exists('invalidateAdminSettingsCache')) {
        invalidateAdminSettingsCache();
    }
}

function adminThemesDeleteSetting(PDO $pdo, string $key): void
{
    try {
        $stmt = $pdo->prepare("DELETE FROM admin_settings WHERE setting_key = :key");
        $stmt->execute(['key' => $key]);
    } catch (Throwable $e) {
        error_log('[silent-catch] ' . $e->getMessage());
    }

    try {
        $legacy = $pdo->prepare("DELETE FROM settings WHERE `key` = :key");
        $legacy->execute(['key' => $key]);
    } catch (Throwable $e) {
        error_log('[silent-catch] ' . $e->getMessage());
    }

    if (function_exists('invalidateAdminSettingsCache')) {
        invalidateAdminSettingsCache();
    }
}

function adminThemesRedirect(string $themeId = '', string $file = ''): void
{
    $query = [];
    if ($themeId !== '') {
        $query['theme'] = $themeId;
    }
    if ($file !== '') {
        $query['file'] = $file;
    }

    header('Location: themes.php' . ($query !== [] ? '?' . http_build_query($query) : ''));
    exit;
}

$selectedTheme = $themeManager->sanitizeThemeId((string) ($_GET['theme'] ?? $themeManager->activeThemeId()));
if ($selectedTheme === '' || !$themeManager->themeExists($selectedTheme)) {
    $selectedTheme = $themeManager->activeThemeId();
}
$selectedFile = trim((string) ($_GET['file'] ?? 'theme.json'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        adminThemesRedirect($selectedTheme, $selectedFile);
    }
    if (!adminCurrentUserCan('themes.edit')) {
        adminDenyAction('Tema islemlerini yapmak icin gerekli izin hesabiniza tanimlanmamis.', 'themes.php');
    }

    $action = (string) ($_POST['_theme_action'] ?? '');
    $postTheme = $themeManager->sanitizeThemeId((string) ($_POST['theme_id'] ?? $selectedTheme));

    try {
        if ($action === 'activate') {
            if ($postTheme === '' || !$themeManager->themeExists($postTheme)) {
                throw new InvalidArgumentException('Tema bulunamadı.');
            }
            $validation = $themeManager->validateTheme($postTheme, true);
            if (!$validation['ok']) {
                throw new RuntimeException('Tema aktif edilemez: ' . implode(' ', $validation['errors']));
            }
            adminThemesSaveSetting($pdo, 'theme_active_id', $postTheme);
            adminThemesDeleteSetting($pdo, 'active_public_theme');
            unset($_SESSION['_theme_preview_id']);
            flash('success', 'Tema aktif edildi.');
            adminThemesRedirect($postTheme);
        }

        if ($action === 'stop_preview') {
            unset($_SESSION['_theme_preview_id']);
            flash('success', 'Tema önizleme kapatıldı.');
            adminThemesRedirect($postTheme ?: $selectedTheme, $selectedFile);
        }

        if ($action === 'duplicate') {
            $source = $themeManager->sanitizeThemeId((string) ($_POST['source_theme'] ?? $themeManager->defaultThemeId()));
            $newId = $themeManager->sanitizeThemeId((string) ($_POST['new_theme_id'] ?? ''));
            $newName = trim((string) ($_POST['new_theme_name'] ?? ''));
            $themeManager->duplicateTheme($source, $newId, $newName);
            flash('success', 'Yeni tema oluşturuldu.');
            adminThemesRedirect($newId, 'theme.json');
        }

        if ($action === 'save_file') {
            $file = trim((string) ($_POST['file_path'] ?? ''));
            $content = (string) ($_POST['file_content'] ?? '');
            $themeManager->writeEditableFile($postTheme, $file, $content);
            flash('success', 'Tema dosyası kaydedildi.');
            adminThemesRedirect($postTheme, $file);
        }

        if ($action === 'convert_dle') {
            $file = trim((string) ($_POST['target_file'] ?? 'converted.tpl'));
            $content = (string) ($_POST['dle_content'] ?? '');
            if (trim($content) === '') {
                throw new InvalidArgumentException('Dönüştürülecek DLE içeriği boş.');
            }
            $themeManager->writeEditableFile($postTheme, $file, ThemeConverter::convertDleTemplate($content));
            flash('success', 'DLE template TurkMod TPL formatına dönüştürüldü.');
            adminThemesRedirect($postTheme, $file);
        }

        if ($action === 'save_settings') {
            $settingsJson = json_encode($_POST['theme_settings'] ?? []);
            adminThemesSaveSetting($pdo, 'theme_' . $postTheme . '_settings', $settingsJson);
            flash('success', 'Tema ayarları başarıyla kaydedildi.');
            adminThemesRedirect($postTheme, ''); // redirect without file to show summary/settings
        }

        if ($action === 'upload_zip') {
            if (empty($_FILES['theme_zip']['tmp_name']) || !is_uploaded_file($_FILES['theme_zip']['tmp_name'])) {
                throw new InvalidArgumentException('ZIP dosyası yüklenmedi.');
            }
            $result = $themeManager->installZip((string) $_FILES['theme_zip']['tmp_name']);
            $message = 'Tema yüklendi: ' . $result['theme_id'];
            if (!empty($result['warnings'])) {
                $message .= ' Uyarılar: ' . implode(' ', $result['warnings']);
            }
            flash('success', $message);
            adminThemesRedirect($result['theme_id'], 'theme.json');
        }

        throw new InvalidArgumentException('Bilinmeyen tema işlemi.');
    } catch (Throwable $e) {
        flash('error', safeErrorMessage($e));
        adminThemesRedirect($postTheme ?: $selectedTheme, $selectedFile);
    }
}

$settings = getAdminSettings($pdo);
$activeThemeId = (string) ($settings['theme_active_id'] ?? 'default');
if ($activeThemeId === '' || !$themeManager->themeExists($activeThemeId)) {
    $activeThemeId = $themeManager->defaultThemeId();
}
$themeManager->setActiveTheme($activeThemeId);
$activeThemeId = $themeManager->activeThemeId();
$themes = $themeManager->discoverThemes();
$selectedTheme = $themeManager->sanitizeThemeId((string) ($_GET['theme'] ?? $themeManager->activeThemeId()));
if ($selectedTheme === '' || !$themeManager->themeExists($selectedTheme)) {
    $selectedTheme = $themeManager->activeThemeId();
}

$editableFiles = $themeManager->editableFiles($selectedTheme);
$filePaths = array_column($editableFiles, 'path');
if (!in_array($selectedFile, $filePaths, true)) {
    $selectedFile = in_array('theme.json', $filePaths, true) ? 'theme.json' : (string) ($filePaths[0] ?? '');
}

$fileContent = '';
$selectedFileMeta = null;
foreach ($editableFiles as $fileMeta) {
    if (($fileMeta['path'] ?? '') === $selectedFile) {
        $selectedFileMeta = $fileMeta;
        break;
    }
}
if ($selectedFile !== '') {
    try {
        $fileContent = $themeManager->readEditableFile($selectedTheme, $selectedFile);
    } catch (Throwable $e) {
        $fileContent = '';
        flash('error', safeErrorMessage($e));
    }
}

$selectedValidation = $themeManager->validateTheme($selectedTheme, true);
$previewThemeId = (string) ($_SESSION['_theme_preview_id'] ?? '');
$zipAvailable = class_exists(ZipArchive::class);
$totalThemes = count($themes);
$brokenThemes = count(array_filter($themes, static fn (array $theme): bool => empty($theme['ok'])));
$readyThemes = max(0, $totalThemes - $brokenThemes);
$selectedThemeSummary = null;
foreach ($themes as $theme) {
    if ((string) ($theme['id'] ?? '') === $selectedTheme) {
        $selectedThemeSummary = $theme;
        break;
    }
}
$selectedThemeSummary = $selectedThemeSummary ?: [
    'id' => $selectedTheme,
    'name' => $selectedTheme,
    'version' => '',
    'author' => '',
    'description' => '',
    'preview_url' => '',
    'ok' => $selectedValidation['ok'],
];
$selectedManifest = $selectedValidation['manifest'];
$countTemplateEntries = static function (mixed $templates) use (&$countTemplateEntries): int {
    if (!is_array($templates)) {
        return 0;
    }

    $count = 0;
    foreach ($templates as $value) {
        $count += is_array($value) ? $countTemplateEntries($value) : 1;
    }

    return $count;
};
$selectedTemplateCount = $countTemplateEntries($selectedManifest['templates'] ?? []);
$selectedCssCount = is_array($selectedManifest['assets']['css'] ?? null) ? count($selectedManifest['assets']['css']) : 0;
$selectedJsCount = is_array($selectedManifest['assets']['js'] ?? null) ? count($selectedManifest['assets']['js']) : 0;
$selectedIssueCount = count($selectedValidation['errors']) + count($selectedValidation['warnings']);

require_once __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= asset_url('admin/assets/themes-page.css', $baseUri) ?>">

<div class="theme-center">
    <header class="theme-center-top">
        <div>
            <span class="theme-eyebrow"><i class="bi bi-brush"></i> Tema Merkezi</span>
            <h2 class="theme-center-title">Public tema kütüphanesi</h2>
            <p class="theme-center-copy">Tema paketlerini buradan yükle, doğrula, önizle, aktif et ve düzenle. DLE temaları geldiğinde dönüşüm ve dosya edit akışı aynı merkezden yönetilecek.</p>
        </div>
        <div class="theme-commandbar">
            <details class="theme-action-popover">
                <summary class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-plus-lg"></i> Yeni Tema</summary>
                <div class="theme-action-menu">
                    <form method="post" action="themes.php" class="theme-mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_theme_action" value="duplicate">
                        <input type="hidden" name="source_theme" value="<?= htmlspecialchars($themeManager->defaultThemeId()) ?>">
                        <label class="ui-admin-form-label" for="newThemeId">Tema ID</label>
                        <input class="ui-admin-form-control" id="newThemeId" name="new_theme_id" placeholder="modern_dark" pattern="[a-z0-9_-]+" required>
                        <label class="ui-admin-form-label" for="newThemeName">Tema adı</label>
                        <input class="ui-admin-form-control" id="newThemeName" name="new_theme_name" placeholder="Modern Dark" required>
                        <button class="ui-admin-btn ui-admin-btn-primary" type="submit"><i class="bi bi-folder-plus"></i> Defaulttan Oluştur</button>
                    </form>
                </div>
            </details>
            <details class="theme-action-popover">
                <summary class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-upload"></i> ZIP Yükle</summary>
                <div class="theme-action-menu">
                    <form method="post" action="themes.php" enctype="multipart/form-data" class="theme-mini-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_theme_action" value="upload_zip">
                        <p class="theme-muted">ZIP içinde <code>theme.json</code> olmalı. PHP ve çalıştırılabilir dosyalar reddedilir.</p>
                        <input class="ui-admin-form-control" type="file" name="theme_zip" accept=".zip" <?= $zipAvailable ? '' : 'disabled' ?> required>
                        <button class="ui-admin-btn ui-admin-btn-primary" type="submit" <?= $zipAvailable ? '' : 'disabled' ?>><i class="bi bi-upload"></i> Paketi Yükle</button>
                    </form>
                </div>
            </details>
            <?php if ($previewThemeId !== ''): ?>
            <form method="post" action="themes.php" class="ui-admin-inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_theme_action" value="stop_preview">
                <input type="hidden" name="theme_id" value="<?= htmlspecialchars($selectedTheme) ?>">
                <button class="ui-admin-btn ui-admin-btn-outline" type="submit"><i class="bi bi-x-circle"></i> Önizlemeyi Kapat</button>
            </form>
            <?php endif; ?>
        </div>
    </header>

    <section class="theme-metrics" aria-label="Tema özeti">
        <div class="theme-metric"><span>Toplam Tema</span><strong><?= number_format($totalThemes, 0, ',', '.') ?></strong></div>
        <div class="theme-metric"><span>Aktif Tema</span><strong><?= htmlspecialchars($activeThemeId) ?></strong></div>
        <div class="theme-metric"><span>Hazır Paket</span><strong><?= number_format($readyThemes, 0, ',', '.') ?></strong></div>
        <div class="theme-metric"><span>Sorunlu Paket</span><strong><?= number_format($brokenThemes, 0, ',', '.') ?></strong></div>
    </section>

        <div class="ui-admin-tabs-container ui-panel">
        <?php $mainTab = (isset($_GET['file']) || isset($_GET['theme']) || ($_SERVER['REQUEST_METHOD'] === 'POST')) ? 'selected' : 'library'; ?>
        <div class="ui-admin-tabs ui-admin-tabs-spaced">
            <button type="button" class="ui-admin-tab <?= $mainTab === 'library' ? 'is-active' : '' ?>" data-tab="main-tab-library"><i class="bi bi-collection"></i> Tema Kütüphanesi</button>
            <button type="button" class="ui-admin-tab <?= $mainTab === 'selected' ? 'is-active' : '' ?>" data-tab="main-tab-selected"><i class="bi bi-brush"></i> Seçili Tema</button>
        </div>

        <div class="theme-workspace">
            <div id="main-tab-library" class="ui-admin-tab-content <?= $mainTab === 'library' ? 'is-active' : '' ?> ui-section">
                <section class="theme-panel">
                    <div class="theme-panel-head">
                        <div>
                            <h3>Tema Kütüphanesi</h3>
                            <span class="theme-muted">Yüklü public tema paketleri</span>
                        </div>
                        <label class="theme-search" for="themeSearch">
                            <i class="bi bi-search"></i>
                            <input class="ui-admin-form-control" id="themeSearch" type="search" placeholder="Tema ara..." data-theme-search>
                        </label>
                    </div>
                    <div class="theme-panel-body">
                        <div class="theme-library-grid" data-theme-library>
                            <?php foreach ($themes as $theme): ?>
                                <?php
                                $themeId = (string) ($theme['id'] ?? '');
                                $themeName = (string) ($theme['name'] ?? $themeId);
                                $themeSearchText = $themeId . ' ' . $themeName . ' ' . (string) ($theme['author'] ?? '');
                                ?>
                                <article class="theme-card<?= $themeId === $selectedTheme ? ' is-selected' : '' ?>" data-theme-card data-theme-search-text="<?= htmlspecialchars($themeSearchText) ?>">
                                    <a class="theme-card-preview" href="themes.php?theme=<?= urlencode($themeId) ?>" aria-label="<?= htmlspecialchars($themeName) ?> temasını aç">
                                        <?php if (($theme['preview_url'] ?? '') !== ''): ?>
                                            <img src="<?= htmlspecialchars((string) $theme['preview_url']) ?>" alt="" width="320" height="180">
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_strtoupper(mb_substr($themeName, 0, 1))) ?>
                                        <?php endif; ?>
                                    </a>
                                    <div class="theme-card-body">
                                        <div class="theme-card-title">
                                            <div>
                                                <h3><?= htmlspecialchars($themeName) ?></h3>
                                                <div class="theme-card-meta"><?= htmlspecialchars($themeId) ?> · v<?= htmlspecialchars((string) ($theme['version'] ?? '')) ?></div>
                                            </div>
                                            <?php if ($themeId === $activeThemeId): ?>
                                                <span class="theme-badge theme-badge-active">Aktif</span>
                                            <?php elseif (!empty($theme['ok'])): ?>
                                                <span class="theme-badge theme-badge-ok">Hazır</span>
                                            <?php else: ?>
                                                <span class="theme-badge theme-badge-warn">Sorunlu</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (($theme['description'] ?? '') !== ''): ?>
                                            <p class="theme-muted"><?= htmlspecialchars((string) $theme['description']) ?></p>
                                        <?php endif; ?>
                                        <div class="theme-actions">
                                            <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="themes.php?theme=<?= urlencode($themeId) ?>"><i class="bi bi-sliders"></i> Yönet</a>
                                            <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars(($baseUri ?? '') . '/index.php?theme_preview=' . urlencode($themeId)) ?>" target="_blank"><i class="bi bi-eye"></i> Önizle</a>
                                            <?php if ($themeId !== $activeThemeId): ?>
                                            <form method="post" action="themes.php" class="ui-admin-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_theme_action" value="activate">
                                                <input type="hidden" name="theme_id" value="<?= htmlspecialchars($themeId) ?>">
                                                <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" type="submit" <?= !empty($theme['ok']) ? '' : 'disabled' ?>><i class="bi bi-check2-circle"></i> Aktif Et</button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>

            <div id="main-tab-selected" class="ui-admin-tab-content <?= $mainTab === 'selected' ? 'is-active' : '' ?> ui-section">
                <section class="theme-panel">
                    <div class="theme-panel-head">
                        <div>
                            <h3>Seçili Tema</h3>
                            <span class="theme-muted"><?= htmlspecialchars($selectedTheme) ?> paketi</span>
                        </div>
                        <?php if ($selectedValidation['ok']): ?>
                            <span class="theme-badge theme-badge-ok"><i class="bi bi-check-circle"></i> Doğrulandı</span>
                        <?php else: ?>
                            <span class="theme-badge theme-badge-warn"><i class="bi bi-exclamation-triangle"></i> İnceleme Gerekli</span>
                        <?php endif; ?>
                    </div>
                    <?php $activeTab = isset($_GET['file']) ? 'editor' : 'summary'; ?>
                    <div class="ui-admin-tabs ui-admin-tabs-inset">
                        <button type="button" class="ui-admin-tab <?= $activeTab === 'summary' ? 'is-active' : '' ?>" data-tab="theme-tab-summary"><i class="bi bi-info-circle"></i> Özet ve Doğrulama</button>
                        <?php if (!empty($selectedManifest['settings_schema'])): ?>
                        <button type="button" class="ui-admin-tab" data-tab="theme-tab-settings"><i class="bi bi-gear"></i> Tema Ayarları</button>
                        <?php endif; ?>
                        <button type="button" class="ui-admin-tab <?= $activeTab === 'editor' ? 'is-active' : '' ?>" data-tab="theme-tab-editor"><i class="bi bi-code-slash"></i> Dosya Editörü</button>
                    </div>
                    <div class="theme-panel-body">
                        <div id="theme-tab-summary" class="ui-admin-tab-content <?= $activeTab === 'summary' ? 'is-active' : '' ?> ui-section">
                            <div class="theme-selected-summary">
                            <div class="theme-selected-preview">
                                <?php if (($selectedThemeSummary['preview_url'] ?? '') !== ''): ?>
                                    <img src="<?= htmlspecialchars((string) $selectedThemeSummary['preview_url']) ?>" alt="" width="320" height="180">
                                <?php else: ?>
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr((string) ($selectedThemeSummary['name'] ?? $selectedTheme), 0, 1))) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="theme-selected-title">
                                    <div>
                                        <h3><?= htmlspecialchars((string) ($selectedThemeSummary['name'] ?? $selectedTheme)) ?></h3>
                                        <div class="theme-muted">
                                            <?= htmlspecialchars($selectedTheme) ?>
                                            <?php if (($selectedThemeSummary['version'] ?? '') !== ''): ?> · v<?= htmlspecialchars((string) $selectedThemeSummary['version']) ?><?php endif; ?>
                                            <?php if (($selectedThemeSummary['author'] ?? '') !== ''): ?> · <?= htmlspecialchars((string) $selectedThemeSummary['author']) ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($selectedTheme === $activeThemeId): ?>
                                        <span class="theme-badge theme-badge-active">Aktif</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (($selectedThemeSummary['description'] ?? '') !== ''): ?>
                                    <p class="theme-muted"><?= htmlspecialchars((string) $selectedThemeSummary['description']) ?></p>
                                <?php endif; ?>
                                <div class="theme-actions">
                                    <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars(($baseUri ?? '') . '/index.php?theme_preview=' . urlencode($selectedTheme)) ?>" target="_blank"><i class="bi bi-eye"></i> Site Önizleme</a>
                                    <?php if ($selectedTheme !== $activeThemeId): ?>
                                    <form method="post" action="themes.php" class="ui-admin-inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_theme_action" value="activate">
                                        <input type="hidden" name="theme_id" value="<?= htmlspecialchars($selectedTheme) ?>">
                                        <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" type="submit" <?= $selectedValidation['ok'] ? '' : 'disabled' ?>><i class="bi bi-check2-circle"></i> Bu Temayı Aktif Et</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="theme-quick-stats">
                            <div class="theme-quick-stat"><span>Template</span><strong><?= number_format($selectedTemplateCount, 0, ',', '.') ?></strong></div>
                            <div class="theme-quick-stat"><span>CSS</span><strong><?= number_format($selectedCssCount, 0, ',', '.') ?></strong></div>
                            <div class="theme-quick-stat"><span>JS</span><strong><?= number_format($selectedJsCount, 0, ',', '.') ?></strong></div>
                            <div class="theme-quick-stat"><span>Uyarı</span><strong><?= number_format($selectedIssueCount, 0, ',', '.') ?></strong></div>
                        </div>

                        <div class="theme-section">
                            <div class="theme-section-title"><h4>Doğrulama Sonuçları</h4></div>
                            <ul class="theme-validation-list">
                                <?php foreach ($selectedValidation['errors'] as $issue): ?><li><strong>Hata:</strong> <?= htmlspecialchars($issue) ?></li><?php endforeach; ?>
                                <?php foreach ($selectedValidation['warnings'] as $issue): ?><li><strong>Uyarı:</strong> <?= htmlspecialchars($issue) ?></li><?php endforeach; ?>
                                <?php if ($selectedValidation['errors'] === [] && $selectedValidation['warnings'] === []): ?><li>Manifest, template ve asset kontrolü temiz.</li><?php endif; ?>
                            </ul>
                        </div>
                        </div>

                        <?php if (!empty($selectedManifest['settings_schema'])): ?>
                        <div id="theme-tab-settings" class="ui-admin-tab-content ui-section">
                            <div class="theme-section ui-admin-theme-section-flat">
                                <div class="theme-section-title ui-admin-theme-title-spaced">
                                    <h4>Tema Ayarları</h4>
                                </div>
                                <form method="post" action="themes.php?theme=<?= urlencode($selectedTheme) ?>" class="theme-mini-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_theme_action" value="save_settings">
                                    <input type="hidden" name="theme_id" value="<?= htmlspecialchars($selectedTheme) ?>">
                                    <?php
                                    $themeSettingsKey = 'theme_' . $selectedTheme . '_settings';
                                    $currentThemeSettings = json_decode((string) ($settings[$themeSettingsKey] ?? '{}'), true);
                                    if (!is_array($currentThemeSettings)) $currentThemeSettings = [];
                                    foreach ($selectedManifest['settings_schema'] as $field):
                                        $fieldName = $field['name'] ?? '';
                                        $fieldType = $field['type'] ?? 'text';
                                        $fieldLabel = $field['label'] ?? $fieldName;
                                        $fieldDesc = $field['description'] ?? '';
                                        $fieldValue = $currentThemeSettings[$fieldName] ?? ($field['default'] ?? '');
                                        if ($fieldName === '') continue;
                                    ?>
                                    <div class="ui-admin-field-spaced">
                                        <label class="ui-admin-form-label"><?= htmlspecialchars($fieldLabel) ?></label>
                                        <?php if ($fieldType === 'textarea'): ?>
                                            <textarea class="ui-admin-form-control" name="theme_settings[<?= htmlspecialchars($fieldName) ?>]"><?= htmlspecialchars((string)$fieldValue) ?></textarea>
                                        <?php else: ?>
                                            <input class="ui-admin-form-control" type="<?= htmlspecialchars($fieldType) ?>" name="theme_settings[<?= htmlspecialchars($fieldName) ?>]" value="<?= htmlspecialchars((string)$fieldValue) ?>">
                                        <?php endif; ?>
                                        <?php if ($fieldDesc !== ''): ?><small class="theme-muted ui-admin-theme-muted-block"><?= htmlspecialchars($fieldDesc) ?></small><?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="settings-savebar ui-admin-savebar-spaced">
                                        <button class="ui-admin-btn ui-admin-btn-primary" type="submit"><i class="bi bi-save"></i> Ayarları Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div id="theme-tab-editor" class="ui-admin-tab-content <?= $activeTab === 'editor' ? 'is-active' : '' ?> ui-section">
                            <div class="theme-section ui-admin-theme-section-flush">
                                <div class="theme-section-title ui-admin-theme-title-spaced">
                                    <h4>Dosya Editörü</h4>
                                    <span class="theme-badge theme-badge-neutral"><?= count($editableFiles) ?> dosya</span>
                            </div>
                            <?php if ($selectedFile === ''): ?>
                                <p class="theme-muted">Bu tema içinde düzenlenebilir dosya bulunamadı.</p>
                            <?php else: ?>
                            <div class="theme-editor-grid" data-theme-editor>
                                <aside class="theme-file-list">
                                    <?php foreach ($editableFiles as $file): ?>
                                        <a class="theme-file-link <?= $file['path'] === $selectedFile ? 'active' : '' ?>" href="themes.php?theme=<?= urlencode($selectedTheme) ?>&file=<?= urlencode($file['path']) ?>">
                                            <?= htmlspecialchars($file['path']) ?><br><small><?= number_format((int) $file['size'], 0, ',', '.') ?> byte</small>
                                            <?php if (!empty($file['large'])): ?><br><small class="theme-badge theme-badge-warn">Büyük dosya</small><?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </aside>
                                <form method="post" action="themes.php?theme=<?= urlencode($selectedTheme) ?>&file=<?= urlencode($selectedFile) ?>" class="theme-mini-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_theme_action" value="save_file">
                                    <input type="hidden" name="theme_id" value="<?= htmlspecialchars($selectedTheme) ?>">
                                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($selectedFile) ?>">
                                    <?php if (!empty($selectedFileMeta['size_warning'])): ?>
                                        <div class="theme-validation-list">
                                            <li><?= htmlspecialchars((string) $selectedFileMeta['size_warning']) ?> Büyük CSS/JS dosyalarında küçük ve kontrollü değişiklik yapın.</li>
                                        </div>
                                    <?php endif; ?>
                                    <div class="theme-editor-toolbar">
                                        <label class="theme-search theme-editor-search" for="themeEditorSearch">
                                            <i class="bi bi-search"></i>
                                            <input class="ui-admin-form-control" id="themeEditorSearch" type="search" placeholder="Dosyada ara..." data-editor-search>
                                        </label>
                                        <span class="theme-badge theme-badge-neutral" data-editor-lines>0 satır</span>
                                        <span class="theme-badge theme-badge-neutral" data-editor-cursor>Satır 1</span>
                                        <a class="ui-admin-btn ui-admin-btn-secondary ui-admin-btn-sm" href="themes.php?theme=<?= urlencode($selectedTheme) ?>&file=<?= urlencode($selectedFile) ?>&tab=summary">
                                            <i class="bi bi-shield-check"></i> Doğrula
                                        </a>
                                    </div>
                                    <div class="theme-editor-search-status theme-muted" data-editor-search-status hidden></div>
                                    <textarea class="ui-admin-form-control theme-code-editor" name="file_content" spellcheck="false" data-code-editor><?= htmlspecialchars($fileContent) ?></textarea>
                                    <div class="settings-savebar">
                                        <span><strong><?= htmlspecialchars($selectedFile) ?></strong> kaydedilecek. TPL dosyaları PHP çalıştırmaz.</span>
                                        <button class="ui-admin-btn ui-admin-btn-primary" type="submit"><i class="bi bi-save"></i> Kaydet</button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_url('admin/assets/themes-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
