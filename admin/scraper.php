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
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
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

<div class="admin-card scraper-tab-pane active ui-panel" id="tab-dashboard">
    <div class="card-header ui-panel__head">Bot İstatistikleri</div>
    <div class="card-body ui-panel__body">
        <div class="admin-stat-grid ui-grid">
            <div class="admin-stat-card stat-info ui-card">
                <div class="stat-icon"><i class="bi bi-globe"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Toplam Site</span>
                    <span class="stat-value"><?= number_format($stats['sites']) ?></span>
                </div>
            </div>
            <div class="admin-stat-card stat-warning ui-card">
                <div class="stat-icon"><i class="bi bi-cloud-arrow-down"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Bekleyen İçerik</span>
                    <span class="stat-value"><?= number_format($stats['pending']) ?></span>
                </div>
            </div>
            <div class="admin-stat-card stat-success ui-card">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-content">
                    <span class="stat-label">Yayınlanan</span>
                    <span class="stat-value"><?= number_format($stats['imported']) ?></span>
                </div>
            </div>
        </div>
        
        <h4 class="mt-4 mb-3 scraper-section-title">Son Bot Görevleri</h4>
        <div class="table-wrapper ui-table-wrap ui-surface">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Site</th>
                        <th>Durum</th>
                        <th>URL'ler</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr><td colspan="5" class="ui-admin-empty-state ui-empty">Henüz görev bulunmuyor.</td></tr>
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
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="scraper-tab-pane" id="tab-sites">
    <div class="admin-card mb-4 ui-panel">
        <div class="card-header ui-panel__head" id="siteFormTitle">Yeni Site Ekle</div>
        <div class="card-body ui-panel__body">
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
                        <div class="settings-card ui-card">
                            <div class="settings-card-header ui-panel__head">
                                <div class="settings-card-icon"><i class="bi bi-info-circle"></i></div>
                                <div>
                                    <h6 class="settings-card-title">Temel Bilgiler</h6>
                                    <p class="settings-card-desc">Site adı, URL ve genel ayarlar</p>
                                </div>
                            </div>
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
                        </div>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-selectors">
                        <div class="settings-card ui-card">
                            <div class="settings-card-header ui-panel__head">
                                <div class="settings-card-icon"><i class="bi bi-crosshair"></i></div>
                                <div>
                                    <h6 class="settings-card-title">CSS Seçiciler (Selectors)</h6>
                                    <p class="settings-card-desc">İçerik çekmek için kullanılacak CSS selector'ları</p>
                                </div>
                            </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="ui-admin-form-label">Konu Listesi (Kapsayıcı)</label><input type="text" id="sel_topic_list" name="sel_topic_list" class="ui-admin-form-control" placeholder=".article-list .item"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Konu Linki</label><input type="text" id="sel_topic_link" name="sel_topic_link" class="ui-admin-form-control" placeholder="a.title"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Başlığı</label><input type="text" id="sel_title" name="sel_title" class="ui-admin-form-control" placeholder="h1.post-title"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İçerik Gövdesi</label><input type="text" id="sel_content" name="sel_content" class="ui-admin-form-control" placeholder=".post-content"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Resimler (Galeri)</label><input type="text" id="sel_images" name="sel_images" class="ui-admin-form-control" placeholder=".post-gallery img"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">İndirme Linkleri</label><input type="text" id="sel_download_links" name="sel_download_links" class="ui-admin-form-control" placeholder="a.download-btn"></div>
                            <div class="col-md-6"><label class="ui-admin-form-label">Sayfalama Sonraki Linki</label><input type="text" id="sel_pagination" name="sel_pagination" class="ui-admin-form-control" placeholder=".pagination a.next"></div>
                        </div>
                        </div>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-settings">
                        <div class="settings-card ui-card">
                            <div class="settings-card-header ui-panel__head">
                                <div class="settings-card-icon"><i class="bi bi-sliders"></i></div>
                                <div>
                                    <h6 class="settings-card-title">Siteye Özel Ayarlar</h6>
                                    <p class="settings-card-desc">Resim, dil ve çeviri ayarları</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="ui-admin-form-label">Max Resim İndirme</label><input type="number" id="max_images" name="max_images" class="ui-admin-form-control" value="5"></div>
                                <div class="col-md-4"><label class="ui-admin-form-label">Kaynak Dil</label><input type="text" id="source_lang" name="source_lang" class="ui-admin-form-control" value="EN" placeholder="EN"></div>
                                <div class="col-md-4"><label class="ui-admin-form-label">Hedef Dil</label><input type="text" id="target_lang" name="target_lang" class="ui-admin-form-control" value="TR" placeholder="TR"></div>
                                <div class="col-12"><label class="ui-admin-switch"><input type="checkbox" id="translate" name="translate" value="1"><span class="ui-admin-switch-label">Bu site için çeviriyi aktifleştir</span></label></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 site-subtab-pane" id="site-tab-customize">
                        <div class="settings-card ui-card">
                            <div class="settings-card-header ui-panel__head">
                                <div class="settings-card-icon"><i class="bi bi-magic"></i></div>
                                <div>
                                    <h6 class="settings-card-title">Özelleştirme & Otomasyonlar</h6>
                                    <p class="settings-card-desc">Bul-değiştir kuralları, otomatik tespit ve özel ayarlar</p>
                                </div>
                            </div>
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
                            <div class="col-md-4"><label class="ui-admin-form-label">Varsayılan Kategori</label><select id="site_default_category_id" name="site_default_category_id" class="ui-admin-form-select"><option value="">Seçilmedi</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
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
        </div>
    </div>
    
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head">Kayıtlı Siteler</div>
        <div class="card-body ui-panel__body">
            <div class="table-wrapper ui-table-wrap ui-surface">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Site Adı</th>
                            <th>Base URL</th>
                            <th>İçerik/Eşleme</th>
                            <th>Durum</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
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
                        <tr><td colspan="6" class="ui-admin-empty-state ui-empty">Kayıtlı site bulunamadı.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="scraper-tab-pane" id="tab-mappings">
    <div class="admin-card mb-4 ui-panel">
        <div class="card-header ui-panel__head">Kategori Eşleme Ekle</div>
        <div class="card-body ui-panel__body">
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
                        <option value="">-- Hedef Kategori --</option>
                        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="scraper-map-actions">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-plus-circle"></i> Ekle</button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline scraper-hidden" data-scraper-action="reset-mapping-form" id="btnCancelMapping"><i class="bi bi-x-circle"></i> İptal</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head">Mevcut Eşlemeler</div>
        <div class="card-body ui-panel__body">
            <div class="table-wrapper ui-table-wrap ui-surface">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Site</th>
                            <th>Uzak Kategori</th>
                            <th>Yerel Kategori</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mappings as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['site_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($m['remote_category_name']) ?></strong><br>
                                <a href="<?= htmlspecialchars($m['remote_category_url']) ?>" target="_blank" class="text-muted scraper-link-sm"><?= htmlspecialchars($m['remote_category_url']) ?></a>
                            </td>
                            <td><span class="admin-badge admin-badge-info"><?= htmlspecialchars($m['local_category_name'] ?? 'Bilinmiyor') ?></span></td>
                            <td class="text-end">
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="list-mapping-topics" data-mapping-id="<?= (int) $m['id'] ?>" data-site-id="<?= (int) $m['bot_site_id'] ?>" data-category-url="<?= htmlspecialchars((string) $m['remote_category_url'], ENT_QUOTES, 'UTF-8') ?>" data-local-cat-id="<?= (int) ($m['local_category_id'] ?? 0) ?>"><i class="bi bi-list-ul"></i> Listele</button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-scraper-action="edit-mapping" data-mapping-id="<?= (int) $m['id'] ?>"><i class="bi bi-pencil"></i> Düzenle</button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="delete-mapping" data-mapping-id="<?= (int) $m['id'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <tr id="mapping-list-row-<?= $m['id'] ?>" class="scraper-subrow">
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
                        <tr><td colspan="4" class="ui-admin-empty-state ui-empty">Eşleme bulunamadı.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="scraper-tab-pane" id="tab-scrape">
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head">Toplu İçerik Çek</div>
        <div class="card-body ui-panel__body">
            <div class="table-wrapper ui-table-wrap ui-surface">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Site</th>
                            <th>Uzak Kategori</th>
                            <th>Yerel Kategori</th>
                            <th>Sayfa Aralığı</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mappings as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['site_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($m['remote_category_name']) ?></strong><br>
                                <a href="<?= htmlspecialchars($m['remote_category_url']) ?>" target="_blank" class="text-muted scraper-link-sm"><?= htmlspecialchars($m['remote_category_url']) ?></a>
                            </td>
                            <td><span class="admin-badge admin-badge-info"><?= htmlspecialchars($m['local_category_name'] ?? 'Bilinmiyor') ?></span></td>
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
                        <tr id="bulk-list-row-<?= $m['id'] ?>" class="scraper-subrow">
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
                        <tr><td colspan="5" class="ui-admin-empty-state ui-empty">Eşleme bulunamadı.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="scraper-tab-pane" id="tab-imports">
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head"><i class="bi bi-journal-text me-2"></i>Bot Logları & İçerik Havuzu</div>
        <div class="card-body ui-panel__body">
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
                $successCount = 0;
                $failedCount = 0;
                $pendingCount = 0;
                foreach ($imports as $imp) {
                    if ($imp['status'] === 'imported') $successCount++;
                    elseif ($imp['status'] === 'failed') $failedCount++;
                    else $pendingCount++;
                }
                ?>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-check-circle scraper-stat-icon scraper-stat-success"></i>
                    <strong class="scraper-stat-value"><?= $successCount ?></strong>
                    <span class="scraper-stat-label">Başarılı</span>
                </div>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-exclamation-circle scraper-stat-icon scraper-stat-danger"></i>
                    <strong class="scraper-stat-value"><?= $failedCount ?></strong>
                    <span class="scraper-stat-label">Hatalı</span>
                </div>
                <div class="admin-surface-card scraper-stat-card ui-surface ui-table-wrap">
                    <i class="bi bi-clock-history scraper-stat-icon scraper-stat-warning"></i>
                    <strong class="scraper-stat-value"><?= $pendingCount ?></strong>
                    <span class="scraper-stat-label">Bekleyen</span>
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
                        <option value="">-- Kategori Seç --</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
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
            <div class="table-wrapper ui-table-wrap ui-surface">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAllImportsHeader" data-import-select-all class="scraper-check">
                            </th>
                            <th>ID</th>
                            <th>Site</th>
                            <th>Başlık (Çeviri / Kaynak)</th>
                            <th>Görsel</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imports as $imp):
                            $title = $imp['translated_title'] ?: $imp['source_title'] ?: '(Başlıksız)';
                            $images = array_filter(explode("\n", $imp['downloaded_images'] ?: $imp['source_images'] ?: ''));
                            $thumb = !empty($images) ? $baseUri . '/' . $images[0] : '';
                            
                            // Durum badge renkleri
                            $statusClass = 'secondary';
                            $statusIcon = 'bi-clock-history';
                            if ($imp['status'] === 'imported') {
                                $statusClass = 'success';
                                $statusIcon = 'bi-check-circle-fill';
                            } elseif ($imp['status'] === 'failed') {
                                $statusClass = 'danger';
                                $statusIcon = 'bi-x-circle-fill';
                            } elseif ($imp['status'] === 'preview') {
                                $statusClass = 'warning';
                                $statusIcon = 'bi-eye-fill';
                            }
                        ?>
                        <tr class="<?= $imp['status'] === 'failed' ? 'scraper-import-row-failed' : '' ?>">
                            <td>
                                <input type="checkbox" class="import-checkbox scraper-check" value="<?= $imp['id'] ?>" data-import-checkbox>
                            </td>
                            <td>#<?= $imp['id'] ?></td>
                            <td><?= htmlspecialchars($imp['site_name'] ?? 'Bilinmeyen') ?></td>
                            <td>
                                <strong class="scraper-title-cell" title="<?= htmlspecialchars($title) ?>">
                                    <?= htmlspecialchars($title) ?>
                                </strong>
                                <a href="<?= htmlspecialchars($imp['source_url']) ?>" target="_blank" class="text-muted scraper-source-link"><i class="bi bi-box-arrow-up-right"></i> Kaynağa Git</a>
                                <?php if ($imp['status'] === 'failed' && !empty($imp['error_message'])): ?>
                                <div class="scraper-error-note">
                                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($imp['error_message']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($thumb): ?>
                                    <img
                                        src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="
                                        data-scraper-thumb-src="<?= htmlspecialchars($thumb) ?>"
                                        class="scraper-thumb"
                                        alt="Thumbnail"
                                        data-scraper-thumb
                                        width="40"
                                        height="40"
                                        loading="lazy"
                                        decoding="async"
                                        fetchpriority="low"
                                    >
                                <?php else: ?>
                                    <div class="scraper-thumb-empty">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d.m H:i', strtotime($imp['created_at'])) ?></td>
                            <td>
                                <span class="admin-badge admin-badge-<?= $statusClass ?> scraper-status-badge">
                                    <i class="bi <?= $statusIcon ?>"></i>
                                    <?= $imp['status'] === 'imported' ? 'Başarılı' : ($imp['status'] === 'failed' ? 'Hatalı' : ($imp['status'] === 'preview' ? 'Önizleme' : ucfirst($imp['status']))) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if($imp['status'] !== 'imported'): ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-scraper-action="preview-import" data-import-id="<?= (int) $imp['id'] ?>">
                                    <i class="bi bi-eye"></i> İncele / Yayınla
                                </button>
                                <?php endif; ?>
                                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline" data-scraper-action="delete-import" data-import-id="<?= (int) $imp['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($imports)): ?>
                        <tr><td colspan="8" class="scraper-empty-table">
                            <i class="bi bi-inbox"></i>
                            <strong>Henüz log kaydı yok</strong>
                            <span>Bot içerik çekmeye başladığında loglar burada görünecek</span>
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="scraper-tab-pane" id="tab-settings">
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head">Bot Motoru Ayarları</div>
        <div class="card-body ui-panel__body">
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
                    <div class="settings-card ui-card">
                        <div class="settings-card-header ui-panel__head">
                            <div class="settings-card-icon"><i class="bi bi-gear"></i></div>
                            <div>
                                <h6 class="settings-card-title">Bağlantı Ayarları</h6>
                                <p class="settings-card-desc">HTTP istek ayarları ve bağlantı parametreleri</p>
                            </div>
                        </div>
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
                    </div>

                    <div class="settings-card ui-card">
                        <div class="settings-card-header ui-panel__head">
                            <div class="settings-card-icon"><i class="bi bi-shield-check"></i></div>
                            <div>
                                <h6 class="settings-card-title">Güvenlik & Protokol</h6>
                                <p class="settings-card-desc">SSL doğrulama ve yönlendirme ayarları</p>
                            </div>
                        </div>
                    <div class="row g-3 scraper-settings-row">
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_follow_redirects" value="1" <?= $botSettings['bot_follow_redirects'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Yönlendirmeleri takip et</span>
                            </label>
                            <small class="text-muted ui-admin-help-block">301/302 yönlendirmelerini otomatik takip eder</small>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_ssl_verify" value="1" <?= $botSettings['bot_ssl_verify'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">SSL sertifikasını doğrula</span>
                            </label>
                            <small class="text-muted ui-admin-help-block">HTTPS bağlantılarında sertifika kontrolü</small>
                        </div>
                    </div>

                    </div>

                    <div class="settings-card ui-card">
                        <div class="settings-card-header ui-panel__head">
                            <div class="settings-card-icon"><i class="bi bi-code-square"></i></div>
                            <div>
                                <h6 class="settings-card-title">Gelişmiş Ayarlar</h6>
                                <p class="settings-card-desc">Özel HTTP header'ları ve diğer gelişmiş seçenekler</p>
                            </div>
                        </div>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="ui-admin-form-label">Özel HTTP Headerlar</label>
                            <textarea name="bot_custom_headers" class="ui-admin-form-control" rows="3" placeholder="Header: Değer&#10;Authorization: Bearer token&#10;X-Custom-Header: value"><?= htmlspecialchars($botSettings['bot_custom_headers']) ?></textarea>
                            <small class="text-muted">Her satıra bir header yazın (Header: Değer formatında)</small>
                        </div>
                    </div>
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
                                <input type="checkbox" name="bot_clean_html" value="1" <?= $botSettings['bot_clean_html'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">HTML temizle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_strip_scripts" value="1" <?= $botSettings['bot_strip_scripts'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Scriptleri temizle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_strip_iframes" value="1" <?= $botSettings['bot_strip_iframes'] === '1' ? 'checked' : '' ?>>
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
                                <input type="checkbox" name="bot_append_source_link" value="1" <?= $botSettings['bot_append_source_link'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Kaynak link ekle</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_skip_duplicate_urls" value="1" <?= $botSettings['bot_skip_duplicate_urls'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Aynı URL'yi atla</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_extract_download_links" value="1" <?= $botSettings['bot_extract_download_links'] === '1' ? 'checked' : '' ?>>
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
                                <input type="checkbox" name="bot_detect_author_enabled" value="1" <?= $botSettings['bot_detect_author_enabled'] === '1' ? 'checked' : '' ?>>
                                <span class="ui-admin-switch-label">Mod yapımcısı otomatik tespiti</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-form-label">Mod Yapımcısı Anahtar Kelimeleri</label>
                            <input type="text" name="bot_detect_author_labels" class="ui-admin-form-control" value="<?= htmlspecialchars($botSettings['bot_detect_author_labels']) ?>" placeholder="author,authors,credit,credits">
                        </div>
                        <div class="col-md-6">
                            <label class="ui-admin-switch">
                                <input type="checkbox" name="bot_detect_version_enabled" value="1" <?= $botSettings['bot_detect_version_enabled'] === '1' ? 'checked' : '' ?>>
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
                            <input type="checkbox" name="bot_auto_publish" value="1" <?= $botSettings['bot_auto_publish'] === '1' ? 'checked' : '' ?>>
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
                            <input type="checkbox" name="bot_require_cover_image" value="1" <?= $botSettings['bot_require_cover_image'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Kapak görseli zorunlu</span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_download_images" value="1" <?= $botSettings['bot_download_images'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Görselleri indir</span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_use_hotlink_images" value="1" <?= $botSettings['bot_use_hotlink_images'] === '1' ? 'checked' : '' ?>>
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
                            <input type="checkbox" name="bot_translate_enabled" value="1" <?= $botSettings['bot_translate_enabled'] === '1' ? 'checked' : '' ?>>
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
                            <input type="checkbox" name="bot_translate_title" value="1" <?= $botSettings['bot_translate_title'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Başlığı çevir</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_content" value="1" <?= $botSettings['bot_translate_content'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">İçeriği çevir</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translate_download_names" value="1" <?= $botSettings['bot_translate_download_names'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Link adlarını çevir</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_translation_fallback_original" value="1" <?= $botSettings['bot_translation_fallback_original'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Hata olursa orijinali kullan</span>
                        </label>
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
                            <input type="checkbox" name="bot_bulk_continue_on_error" value="1" <?= $botSettings['bot_bulk_continue_on_error'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Hata olunca devam et</span>
                        </label>
                    </div>
                    <div class="col-md-3">
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="bot_bulk_default_selected" value="1" <?= $botSettings['bot_bulk_default_selected'] === '1' ? 'checked' : '' ?>>
                            <span class="ui-admin-switch-label">Toplu listede varsayılan seçili gelsin</span>
                        </label>
                    </div>
                </div>
                </div>
                <div class="mt-4 pt-3 border-top text-end">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Bot Ayarlarını Kaydet</button>
                </div>
            </form>
        </div>
    </div>
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
