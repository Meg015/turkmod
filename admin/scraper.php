<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

adminRequirePermission('scraper.view', 'İçerik botunu görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'İçerik Botu';
$sites = getScraperSites($pdo);
$mappings = getScraperMappings($pdo);
$jobs = getScraperJobs($pdo, null, 10);
$imports = getScraperImports($pdo, null, null, 50);
$stats = getScraperStats($pdo);
$botSettings = getScraperBotSettings($pdo);
$botSettingEnabled = static fn(string $key, mixed $default = '0'): bool => scraperBoolSetting($botSettings, $key, $default);
$categories = getAdminCategoryOptions($pdo);
$categoryHasChildren = [];
foreach ($categories as $category) {
    $parentId = $category['parent_id'] === null ? 0 : (int) $category['parent_id'];
    if ($parentId > 0) {
        $categoryHasChildren[$parentId] = true;
    }
}
$categoryOptionsHtml = static function (string $placeholder, int $selectedId = 0) use ($categories, $categoryHasChildren): string {
    $html = '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    $optgroupOpen = false;

    foreach ($categories as $category) {
        $id = (int) ($category['id'] ?? 0);
        $depth = max(0, (int) ($category['depth'] ?? 0));
        $name = trim((string) ($category['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            continue;
        }

        if ($depth === 0 && !empty($categoryHasChildren[$id])) {
            if ($optgroupOpen) {
                $html .= '</optgroup>';
            }
            $html .= '<optgroup label="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">';
            $optgroupOpen = true;
            continue;
        }

        if ($depth === 0 && $optgroupOpen) {
            $html .= '</optgroup>';
            $optgroupOpen = false;
        }

        $label = $depth > 1 ? str_repeat('  ', $depth - 1) . '- ' . $name : $name;
        $html .= '<option value="' . $id . '"' . ($id === $selectedId ? ' selected' : '') . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    if ($optgroupOpen) {
        $html .= '</optgroup>';
    }

    return $html;
};
$scraperMappingGameMeta = static function (string $categoryName): array {
    $normalized = strtolower(strtr($categoryName, [
        'Ç' => 'c', 'ç' => 'c',
        'Ğ' => 'g', 'ğ' => 'g',
        'İ' => 'i', 'ı' => 'i',
        'Ö' => 'o', 'ö' => 'o',
        'Ş' => 's', 'ş' => 's',
        'Ü' => 'u', 'ü' => 'u',
    ]));
    $normalized = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $normalized));

    if (str_contains($normalized, 'euro truck simulator 2') || preg_match('/\bets\s*2\b/', $normalized)) {
        return ['code' => 'ETS2', 'tone' => 'ets2', 'icon' => 'bi-truck-front-fill'];
    }
    if (str_contains($normalized, 'american truck simulator') || preg_match('/\bats\b/', $normalized)) {
        return ['code' => 'ATS', 'tone' => 'ats', 'icon' => 'bi-truck-front-fill'];
    }
    if (str_contains($normalized, 'fs 25') || str_contains($normalized, 'fs25') || str_contains($normalized, 'farming simulator 25')) {
        return ['code' => 'FS25', 'tone' => 'fs25', 'icon' => 'bi-gear-wide-connected'];
    }
    if (str_contains($normalized, 'beamng')) {
        return ['code' => 'BEAMNG', 'tone' => 'beamng', 'icon' => 'bi-car-front-fill'];
    }
    if (str_contains($normalized, 'city car driving')) {
        return ['code' => 'CCD', 'tone' => 'ccd', 'icon' => 'bi-car-front-fill'];
    }
    if (str_contains($normalized, 'snowrunner')) {
        return ['code' => 'SNOW', 'tone' => 'snowrunner', 'icon' => 'bi-truck'];
    }
    if (str_contains($normalized, 'mudrunner')) {
        return ['code' => 'MUD', 'tone' => 'mudrunner', 'icon' => 'bi-truck'];
    }
    if (str_contains($normalized, 'spintires')) {
        return ['code' => 'SPIN', 'tone' => 'spintires', 'icon' => 'bi-truck'];
    }

    $words = array_values(array_filter(explode(' ', $normalized), static fn (string $word): bool => strlen($word) > 1));
    $code = strtoupper(substr(implode('', array_map(static fn (string $word): string => $word[0], array_slice($words, 0, 3))), 0, 5));

    return ['code' => $code !== '' ? $code : 'GAME', 'tone' => 'generic', 'icon' => 'bi-controller'];
};
$scraperMappingGroupInfo = static function (array $mapping) use ($scraperMappingGameMeta): array {
    $siteId = (int) ($mapping['bot_site_id'] ?? 0);
    $siteName = trim((string) ($mapping['site_name'] ?? ''));
    $parentId = (int) ($mapping['local_parent_category_id'] ?? 0);
    $localId = (int) ($mapping['local_category_id'] ?? 0);
    $parentName = trim((string) ($mapping['local_parent_category_name'] ?? ''));
    $localName = trim((string) ($mapping['local_category_name'] ?? ''));
    $groupId = $parentId > 0 ? $parentId : $localId;
    $groupName = $parentName !== '' ? $parentName : ($localName !== '' ? $localName : 'Yerel kategori yok');
    $game = $scraperMappingGameMeta($groupName);

    return [
        'key' => $siteId . ':' . $groupId . ':' . $groupName,
        'site' => $siteName !== '' ? $siteName : 'Bilinmeyen site',
        'category' => $groupName,
        'game_code' => $game['code'],
        'game_tone' => $game['tone'],
        'game_icon' => $game['icon'],
    ];
};
$scraperMappingRemoteHtml = static function (array $mapping): string {
    $url = trim((string) ($mapping['remote_category_url'] ?? ''));
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
    $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
    $parts = $path === '' ? [] : array_values(array_filter(explode('/', $path)));
    $slug = $parts === [] ? $url : (string) end($parts);
    $label = ($host !== '' ? $host . ' / ' : '') . $slug;
    $prefix = trim((string) ($mapping['title_prefix'] ?? ''));

    $html = '<div class="scraper-url-cell">'
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="scraper-url-title">'
        . '<i class="bi bi-box-arrow-up-right"></i> '
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</a>'
        . '<span class="scraper-url-full">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</span>';
    if ($prefix !== '') {
        $html .= '<span class="admin-badge admin-badge-secondary scraper-prefix-badge"><i class="bi bi-type"></i> '
            . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
    $html .= '</div>';

    return $html;
};
$scraperMappingLocalHtml = static function (array $mapping): string {
    $parentName = trim((string) ($mapping['local_parent_category_name'] ?? ''));
    $localName = trim((string) ($mapping['local_category_name'] ?? 'Bilinmiyor'));

    $html = '<div class="scraper-local-category">';
    if ($parentName !== '') {
        $html .= '<span class="scraper-local-parent">' . htmlspecialchars($parentName, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $html .= '<span class="admin-badge admin-badge-info scraper-local-child">'
        . htmlspecialchars($localName !== '' ? $localName : 'Bilinmiyor', ENT_QUOTES, 'UTF-8')
        . '</span></div>';

    return $html;
};
$scraperMappingGroupCounts = [];
foreach ($mappings as $mapping) {
    $group = $scraperMappingGroupInfo($mapping);
    $scraperMappingGroupCounts[$group['key']] = ($scraperMappingGroupCounts[$group['key']] ?? 0) + 1;
}
$scraperDateInfo = static function (mixed $value): array {
    $raw = trim((string) $value);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return ['date' => '-', 'time' => '', 'full' => '-', 'iso' => '', 'label' => 'Tarih yok'];
    }

    try {
        $date = new DateTimeImmutable($raw);
    } catch (Throwable) {
        return ['date' => $raw, 'time' => '', 'full' => $raw, 'iso' => '', 'label' => $raw];
    }

    $today = new DateTimeImmutable('today');
    $dateOnly = $date->format('Y-m-d');
    $label = match ($dateOnly) {
        $today->format('Y-m-d') => 'Bugün',
        $today->modify('-1 day')->format('Y-m-d') => 'Dün',
        default => $date->format('d.m.Y'),
    };

    return [
        'date' => $date->format('d.m.Y'),
        'time' => $date->format('H:i'),
        'full' => $date->format('d.m.Y H:i'),
        'iso' => $date->format(DateTimeInterface::ATOM),
        'label' => $label,
    ];
};
$scraperUrlLabel = static function (string $url): string {
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
    $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
    $parts = $path === '' ? [] : array_values(array_filter(explode('/', $path)));
    $slug = $parts === [] ? $url : (string) end($parts);

    return ($host !== '' ? $host . ' / ' : '') . $slug;
};
$scraperImportStatusMeta = static function (string $status): array {
    return match ($status) {
        'imported' => ['class' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Yayınlandı'],
        'failed' => ['class' => 'danger', 'icon' => 'bi-x-circle-fill', 'label' => 'Hatalı'],
        'preview' => ['class' => 'warning', 'icon' => 'bi-eye-fill', 'label' => 'Önizleme'],
        'pending' => ['class' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Bekliyor'],
        'skipped' => ['class' => 'secondary', 'icon' => 'bi-skip-forward-fill', 'label' => 'Atlandı'],
        default => ['class' => 'secondary', 'icon' => 'bi-clock-history', 'label' => ucfirst($status !== '' ? $status : 'Bilinmiyor')],
    };
};
$botAuthors = usersGetList($pdo, '', '', 'active');
$botAuthorLabel = static function (array $author): string {
    $label = trim((string) ($author['username'] ?? $author['name'] ?? $author['email'] ?? ''));
    if ($label === '') {
        $label = 'Kullanıcı #' . (int) ($author['id'] ?? 0);
    }

    return $label;
};

require_once __DIR__ . '/header.php';
?>
<!-- Grid stretch ve boşluk sorunlarını tamamen çözmek için tek bir sarmalayıcı -->
<div class="scraper-full-layout">
    <div class="bot-tabs-wrapper">
        <a class="bot-tab scraper-tab-link active" data-tab="dashboard"><i class="bi bi-speedometer2"></i>Özet</a>
        <a class="bot-tab scraper-tab-link" data-tab="sites"><i class="bi bi-globe"></i>Siteler</a>
        <a class="bot-tab scraper-tab-link" data-tab="mappings"><i class="bi bi-diagram-2"></i>Eşlemeler</a>
        <a class="bot-tab scraper-tab-link" data-tab="scrape"><i class="bi bi-cloud-download"></i>Toplu İçerik Çek</a>
        <a class="bot-tab scraper-tab-link" data-tab="imports"><i class="bi bi-journal-text"></i>Bot Logları</a>
        <a class="bot-tab scraper-tab-link" data-tab="settings"><i class="bi bi-gear"></i>Bot Ayarları</a>
    </div>

<?= adminRenderPanelOpen([
    'tag' => 'div',
    'class' => 'scraper-tab-pane active',
    'attrs' => ['id' => 'tab-dashboard'],
    'title' => 'Bot İstatistikleri',
]) ?>
        <?= adminRenderStatCards([
            ['tone' => 'info', 'icon' => 'bi-globe', 'label' => 'Toplam Site', 'value' => number_format((int) $stats['sites'], 0, ',', '.')],
            ['tone' => 'warning', 'icon' => 'bi-cloud-arrow-down', 'label' => 'Bekleyen İçerik', 'value' => number_format((int) $stats['pending'], 0, ',', '.')],
            ['tone' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Yayınlanan', 'value' => number_format((int) $stats['imported'], 0, ',', '.')],
        ], ['class' => 'scraper-summary', 'aria_label' => 'Bot istatistikleri']) ?>
        
        <h4 class="mt-4 mb-3 scraper-section-title">Son Bot Görevleri</h4>
        <?= adminRenderTableOpen(['ID', 'Site', 'Durum', "URL'ler", 'Tarih'], [
            'class' => 'admin-table',
            'wrap_class' => 'table-wrapper ui-table-wrap ui-surface',
            'label' => 'Son bot görevleri',
        ]) ?>
                    <?php if (empty($jobs)): ?>
                    <?= adminRenderTableEmptyRow(5, ['icon' => 'bi-inbox', 'tone' => 'info', 'title' => 'Henüz görev bulunmuyor.', 'description' => 'Bot görevi oluştuğunda burada listelenecek.']) ?>
                    <?php else: foreach ($jobs as $j): ?>
                    <tr>
                        <td>#<?= $j['id'] ?></td>
                        <td><?= htmlspecialchars($j['site_name'] ?? 'Bilinmeyen') ?></td>
                        <td>
                            <span class="admin-badge admin-badge-<?= $j['status'] === 'completed' ? 'success' : ($j['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                <?= htmlspecialchars(ucfirst($j['status'])) ?>
                            </span>
                        </td>
                        <td><?= $j['processed_urls'] ?> / <?= $j['total_urls'] ?> (Başarılı: <?= $j['imported_urls'] ?>)</td>
                        <td><?= date('d.m.Y H:i', strtotime($j['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
        <?= adminRenderTableClose() ?>
<?= adminRenderPanelClose('div') ?>

<div class="scraper-tab-pane" id="tab-sites">
    <?= adminRenderPanelOpen([
        'tag' => 'div',
        'class' => 'mb-4',
        'header_html' => '<span id="siteFormTitle">Yeni Site Ekle</span>',
    ]) ?>
            <form id="siteForm">
                <?= csrf_field() ?>
                <input type="hidden" id="site_id" name="site_id" value="">
                <div class="site-subtabs" role="tablist">
                    <button type="button" class="site-subtab-link active" data-site-tab="basic"><i class="bi bi-globe"></i> Temel</button>
                    <button type="button" class="site-subtab-link" data-site-tab="selectors"><i class="bi bi-crosshair"></i> Seçiciler</button>
                    <button type="button" class="site-subtab-link" data-site-tab="settings"><i class="bi bi-sliders"></i> Ayarlar</button>
                    <button type="button" class="site-subtab-link" data-site-tab="customize"><i class="bi bi-magic"></i> Özelleştir</button>
                </div>
                <div class="row g-3">
                    <div class="col-12 site-subtab-pane active" id="site-tab-basic">
                        <?= adminRenderSettingsCardOpen([
                            'icon' => 'bi-info-circle',
                            'title' => 'Temel Bilgiler',
                            'description' => 'Site adı, URL ve genel ayarlar',
                        ]) ?>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="ui-admin-form-label">Site Adı</label>
                                    <input type="text" id="site_name" name="name" class="ui-admin-form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="ui-admin-form-label">Base URL</label>
                                    <input type="url" id="site_base_url" name="base_url" class="ui-admin-form-control" placeholder="https://example.com" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="ui-admin-form-label">Durum</label>
                                    <select id="site_status" name="status" class="ui-admin-form-select">
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Pasif</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="ui-admin-form-label">Açıklama</label>
                                    <input type="text" id="site_description" name="description" class="ui-admin-form-control">
                                </div>
                            </div>
                        <?= adminRenderSettingsCardClose() ?>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-selectors">
                        <?= adminRenderSettingsCardOpen([
                            'icon' => 'bi-crosshair',
                            'title' => 'CSS Seçiciler (Selectors)',
                            'description' => "İçerik çekmek için kullanılacak CSS selector'ları",
                        ]) ?>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="ui-admin-form-label">Konu Listesi (Kapsayıcı)</label><input type="text" id="sel_topic_list" name="sel_topic_list" class="ui-admin-form-control" placeholder=".article-list .item"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Konu Linki</label><input type="text" id="sel_topic_link" name="sel_topic_link" class="ui-admin-form-control" placeholder="a.title"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Başlığı</label><input type="text" id="sel_title" name="sel_title" class="ui-admin-form-control" placeholder="h1.post-title"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Gövdesi</label><input type="text" id="sel_content" name="sel_content" class="ui-admin-form-control" placeholder=".post-content"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Resimler (Galeri)</label><input type="text" id="sel_images" name="sel_images" class="ui-admin-form-control" placeholder=".post-gallery img"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İndirme Linkleri</label><input type="text" id="sel_download_links" name="sel_download_links" class="ui-admin-form-control" placeholder="a.download-btn"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Sayfalama Sonraki Linki</label><input type="text" id="sel_pagination" name="sel_pagination" class="ui-admin-form-control" placeholder=".pagination a.next"></div>
                        </div>
                        <?= adminRenderSettingsCardClose() ?>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-settings">
                        <?= adminRenderSettingsCardOpen([
                            'icon' => 'bi-sliders',
                            'title' => 'Siteye Özel Ayarlar',
                            'description' => 'Resim, dil ve çeviri ayarları',
                        ]) ?>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="ui-admin-form-label">Max Resim İndirme</label><input type="number" id="max_images" name="max_images" class="ui-admin-form-control" value="5"></div>
                                <div class="col-md-4"><label class="ui-admin-form-label">Kaynak Dil</label><input type="text" id="source_lang" name="source_lang" class="ui-admin-form-control" value="EN" placeholder="EN"></div>
                                <div class="col-md-4"><label class="ui-admin-form-label">Hedef Dil</label><input type="text" id="target_lang" name="target_lang" class="ui-admin-form-control" value="TR" placeholder="TR"></div>
                                <div class="col-12"><label class="ui-admin-switch"><input type="checkbox" id="translate" name="translate" value="1"><span class="ui-admin-switch-label">Bu site için çeviriyi aktifleştir</span></label></div>
                            </div>
                        <?= adminRenderSettingsCardClose() ?>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-customize">
                        <?= adminRenderSettingsCardOpen([
                            'icon' => 'bi-magic',
                            'title' => 'Özelleştirme & Otomasyonlar',
                            'description' => 'Bul-değiştir kuralları, otomatik tespit ve özel ayarlar',
                        ]) ?>
                        <div id="replaceRulesContainer" class="replace-rule-list"></div>
                        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline scraper-button-offset" data-scraper-action="add-replace-rule"><i class="bi bi-plus-circle"></i> Bul Değiştir Kuralı Ekle</button>
                        <hr>
                        <div class="row g-4 scraper-advanced-fields">
                            <div class="col-md-6"><label class="ui-admin-form-label">Başlık ablonu</label><input type="text" id="title_template" name="title_template" class="ui-admin-form-control" placeholder="{title} Türkçe Mod"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">HTML Selector Temizle</label><input type="text" id="remove_selectors" name="remove_selectors" class="ui-admin-form-control" placeholder=".ads, .share-buttons"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Başına Ekle</label><textarea id="content_prepend" name="content_prepend" class="ui-admin-form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Sonuna Ekle</label><textarea id="content_append" name="content_append" class="ui-admin-form-control" rows="3"></textarea></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Bu Metinden Öncesini Sil</label><input type="text" id="trim_before_text" name="trim_before_text" class="ui-admin-form-control"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Bu Metinden Sonrasını Sil</label><input type="text" id="trim_after_text" name="trim_after_text" class="ui-admin-form-control"></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">Varsayılan Kategori</label><select id="site_default_category_id" name="site_default_category_id" class="ui-admin-form-select"><?= $categoryOptionsHtml('Seçilmedi') ?></select></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">Önizleme Aktarım Durumu</label><select id="site_default_status" name="site_default_status" class="ui-admin-form-select"><option value="">Genel yayın ayarını kullan</option><option value="draft">Taslak</option><option value="published">Yayında</option></select></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">Varsayılan Yazar</label><select id="site_default_author_id" name="site_default_author_id" class="ui-admin-form-select"><option value="">Genel ayarı kullan</option><?php foreach ($botAuthors as $author): ?><option value="<?= (int)$author['id'] ?>"><?= htmlspecialchars($botAuthorLabel($author), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">Görsel URL İçeriyorsa Atla</label><input type="text" id="skip_image_contains" name="skip_image_contains" class="ui-admin-form-control" placeholder="logo, avatar"></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">İzinli Görsel Domainleri</label><input type="text" id="allowed_image_domains" name="allowed_image_domains" class="ui-admin-form-control" placeholder="cdn.site.com"></div>
                            <div class="col-md-4"><label class="ui-admin-form-label">Min Görsel Genişliği</label><input type="number" id="min_image_width" name="min_image_width" class="ui-admin-form-control" value="0" min="0"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Atlanacak Download Domainleri</label><input type="text" id="skip_download_domains" name="skip_download_domains" class="ui-admin-form-control" placeholder="badhost.com, ads.com"></div>
                            <div class="col-md-6">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" id="detect_author_enabled" name="detect_author_enabled" value="1" checked>
                                    <span class="ui-admin-switch-label">Mod yapımcısını açıklamadan otomatik tespit et</span>
                                </label>
                            </div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Mod Yapımcısı Anahtar Kelimeleri</label><input type="text" id="detect_author_labels" name="detect_author_labels" class="ui-admin-form-control" value="author,authors,credit,credits" placeholder="author, authors, credit, credits"></div>
                            <div class="col-md-6">
                                <label class="ui-admin-switch">
                                    <input type="checkbox" id="detect_version_enabled" name="detect_version_enabled" value="1" checked>
                                    <span class="ui-admin-switch-label">Gerekli oyun sürümünü açıklamadan otomatik tespit et</span>
                                </label>
                            </div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Oyun Sürümü Regex Deseni</label><input type="text" id="detect_version_pattern" name="detect_version_pattern" class="ui-admin-form-control" value="1\.(?:[3-9]\d|[1-9]\d{2,})" placeholder="1\.(?:[3-9]\d|[1-9]\d{2,})"></div>
                        </div>
                        <h6 class="mt-3 mb-2 scraper-section-title-sm">Silinecek Metinler</h6>
                        <div id="removeTextsContainer" class="replace-rule-list"></div>
                        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline mt-2" data-scraper-action="add-remove-text"><i class="bi bi-plus-circle"></i> Silinecek Metin Ekle</button>
                        <h6 class="mt-3 mb-2 scraper-section-title-sm">Otomatik Etiketler</h6>
                        <div id="autoTagsContainer" class="replace-rule-list"></div>
                        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline mt-2" data-scraper-action="add-auto-tag"><i class="bi bi-plus-circle"></i> Etiket Kuralı Ekle</button>
                        <h6 class="mt-3 mb-2 scraper-section-title-sm">Download Link Düzeltmeleri</h6>
                        <div id="downloadLinkRulesContainer" class="replace-rule-list"></div>
                        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline mt-2" data-scraper-action="add-download-link-rule"><i class="bi bi-plus-circle"></i> Download Kuralı Ekle</button>
                        </div>
                    </div>
                </div>
                <div class="scraper-form-actions">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Kaydet</button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-scraper-action="reset-site-form"><i class="bi bi-x-circle"></i> İptal / Temizle</button>
                    <button type="button" id="btnTestConn" class="ui-admin-btn ui-admin-btn-outline ms-auto" data-scraper-action="test-connection"><i class="bi bi-wifi"></i> Bağlantı Test</button>
                </div>
            </form>
    <?= adminRenderPanelClose('div') ?>
    
    <?= adminRenderPanelOpen(['tag' => 'div', 'title' => 'Kayıtlı Siteler']) ?>
            <?= adminRenderTableOpen([
                'ID',
                'Site Adı',
                'Base URL',
                'İçerik/Eşleme',
                'Durum',
                ['label' => 'İşlemler', 'class' => 'text-end'],
            ], [
                'class' => 'admin-table',
                'wrap_class' => 'table-wrapper ui-table-wrap ui-surface',
                'label' => 'Kayıtlı siteler',
            ]) ?>
                        <?php foreach ($sites as $s): ?>
                        <tr>
                            <td>#<?= $s['id'] ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><a href="<?= htmlspecialchars($s['base_url']) ?>" target="_blank"><?= htmlspecialchars($s['base_url']) ?></a></td>
                            <td><span class="admin-badge admin-badge-info"><?= $s['import_count'] ?> İçerik</span> <span class="admin-badge admin-badge-secondary"><?= $s['mapping_count'] ?> Eşleme</span></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= $s['status'] === 'active' ? 'success' : 'danger' ?>"><?= $s['status'] === 'active' ? 'Aktif' : 'Pasif' ?></span>
                            </td>
                            <td class="text-end">
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-scraper-action="edit-site" data-site-id="<?= (int) $s['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="delete-site" data-site-id="<?= (int) $s['id'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sites)): ?>
                        <?= adminRenderTableEmptyRow(6, ['icon' => 'bi-globe', 'tone' => 'info', 'title' => 'Kayıtlı site bulunamadı.', 'description' => 'Yeni site eklediğinizde burada görünecek.']) ?>
                        <?php endif; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
</div>

<div class="scraper-tab-pane" id="tab-mappings">
    <?= adminRenderPanelOpen(['tag' => 'div', 'class' => 'mb-4', 'title' => 'Kategori Eşleme Ekle']) ?>
            <form id="mappingForm" class="scraper-mapping-form">
                <?= csrf_field() ?>
                <input type="hidden" name="mapping_id" value="">
                <div class="scraper-map-col">
                    <label class="ui-admin-form-label">Kaynak Site</label>
                    <select name="bot_site_id" class="ui-admin-form-select" required>
                        <option value="">-- Site Seç --</option>
                        <?php foreach ($sites as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="scraper-map-col-wide">
                    <label class="ui-admin-form-label">Uzak Kategori URL</label>
                    <input type="url" name="remote_category_url" class="ui-admin-form-control" required placeholder="https://example.com/category/mods">
                </div>
                <div class="scraper-map-col">
                    <label class="ui-admin-form-label">Yerel Kategori</label>
                    <select name="local_category_id" class="ui-admin-form-select" required>
                        <?= $categoryOptionsHtml('-- Hedef Kategori --') ?>
                    </select>
                </div>
                <div class="scraper-map-col">
                    <label class="ui-admin-form-label">Başlık Ön Eki</label>
                    <input type="text" name="title_prefix" class="ui-admin-form-control" maxlength="100" placeholder="ETS2 -">
                </div>
                <div class="scraper-map-actions">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-plus-circle"></i> Ekle</button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline scraper-hidden" data-scraper-action="reset-mapping-form" id="btnCancelMapping"><i class="bi bi-x-circle"></i> İptal</button>
                </div>
            </form>
    <?= adminRenderPanelClose('div') ?>
    
    <?= adminRenderPanelOpen(['tag' => 'div', 'title' => 'Mevcut Eşlemeler']) ?>
            <?= adminRenderTableOpen([
                'Site',
                'Uzak Kategori',
                'Yerel Kategori',
                ['label' => 'İşlemler', 'class' => 'text-end'],
            ], [
                'class' => 'admin-table',
                'wrap_class' => 'table-wrapper ui-table-wrap ui-surface',
                'label' => 'Mevcut eşlemeler',
            ]) ?>
                        <?php $lastMappingGroupKey = null; $mappingGroupIndex = 0; $mappingGroupId = ''; foreach ($mappings as $m):
                            $mappingGroup = $scraperMappingGroupInfo($m);
                            if ($lastMappingGroupKey !== $mappingGroup['key']):
                                $lastMappingGroupKey = $mappingGroup['key'];
                                $mappingGroupIndex++;
                                $mappingGroupId = 'scraper-mapping-group-' . $mappingGroupIndex;
                        ?>
                        <tr class="scraper-mapping-group-row scraper-game-row-<?= htmlspecialchars((string) $mappingGroup['game_tone'], ENT_QUOTES, 'UTF-8') ?> is-collapsed">
                            <td colspan="4">
                                <button type="button" class="scraper-mapping-group-toggle" data-scraper-action="toggle-mapping-group" data-scraper-group="<?= htmlspecialchars($mappingGroupId, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                                    <span class="scraper-mapping-group">
                                        <i class="bi bi-chevron-right scraper-mapping-group-chevron"></i>
                                        <span class="scraper-game-badge scraper-game-badge-<?= htmlspecialchars((string) $mappingGroup['game_tone'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $mappingGroup['game_icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $mappingGroup['game_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="scraper-mapping-group-site"><i class="bi bi-globe2"></i> <?= htmlspecialchars($mappingGroup['site'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="scraper-mapping-group-category"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($mappingGroup['category'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="admin-badge admin-badge-secondary"><?= (int) ($scraperMappingGroupCounts[$mappingGroup['key']] ?? 0) ?> eşleme</span>
                                    </span>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="scraper-mapping-item-row is-group-collapsed" data-scraper-group="<?= htmlspecialchars($mappingGroupId, ENT_QUOTES, 'UTF-8') ?>" hidden>
                            <td><?= htmlspecialchars($m['site_name']) ?></td>
                            <td><?= $scraperMappingRemoteHtml($m) ?></td>
                            <td><?= $scraperMappingLocalHtml($m) ?></td>
                            <td class="text-end">
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="list-mapping-topics" data-mapping-id="<?= (int) $m['id'] ?>" data-site-id="<?= (int) $m['bot_site_id'] ?>" data-category-url="<?= htmlspecialchars((string) $m['remote_category_url'], ENT_QUOTES, 'UTF-8') ?>" data-local-cat-id="<?= (int) ($m['local_category_id'] ?? 0) ?>"><i class="bi bi-list-ul"></i> Listele</button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-scraper-action="edit-mapping" data-mapping-id="<?= (int) $m['id'] ?>"><i class="bi bi-pencil"></i> Düzenle</button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="delete-mapping" data-mapping-id="<?= (int) $m['id'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <tr id="mapping-list-row-<?= $m['id'] ?>" class="scraper-subrow is-group-collapsed" data-scraper-group="<?= htmlspecialchars($mappingGroupId, ENT_QUOTES, 'UTF-8') ?>" hidden>
                            <td colspan="4" class="scraper-subrow-cell">
                                <div class="scraper-subrow-head">
                                    <h5 class="scraper-subrow-title"><i class="bi bi-collection"></i> Bulunan Konular</h5>
                                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-scraper-action="hide-target" data-target-id="mapping-list-row-<?= (int) $m['id'] ?>"><i class="bi bi-x-lg"></i> Kapat</button>
                                </div>
                                <div id="mapping-list-loading-<?= $m['id'] ?>" class="scraper-loading"><i class="bi bi-hourglass-split"></i> Hedef sayfadaki URL'ler keşfediliyor...</div>
                                <div id="mapping-list-content-<?= $m['id'] ?>" class="mapping-list-panel"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($mappings)): ?>
                        <?= adminRenderTableEmptyRow(4, ['icon' => 'bi-diagram-2', 'tone' => 'info', 'title' => 'Eşleme bulunamadı.', 'description' => 'Kategori eşlemeleri oluşturulduğunda burada listelenecek.']) ?>
                        <?php endif; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
</div>

<div class="scraper-tab-pane" id="tab-scrape">
    <?= adminRenderPanelOpen(['tag' => 'div', 'title' => 'Toplu İçerik Çek']) ?>
            <?= adminRenderTableOpen([
                'Site',
                'Uzak Kategori',
                'Yerel Kategori',
                'Sayfa Aralığı',
                ['label' => 'İşlemler', 'class' => 'text-end'],
            ], [
                'class' => 'admin-table',
                'wrap_class' => 'table-wrapper ui-table-wrap ui-surface',
                'label' => 'Toplu içerik çekilecek eşlemeler',
            ]) ?>
                        <?php $lastBulkMappingGroupKey = null; $bulkMappingGroupIndex = 0; $bulkMappingGroupId = ''; foreach ($mappings as $m):
                            $mappingGroup = $scraperMappingGroupInfo($m);
                            if ($lastBulkMappingGroupKey !== $mappingGroup['key']):
                                $lastBulkMappingGroupKey = $mappingGroup['key'];
                                $bulkMappingGroupIndex++;
                                $bulkMappingGroupId = 'scraper-bulk-mapping-group-' . $bulkMappingGroupIndex;
                        ?>
                        <tr class="scraper-mapping-group-row scraper-game-row-<?= htmlspecialchars((string) $mappingGroup['game_tone'], ENT_QUOTES, 'UTF-8') ?> is-collapsed">
                            <td colspan="5">
                                <button type="button" class="scraper-mapping-group-toggle" data-scraper-action="toggle-mapping-group" data-scraper-group="<?= htmlspecialchars($bulkMappingGroupId, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                                    <span class="scraper-mapping-group">
                                        <i class="bi bi-chevron-right scraper-mapping-group-chevron"></i>
                                        <span class="scraper-game-badge scraper-game-badge-<?= htmlspecialchars((string) $mappingGroup['game_tone'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $mappingGroup['game_icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $mappingGroup['game_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="scraper-mapping-group-site"><i class="bi bi-globe2"></i> <?= htmlspecialchars($mappingGroup['site'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="scraper-mapping-group-category"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($mappingGroup['category'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="admin-badge admin-badge-secondary"><?= (int) ($scraperMappingGroupCounts[$mappingGroup['key']] ?? 0) ?> eşleme</span>
                                    </span>
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="scraper-mapping-item-row is-group-collapsed" data-scraper-group="<?= htmlspecialchars($bulkMappingGroupId, ENT_QUOTES, 'UTF-8') ?>" hidden>
                            <td><?= htmlspecialchars($m['site_name']) ?></td>
                            <td><?= $scraperMappingRemoteHtml($m) ?></td>
                            <td><?= $scraperMappingLocalHtml($m) ?></td>
                            <td>
                                <div class="bulk-page-range">
                                    <label>
                                        <span>Başlangıç</span>
                                        <input type="number" id="bulk-page-start-<?= $m['id'] ?>" class="ui-admin-form-control bulk-page-control" value="1" min="1" max="999">
                                    </label>
                                    <label>
                                        <span>Bitiş</span>
                                        <input type="number" id="bulk-page-end-<?= $m['id'] ?>" class="ui-admin-form-control bulk-page-control" value="1" min="1" max="999">
                                    </label>
                                </div>
                            </td>
                            <td class="text-end">
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="list-bulk-mapping-topics" data-mapping-id="<?= (int) $m['id'] ?>" data-site-id="<?= (int) $m['bot_site_id'] ?>" data-category-url="<?= htmlspecialchars((string) $m['remote_category_url'], ENT_QUOTES, 'UTF-8') ?>" data-local-cat-id="<?= (int) ($m['local_category_id'] ?? 0) ?>"><i class="bi bi-list-ul"></i> Listele</button>
                            </td>
                        </tr>
                        <tr id="bulk-list-row-<?= $m['id'] ?>" class="scraper-subrow is-group-collapsed" data-scraper-group="<?= htmlspecialchars($bulkMappingGroupId, ENT_QUOTES, 'UTF-8') ?>" hidden>
                            <td colspan="5" class="scraper-subrow-cell">
                                <div class="scraper-subrow-head">
                                    <h5 class="scraper-subrow-title"><i class="bi bi-collection"></i> Toplu Çekilecek Konular</h5>
                                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-scraper-action="hide-target" data-target-id="bulk-list-row-<?= (int) $m['id'] ?>"><i class="bi bi-x-lg"></i> Kapat</button>
                                </div>
                                <div id="bulk-list-loading-<?= $m['id'] ?>" class="scraper-loading"><i class="bi bi-hourglass-split"></i> Hedef sayfalar taranıyor...</div>
                                <div id="bulk-list-content-<?= $m['id'] ?>" class="mapping-list-panel"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($mappings)): ?>
                        <?= adminRenderTableEmptyRow(5, ['icon' => 'bi-diagram-2', 'tone' => 'info', 'title' => 'Eşleme bulunamadı.', 'description' => 'Toplu içerik çekmek için önce bir eşleme oluşturun.']) ?>
                        <?php endif; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
</div>

<div class="scraper-tab-pane" id="tab-imports">
    <?= adminRenderPanelOpen([
        'tag' => 'div',
        'title' => 'Bot Logları & İçerik Havuzu',
        'icon' => 'bi-journal-text',
    ]) ?>
            <!-- Filtreleme Butonları -->
            <div class="scraper-filterbar">
                <span class="scraper-filter-label">Filtrele:</span>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm log-filter-btn active" data-filter="all" data-scraper-action="filter-logs">
                    <i class="bi bi-collection"></i> Tümü
                </button>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-success-outline log-filter-btn" data-filter="imported" data-scraper-action="filter-logs">
                    <i class="bi bi-check-circle"></i> Başarılı
                </button>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline log-filter-btn" data-filter="failed" data-scraper-action="filter-logs">
                    <i class="bi bi-x-circle"></i> Hatalı
                </button>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-warning-outline log-filter-btn" data-filter="preview" data-scraper-action="filter-logs">
                    <i class="bi bi-eye"></i> Önizleme
                </button>
            </div>

            <!-- İstatistik Kartları -->
            <div class="scraper-stats-grid" id="logStatsCards">
                <?php
                $importStatusCounts = ['imported' => 0, 'failed' => 0, 'preview' => 0, 'other' => 0];
                foreach ($imports as $imp) {
                    $status = (string) ($imp['status'] ?? '');
                    if (isset($importStatusCounts[$status])) {
                        $importStatusCounts[$status]++;
                    } else {
                        $importStatusCounts['other']++;
                    }
                }
                ?>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-check-circle scraper-stat-icon scraper-stat-success"></i>
                    <strong class="scraper-stat-value"><?= (int) $importStatusCounts['imported'] ?></strong>
                    <span class="scraper-stat-label">Yayınlanan</span>
                </div>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-exclamation-circle scraper-stat-icon scraper-stat-danger"></i>
                    <strong class="scraper-stat-value"><?= (int) $importStatusCounts['failed'] ?></strong>
                    <span class="scraper-stat-label">Hatalı</span>
                </div>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-eye scraper-stat-icon scraper-stat-warning"></i>
                    <strong class="scraper-stat-value"><?= (int) $importStatusCounts['preview'] ?></strong>
                    <span class="scraper-stat-label">Önizleme</span>
                </div>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-collection scraper-stat-icon scraper-stat-accent"></i>
                    <strong class="scraper-stat-value"><?= count($imports) ?></strong>
                    <span class="scraper-stat-label">Toplam</span>
                </div>
            </div>

            <?php if(!empty($imports)): ?>
            <div class="bulk-actions scraper-bulk-actions">
                <div>
                    <strong><span id="selectedImportsCount">0</span> içerik seçildi</strong>
                    <span class="scraper-bulk-hint">Toplu işlem yapmak için içerikleri seçin</span>
                </div>
                <div class="bulk-action-controls">
                    <label class="bulk-action-check">
                        <input type="checkbox" id="selectAllImports" data-import-select-all>
                        <span>Tümünü Seç</span>
                    </label>
                    <select id="bulkImportCategory" class="ui-admin-form-select scraper-select-narrow">
                        <?= $categoryOptionsHtml('-- Kategori Seç --') ?>
                    </select>
                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="bulk-publish" id="btnBulkPublish" disabled>
                        <i class="bi bi-send-check"></i> Toplu Yayınla
                    </button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="bulk-delete" id="btnBulkDelete" disabled>
                        <i class="bi bi-trash"></i> Toplu Sil
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <?= adminRenderTableOpen([
                [
                    'html' => '<input type="checkbox" id="selectAllImportsHeader" data-import-select-all class="scraper-check">',
                    'attrs' => ['aria-label' => 'Tüm içerikleri seç'],
                ],
                'İçerik',
                'Kaynak',
                'Görsel',
                'Zaman',
                'Durum',
                ['label' => 'İşlemler', 'class' => 'text-end'],
            ], [
                'class' => 'admin-table',
                'wrap_class' => 'table-wrapper ui-table-wrap ui-surface',
                'label' => 'Bot logları ve içerik havuzu',
            ]) ?>
                        <?php foreach ($imports as $imp):
                            $sourceTitle = trim((string) ($imp['source_title'] ?? ''));
                            $translatedTitle = trim((string) ($imp['translated_title'] ?? ''));
                            $title = $translatedTitle !== '' ? $translatedTitle : ($sourceTitle !== '' ? $sourceTitle : '(Başlıksız)');
                            $hasTranslation = $translatedTitle !== '' && $sourceTitle !== '' && $translatedTitle !== $sourceTitle;
                            $sourceUrl = trim((string) ($imp['source_url'] ?? ''));
                            $sourceLabel = $sourceUrl !== '' ? $scraperUrlLabel($sourceUrl) : 'Kaynak URL yok';
                            $images = array_values(array_filter(array_map('trim', explode("\n", (string) ($imp['downloaded_images'] ?: $imp['source_images'] ?: '')))));
                            $thumb = !empty($images) ? adminSafeImageUrl((string) $images[0], $baseUri) : '';
                            $imageCount = max((int) ($imp['images_count'] ?? 0), count($images));
                            $createdAt = $scraperDateInfo($imp['created_at'] ?? '');
                            $updatedAt = $scraperDateInfo($imp['updated_at'] ?? '');
                            $status = (string) ($imp['status'] ?? '');
                            $statusMeta = $scraperImportStatusMeta($status);
                            $siteName = trim((string) ($imp['site_name'] ?? 'Bilinmeyen'));
                            $topicId = (int) ($imp['topic_id'] ?? 0);
                        ?>
                        <tr class="scraper-import-row <?= $status === 'failed' ? 'scraper-import-row-failed' : '' ?>" data-log-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                            <td>
                                <input type="checkbox" class="import-checkbox scraper-check" value="<?= (int) $imp['id'] ?>" data-import-checkbox>
                            </td>
                            <td>
                                <div class="scraper-import-title-stack">
                                    <strong class="scraper-title-cell" title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                                    </strong>
                                    <span class="scraper-import-meta">
                                        <span>#<?= (int) $imp['id'] ?></span>
                                        <span><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($topicId > 0): ?><span>Konu #<?= $topicId ?></span><?php endif; ?>
                                    </span>
                                    <?php if ($hasTranslation): ?>
                                    <span class="scraper-import-source-title" title="<?= htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($sourceTitle, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($status === 'failed' && !empty($imp['error_message'])): ?>
                                <div class="scraper-error-note">
                                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars((string) $imp['error_message'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="scraper-import-source">
                                    <?php if ($sourceUrl !== ''): ?>
                                    <a href="<?= htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="scraper-source-link" title="<?= htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-box-arrow-up-right"></i> <?= htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">Kaynak URL yok</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($thumb): ?>
                                <div class="scraper-thumb-wrap">
                                    <img
                                        src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
                                        data-scraper-thumb-src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>"
                                        class="scraper-thumb"
                                        alt="Thumbnail"
                                        data-scraper-thumb
                                        width="40"
                                        height="40"
                                        loading="lazy"
                                        decoding="async"
                                        fetchpriority="low"
                                    >
                                    <?php if ($imageCount > 1): ?><span class="scraper-thumb-count"><?= $imageCount ?></span><?php endif; ?>
                                </div>
                                <?php else: ?>
                                    <?= adminRenderImagePlaceholder('scraper-thumb scraper-thumb-empty') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <time class="scraper-log-time" datetime="<?= htmlspecialchars($createdAt['iso'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($createdAt['full'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="scraper-log-time-label"><?= htmlspecialchars($createdAt['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= htmlspecialchars($createdAt['date'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($createdAt['time'] !== ''): ?><span><?= htmlspecialchars($createdAt['time'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                </time>
                                <?php if ($updatedAt['full'] !== '-' && $updatedAt['full'] !== $createdAt['full']): ?>
                                <span class="scraper-log-updated">Güncellendi: <?= htmlspecialchars($updatedAt['full'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-<?= htmlspecialchars((string) $statusMeta['class'], ENT_QUOTES, 'UTF-8') ?> scraper-status-badge">
                                    <i class="bi <?= htmlspecialchars((string) $statusMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars((string) $statusMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="scraper-import-actions">
                                <?php if($status !== 'imported'): ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="preview-import" data-import-id="<?= (int) $imp['id'] ?>">
                                    <i class="bi bi-eye"></i> İncele
                                </button>
                                <?php endif; ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="delete-import" data-import-id="<?= (int) $imp['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!empty($imports)): ?>
                        <tr class="scraper-filter-empty-row" data-log-empty="filter" hidden>
                            <td colspan="7">
                                <div class="scraper-empty-table">
                                    <i class="bi bi-filter-circle"></i>
                                    <strong>Filtreye uygun log bulunamadı</strong>
                                    <span data-log-empty-label>Bu durumda içerik yok</span>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if(empty($imports)): ?>
                        <?= adminRenderTableEmptyRow(7, [
                            'icon' => 'bi-inbox',
                            'tone' => 'info',
                            'title' => 'Henüz log kaydı yok',
                            'description' => 'Bot içerik çekmeye başladığında loglar burada görünecek',
                        ]) ?>
                        <?php endif; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
</div>

<div class="scraper-tab-pane" id="tab-settings">
    <?= adminRenderPanelOpen(['tag' => 'div', 'title' => 'Bot Motoru Ayarları']) ?>
            <form id="botSettingsForm">
                <?= csrf_field() ?>
                <div class="settings-subtabs" role="tablist">
                    <button type="button" class="settings-subtab-link active" data-settings-tab="general"><i class="bi bi-sliders"></i> Genel</button>
                    <button type="button" class="settings-subtab-link" data-settings-tab="content"><i class="bi bi-file-richtext"></i> İçerik</button>
                    <button type="button" class="settings-subtab-link" data-settings-tab="publish"><i class="bi bi-send-check"></i> Yayınlama</button>
                    <button type="button" class="settings-subtab-link" data-settings-tab="media"><i class="bi bi-images"></i> Medya</button>
                    <button type="button" class="settings-subtab-link" data-settings-tab="translation"><i class="bi bi-translate"></i> Çeviri</button>
                    <button type="button" class="settings-subtab-link" data-settings-tab="bulk"><i class="bi bi-collection"></i> Toplu İşlem</button>
                </div>
                <div class="settings-subtab-pane active" id="settings-tab-general">
                    <?= adminRenderSettingsCardOpen([
                        'icon' => 'bi-gear',
                        'title' => 'Bağlantı Ayarları',
                        'description' => 'HTTP istek ayarları ve bağlantı parametreleri',
                    ]) ?>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="ui-admin-form-label">User-Agent (Bot Kimliği)</label>
                            <input type="text" name="bot_user_agent" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_user_agent']) ?>">
                            <small class="text-muted">Hedef siteler botu engellemesin diye kullanılan tarayıcı kimliği.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">İstek Timeout (saniye)</label>
                            <input type="number" name="bot_request_timeout" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_request_timeout']) ?>" min="5" max="180">
                            <small class="text-muted">Sayfa yüklenme süresi limiti</small>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">İstek Gecikmesi (ms)</label>
                            <input type="number" name="bot_request_delay" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_request_delay']) ?>">
                            <small class="text-muted">Toplu çekimde sayfa arası bekleme</small>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">Retry Sayısı</label>
                            <input type="number" name="bot_retry_count" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_retry_count']) ?>" min="0" max="10">
                            <small class="text-muted">Hata durumunda tekrar deneme</small>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">Liste Kapak Arama Limiti</label>
                            <input type="number" name="bot_discover_cover_lookup_limit" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_discover_cover_lookup_limit']) ?>" min="0" max="50">
                            <small class="text-muted">Kategori listesinde kapak yoksa açılacak detay sayısı</small>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Retry Bekleme (ms)</label>
                            <input type="number" name="bot_retry_delay" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_retry_delay']) ?>" min="0" max="30000">
                            <small class="text-muted">Tekrar denemeler arası bekleme</small>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Proxy Bağlantısı</label>
                            <div class="ui-admin-form-control scraper-readonly-box">
                                Güvenli hedef sabitleme desteği tamamlanana kadar proxy kullanımı kapalıdır.
                                </div>
                            </div>
                        </div>
                        <?= adminRenderSettingsCardClose() ?>

                    <?= adminRenderSettingsCardOpen([
                        'icon' => 'bi-shield-check',
                        'title' => 'Güvenlik & Protokol',
                        'description' => 'SSL doğrulama ve yönlendirme ayarları',
                    ]) ?>
                    <div class="row g-3 scraper-settings-row">
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_follow_redirects" value="1" <?= $botSettingEnabled('bot_follow_redirects', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Yönlendirmeleri takip et</span>
                            </label>
                            <small class="text-muted ui-admin-help-block">301/302 yönlendirmelerini otomatik takip eder</small>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_ssl_verify" value="1" <?= $botSettingEnabled('bot_ssl_verify', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">SSL sertifikasını doğrula</span>
                            </label>
                            <small class="text-muted ui-admin-help-block">HTTPS bağlantılarında sertifika kontrolü</small>
                        </div>
                    </div>

                    <?= adminRenderSettingsCardClose() ?>

                    <?= adminRenderSettingsCardOpen([
                        'icon' => 'bi-code-square',
                        'title' => 'Gelişmiş Ayarlar',
                        'description' => "Özel HTTP header'ları ve diğer gelişmiş seçenekler",
                    ]) ?>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="ui-admin-form-label">Özel HTTP Headerlar</label>
                            <textarea name="bot_custom_headers" class="ui-admin-form-control" rows="3" placeholder="Header: Değer&#10;Authorization: Bearer token&#10;X-Custom-Header: value"><?= htmlspecialchars($botSettings['bot_custom_headers']) ?></textarea>
                            <small class="text-muted">Her satıra bir header yazın (Header: Değer formatında)</small>
                        </div>
                    <?= adminRenderSettingsCardClose() ?>
                    </div>
                </div>
                <div class="settings-subtab-pane" id="settings-tab-content">
                    <h6 class="scraper-settings-title">
                        <i class="bi bi-sliders"></i> Temel Ayarlar
                    </h6>
                    <div class="row g-3 scraper-settings-row">
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">Varsayılan Max Resim Sayısı</label>
                            <input type="number" name="bot_default_max_images" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_default_max_images']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">Resim Kayıt Klasörü</label>
                            <input type="text" name="bot_image_save_path" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_image_save_path']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-form-label">Açıklama Hizası</label>
                            <select name="bot_content_align" class="ui-admin-form-select">
                                <option value="left" <?= $botSettings['bot_content_align'] === 'left' ? 'selected' : '' ?>>Sol</option>
                                <option value="center" <?= $botSettings['bot_content_align'] === 'center' ? 'selected' : '' ?>>Orta</option>
                                <option value="right" <?= $botSettings['bot_content_align'] === 'right' ? 'selected' : '' ?>>Sağ</option>
                                <option value="justify" <?= $botSettings['bot_content_align'] === 'justify' ? 'selected' : '' ?>>İki Yana Yasla</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Minimum Başlık Uzunluğu</label>
                            <input type="number" name="bot_min_title_length" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_min_title_length']) ?>" min="0" max="250">
                            <small class="text-muted">Daha kısa başlıklar reddedilir</small>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Minimum İçerik Uzunluğu</label>
                            <input type="number" name="bot_min_content_length" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_min_content_length']) ?>" min="0" max="5000">
                            <small class="text-muted">Daha kısa içerikler reddedilir</small>
                        </div>
                    </div>

                    <h6 class="scraper-settings-title">
                        <i class="bi bi-code-slash"></i> HTML Temizleme
                    </h6>
                    <div class="row g-3 scraper-settings-row">
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_clean_html" value="1" <?= $botSettingEnabled('bot_clean_html', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">HTML temizle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_strip_scripts" value="1" <?= $botSettingEnabled('bot_strip_scripts', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Scriptleri temizle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_strip_iframes" value="1" <?= $botSettingEnabled('bot_strip_iframes', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Iframe temizle</span>
                            </label>
                        </div>
                    </div>

                    <h6 class="scraper-settings-title">
                        <i class="bi bi-download"></i> İçerik Çekme Seçenekleri
                    </h6>
                    <div class="row g-3 scraper-settings-row">
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_append_source_link" value="1" <?= $botSettingEnabled('bot_append_source_link', '0') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Kaynak link ekle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_skip_duplicate_urls" value="1" <?= $botSettingEnabled('bot_skip_duplicate_urls', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Aynı URL'yi atla</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_extract_download_links" value="1" <?= $botSettingEnabled('bot_extract_download_links', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">İndirme linklerini çek</span>
                            </label>
                        </div>
                    </div>

                    <h6 class="scraper-settings-title">
                        <i class="bi bi-magic"></i> Otomatik Tespit
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_detect_author_enabled" value="1" <?= $botSettingEnabled('bot_detect_author_enabled', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Mod yapımcısı otomatik tespiti</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Mod Yapımcısı Anahtar Kelimeleri</label>
                            <input type="text" name="bot_detect_author_labels" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_detect_author_labels']) ?>" placeholder="author,authors,credit,credits">
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_detect_version_enabled" value="1" <?= $botSettingEnabled('bot_detect_version_enabled', '1') ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Oyun sürümü otomatik tespiti</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Oyun Sürümü Regex Deseni</label>
                            <input type="text" name="bot_detect_version_pattern" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_detect_version_pattern']) ?>" placeholder="1\.(?:[3-9]\d|[1-9]\d{2,})">
                        </div>
                    </div>
                </div>
                <div class="settings-subtab-pane" id="settings-tab-publish">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_auto_publish" value="1" <?= $botSettingEnabled('bot_auto_publish', '0') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Bot içerikleri otomatik yayına alsın</span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="ui-admin-form-label">Varsayılan Yayın Durumu</label>
                        <select name="bot_default_status" class="ui-admin-form-select">
                            <option value="draft" <?= $botSettings['bot_default_status'] === 'draft' ? 'selected' : '' ?>>Taslak</option>
                            <option value="published" <?= $botSettings['bot_default_status'] === 'published' ? 'selected' : '' ?>>Yayında</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-form-label">Varsayılan Yazar</label>
                        <select name="bot_default_author_id" class="ui-admin-form-select">
                            <?php foreach ($botAuthors as $author): ?>
                            <option value="<?= (int)$author['id'] ?>" <?= (string)$botSettings['bot_default_author_id'] === (string)$author['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($botAuthorLabel($author), ENT_QUOTES, 'UTF-8') ?><?= !empty($author['group_name']) ? ' - ' . htmlspecialchars((string) $author['group_name'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if (empty($botAuthors)): ?>
                            <option value="<?= htmlspecialchars($botSettings['bot_default_author_id']) ?>">Mevcut kullanıcı (#<?= htmlspecialchars($botSettings['bot_default_author_id']) ?>)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-form-label">Tekrar Eden İçerik</label>
                        <select name="bot_duplicate_strategy" class="ui-admin-form-select">
                            <option value="skip" <?= $botSettings['bot_duplicate_strategy'] === 'skip' ? 'selected' : '' ?>>Atla</option>
                            <option value="update" <?= $botSettings['bot_duplicate_strategy'] === 'update' ? 'selected' : '' ?>>Güncelle</option>
                            <option value="draft" <?= $botSettings['bot_duplicate_strategy'] === 'draft' ? 'selected' : '' ?>>Taslak oluştur</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-form-label">Yayın Tarihi</label>
                        <select name="bot_publish_date_mode" class="ui-admin-form-select">
                            <option value="now" <?= $botSettings['bot_publish_date_mode'] === 'now' ? 'selected' : '' ?>>imdi</option>
                            <option value="source" <?= $botSettings['bot_publish_date_mode'] === 'source' ? 'selected' : '' ?>>Kaynak tarihi</option>
                            <option value="empty" <?= $botSettings['bot_publish_date_mode'] === 'empty' ? 'selected' : '' ?>>Boş bırak</option>
                        </select>
                    </div>
                </div>
                </div>
                <div class="settings-subtab-pane" id="settings-tab-media">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_require_cover_image" value="1" <?= $botSettingEnabled('bot_require_cover_image', '0') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Kapak görseli zorunlu</span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_download_images" value="1" <?= $botSettingEnabled('bot_download_images', '1') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Görselleri indir</span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_use_hotlink_images" value="1" <?= $botSettingEnabled('bot_use_hotlink_images', '0') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Hotlink görsel kullan</span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="ui-admin-form-label">Dosya Adı Formatı</label>
                        <select name="bot_image_filename_mode" class="ui-admin-form-select">
                            <option value="slug" <?= $botSettings['bot_image_filename_mode'] === 'slug' ? 'selected' : '' ?>>Başlık slug</option>
                            <option value="hash" <?= $botSettings['bot_image_filename_mode'] === 'hash' ? 'selected' : '' ?>>Hash</option>
                            <option value="original" <?= $botSettings['bot_image_filename_mode'] === 'original' ? 'selected' : '' ?>>Orijinal</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="ui-admin-form-label">İzinli Görsel Uzantıları</label>
                        <input type="text" name="bot_allowed_image_extensions" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_allowed_image_extensions']) ?>">
                    </div>
                </div>
                </div>
                <div class="settings-subtab-pane" id="settings-tab-translation">
                <div class="row g-4">
                    <div class="col-12"><hr></div>
                    <div class="col-12">
                        <h5 class="scraper-subrow-title">DeepL Çeviri Ayarları</h5>
                    </div>
                    <div class="col-md-12">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_enabled" value="1" <?= $botSettingEnabled('bot_translate_enabled', '0') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">DeepL API Çevirisini Aktifleştir</span>
                        </label>
                    </div>
                    <div class="col-md-12">
                        <label class="ui-admin-form-label">DeepL API Anahtarı (Auth Key)</label>
                        <input type="password" name="bot_deepl_api_key" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_deepl_api_key']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="ui-admin-form-label">Varsayılan Kaynak Dil</label>
                        <input type="text" name="bot_source_lang" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_source_lang']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="ui-admin-form-label">Varsayılan Hedef Dil</label>
                        <input type="text" name="bot_target_lang" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_target_lang']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_title" value="1" <?= $botSettingEnabled('bot_translate_title', '1') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Başlığı çevir</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_content" value="1" <?= $botSettingEnabled('bot_translate_content', '1') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">İçeriği çevir</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_download_names" value="1" <?= $botSettingEnabled('bot_translate_download_names', '0') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Link adlarını çevir</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <div class="scraper-translation-test">
                            <div class="scraper-translation-test-head">
                                <label class="ui-admin-form-label" for="botTranslationTestText">DeepL API Testi</label>
                                <button type="button" id="btnTestTranslation" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" data-scraper-action="test-translation">
                                    <i class="bi bi-translate"></i> Çevir
                                </button>
                            </div>
                            <textarea id="botTranslationTestText" class="ui-admin-form-control" rows="3" maxlength="5000" placeholder="Hello world"></textarea>
                            <div id="botTranslationTestResult" class="scraper-translation-test-result" hidden></div>
                        </div>
                    </div>
                </div>
                </div>
                <div class="settings-subtab-pane" id="settings-tab-bulk">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="ui-admin-form-label">Eş Zamanlı İşlem</label>
                        <input type="number" name="bot_bulk_concurrency" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_bulk_concurrency']) ?>" min="1" max="10">
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-form-label">Sayfa Başı Max Konu</label>
                        <input type="number" name="bot_bulk_max_topics_per_page" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_bulk_max_topics_per_page']) ?>" min="0">
                        <small class="text-muted">0 sınırsız anlamına gelir.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-form-label">Log Seviyesi</label>
                        <select name="bot_log_level" class="ui-admin-form-select">
                            <option value="minimal" <?= $botSettings['bot_log_level'] === 'minimal' ? 'selected' : '' ?>>Minimal</option>
                            <option value="normal" <?= $botSettings['bot_log_level'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="debug" <?= $botSettings['bot_log_level'] === 'debug' ? 'selected' : '' ?>>Debug</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_bulk_continue_on_error" value="1" <?= $botSettingEnabled('bot_bulk_continue_on_error', '1') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Hata olunca devam et</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_bulk_default_selected" value="1" <?= $botSettingEnabled('bot_bulk_default_selected', '1') ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Toplu listede varsayılan seçili gelsin</span>
                        </label>
                    </div>
                </div>
                </div>
                <div class="mt-4 pt-3 border-top text-end">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Bot Ayarlarını Kaydet</button>
                </div>
            </form>
    <?= adminRenderPanelClose('div') ?>
</div>

<div id="previewModal" class="ui-admin-modal-overlay scraper-preview-modal" role="dialog" aria-modal="true" aria-label="Icerik inceleme ve duzenleme" hidden aria-hidden="true">
    <div class="ui-admin-modal-shell scraper-preview-dialog ui-panel">
        <!--  Premium Header  -->
        <div class="crm-header">
            <div class="crm-header-left">
                <div class="crm-header-icon"><i class="bi bi-pencil-square"></i></div>
                <div class="crm-header-text">
                    <h5>İçerik İnceleme & Düzenleme</h5>
                    <span>İçeriği gözden geçirin, düzenleyin ve yayınlayın</span>
                </div>
            </div>
            <button type="button" class="crm-close" data-ui-modal-close data-scraper-action="close-preview"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <!--  Scrollable Body  -->
        <div class="crm-body">
            
            <!-- § Title -->
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-type-h1"></i> Başlık</div>
                <input type="text" id="prevTitleEdit" class="crm-title-input" placeholder="İçerik başlığını girin...">
            </div>
            
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-info-circle"></i> Mod Yapımcısı</div>
                <div class="scraper-preview-grid">
                    <div>
                        <label class="ui-admin-form-label" for="prevAuthorTopicEdit">Mod Yapımcısı</label>
                        <input type="text" id="prevAuthorTopicEdit" data-field="author_topic" class="ui-admin-form-control" placeholder="Mod Yapımcısı">
                    </div>
                    <div>
                        <label class="ui-admin-form-label" for="prevTopicVersionEdit">Gerekli Oyun Sürümü</label>
                        <input type="text" id="prevTopicVersionEdit" data-field="topic_version" class="ui-admin-form-control" placeholder="Gerekli Oyun Sürümü">
                    </div>
                </div>
            </div>
            
            <!-- § Cover -->
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-image"></i> Kapak Görseli</div>
                <div class="crm-cover-wrap scraper-preview-cover" id="prevCoverImage"></div>
            </div>
            
            <!-- § Content Editor -->
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-body-text"></i> Açıklama (İçerik)</div>
                <div class="crm-editor-wrap">
                    <div id="prevContentEdit" class="scraper-preview-editor"></div>
                </div>
            </div>
            
            <!-- § Gallery -->
            <div class="crm-section">
                <div class="crm-section-label">
                    <i class="bi bi-images"></i> Diğer Görseller 
                    <span class="crm-count scraper-preview-count" id="prevImages">0</span>
                </div>
                <div class="crm-gallery-thumbs scraper-preview-gallery" id="prevImgList"></div>
            </div>
            

            
            <!-- § Download Links -->
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-download"></i> İndirme Linkleri</div>
                <div id="prevDownloadsContainer"></div>
                <button type="button" class="crm-dl-add" data-scraper-action="add-prev-download"><i class="bi bi-plus-circle"></i> Yeni Link Ekle</button>
            </div>
            
            <!-- § Source -->
            <div class="crm-section">
                <div class="crm-section-label"><i class="bi bi-link-45deg"></i> Kaynak</div>
                <div class="crm-source">
                    <span class="crm-source-badge"><i class="bi bi-box-arrow-up-right"></i> KAYNAK</span>
                    <a href="#" id="prevSource" target="_blank"></a>
                </div>
            </div>
            
        </div>
        
        <!--  Premium Footer  -->
        <div class="crm-footer scraper-preview-footer">
            <a href="#" id="btnGoToTopic" target="_blank" class="scraper-topic-link">
                <i class="bi bi-box-arrow-up-right"></i> İçerik Aktarıldı konuya gitmek için tıklayın
            </a>
            <input type="hidden" id="publish_import_id">
            <button type="button" class="crm-publish-btn" data-scraper-action="publish-import">
                <i class="bi bi-check2-circle"></i> İçeriği Aktar
            </button>
        </div>
    </div>
</div>

</div> <!-- /.scraper-full-layout -->

<script type="application/json" id="adminScraperConfig"><?= json_encode([
    'baseUri' => rtrim((string) ($baseUri ?? ''), '/'),
    'botDefaultStatus' => (string) ($botSettings['bot_default_status'] ?? 'published'),
    'botBulkDefaultSelected' => (string) ($botSettings['bot_bulk_default_selected'] ?? '1'),
    'botBulkMaxTopicsPerPage' => (string) ($botSettings['bot_bulk_max_topics_per_page'] ?? '0'),
    'botBulkContinueOnError' => (string) ($botSettings['bot_bulk_continue_on_error'] ?? '1'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}' ?></script>
<script src="<?= asset_url('admin/assets/scraper-page.js', $baseUri) ?>" defer></script>
<script src="<?= asset_url('admin/assets/scraper.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
