<?php

declare(strict_types=1);
/**
 * Görünüm Yönetimi — Header, Footer, Sidebar, Menü Ayarları
 */
require_once __DIR__ . '/init.php';
adminRequirePermission('appearance.view', 'Gorunum ayarlarini goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Görünüm';
$definitions = adminSettingDefinitions();
$sections = [
    'appearance'  => ['title' => 'Görünüm Ayarları',  'icon' => 'bi-palette',         'desc' => 'Tema, marka, renk ve genel public görünüm tercihleri'],
    'lay_header'  => ['title' => 'Header',  'icon' => 'bi-window-stack',    'desc' => 'Üst menü çubuğu düzeni, renkleri ve bileşenleri'],
    'lay_footer'  => ['title' => 'Footer',  'icon' => 'bi-window-desktop',  'desc' => 'Alt bilgi alanı düzeni, kolonlar ve içerik'],
    'lay_sidebar' => ['title' => 'Sidebar', 'icon' => 'bi-layout-sidebar',  'desc' => 'Yan panel widget ayarları ve sıralaması'],
    'lay_menu'    => ['title' => 'Menü',    'icon' => 'bi-list-nested',     'desc' => 'Navigasyon menü öğeleri ve mobil menü'],
    'topic_detail' => ['title' => 'Konu İçi Ayarları', 'icon' => 'bi-card-text',      'desc' => 'Public konu detay sayfasındaki paneller, sayaçlar ve içerik blokları'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik hatası.');
        header('Location: appearance.php');
        exit;
    }
    if (!adminCurrentUserCan('appearance.edit')) {
        adminDenyAction('Gorunum ayarlarini kaydetmek icin gerekli izin hesabiniza tanimlanmamis.', 'appearance.php');
    }

    try {
        saveAdminSettings($pdo, $_POST);
        $activeTab = $_POST['_active_tab'] ?? 'lay_header';
        flash('success', 'Görünüm ayarları başarıyla kaydedildi.');
        header('Location: appearance.php#' . $activeTab);
        exit;
    } catch (Throwable $e) {
        flash('error', 'Ayarlar kaydedilemedi: ' . safeErrorMessage($e));
    }
}

$settings = getAdminSettings($pdo);
$sidebarBuilderConfig = function_exists('sidebarBuilderConfigFromSettings') ? sidebarBuilderConfigFromSettings($settings) : [];
$sidebarWidgetCatalog = function_exists('sidebarBuilderWidgetCatalog') ? sidebarBuilderWidgetCatalog() : [];

$menuIconPresets = [
    'bi-house' => 'Anasayfa',
    'bi-grid' => 'Kategoriler',
    'bi-cloud-arrow-up' => 'Mod Yukle',
    'bi-calendar-event' => 'Etkinlik',
    'bi-trophy' => 'Liderlik',
    'bi-envelope-paper' => 'Iletisim',
    'bi-fire' => 'Populer',
    'bi-clock-history' => 'Yeni',
    'bi-person-circle' => 'Profil',
    'bi-search' => 'Arama',
    'bi-info-circle' => 'Bilgi',
    'bi-link-45deg' => 'Link',
];
$defaultTopMenuRaw = "Anasayfa|/index.php|bi-house\nKategoriler|{category_list}|bi-grid\nMod Yukle|{upload_topic}|bi-cloud-arrow-up\nEtkinlikler|{events}|bi-calendar-event\nLiderlik|{leaderboard}|bi-trophy\nIletisim|{contact}|bi-envelope-paper";
$menuRaw = trim((string) ($settings['menu_items'] ?? ''));
$menuBuilderRaw = $menuRaw !== '' ? $menuRaw : $defaultTopMenuRaw;
$menuLinesForBuilder = array_values(array_filter(array_map('trim', preg_split('/\R+/', $menuBuilderRaw) ?: [])));
$menuRows = [];
foreach ($menuLinesForBuilder as $line) {
    $parts = array_map('trim', explode('|', $line, 3));
    if (($parts[0] ?? '') === '') {
        continue;
    }
    $menuRows[] = [
        'label' => $parts[0] ?? '',
        'url' => $parts[1] ?? '#',
        'icon' => $parts[2] ?? '',
    ];
}
if ($menuRows === []) {
    $menuRows = [
        ['label' => 'Anasayfa', 'url' => '/index.php', 'icon' => 'bi-house'],
        ['label' => 'Kategoriler', 'url' => '{category_list}', 'icon' => 'bi-grid'],
        ['label' => 'Mod Yukle', 'url' => '{upload_topic}', 'icon' => 'bi-cloud-arrow-up'],
        ['label' => 'Etkinlikler', 'url' => '{events}', 'icon' => 'bi-calendar-event'],
        ['label' => 'Liderlik', 'url' => '{leaderboard}', 'icon' => 'bi-trophy'],
        ['label' => 'Iletisim', 'url' => '{contact}', 'icon' => 'bi-envelope-paper'],
    ];
}
require_once __DIR__ . '/header.php';
?>


<form method="post" action="appearance.php" class="settings-admin-form" id="appearanceForm">
    <?= csrf_field() ?>
    <input type="hidden" name="_active_tab" id="activeTabInput" value="appearance">
    <input type="hidden" name="_sections" value="<?= htmlspecialchars(implode(',', array_keys($sections)), ENT_QUOTES, 'UTF-8') ?>">

    <div class="settings-tabs-wrapper ui-section">
        <ul class="settings-tabs">
            <?php $first = true; foreach ($sections as $id => $section): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $first ? 'active' : '' ?>" href="#<?= htmlspecialchars($id) ?>">
                        <i class="bi <?= htmlspecialchars($section['icon']) ?>"></i><?= htmlspecialchars($section['title']) ?>
                    </a>
                </li>
            <?php $first = false; endforeach; ?>
        </ul>
    </div>

    <div class="settings-tab-content ui-section">
        <?php foreach ($sections as $id => $section): ?>
            <section id="<?= htmlspecialchars($id) ?>" class="settings-section ui-section">
                <!-- Açıklama kartı -->
                <div class="appearance-section-head">
                    <i class="bi <?= htmlspecialchars($section['icon']) ?>"></i>
                    <div>
                        <div class="appearance-section-title"><?= htmlspecialchars($section['title']) ?></div>
                        <div class="appearance-section-desc"><?= htmlspecialchars($section['desc']) ?></div>
                    </div>
                </div>

                <?php if ($id !== 'lay_sidebar' && $id !== 'lay_menu' && $id !== 'lay_header'): ?>
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'icon' => (string) $section['icon'],
                    'title' => (string) $section['title'] . ' Yapılandırması',
                ]) ?>
                        <?= adminRenderSettingsGrid($definitions, $settings, $id, null, 'admin-appearance-grid ui-grid') ?>
                <?= adminRenderPanelClose('div') ?>
                <?php endif; ?>

                <?php if ($id === 'lay_header'): ?>
                <?php
                $headerSubTabs = [
                    'hd_general' => ['title' => 'Genel Header Ayarlari', 'icon' => 'bi-sliders'],
                    'hd_topbar' => ['title' => 'Ust Menu Ogeleri', 'icon' => 'bi-list-nested'],
                ];
                $headerGeneralKeys = [
                    'header_sticky',
                    'header_show_search',
                    'header_search_placeholder',
                    'header_show_auth_buttons',
                    'header_show_profile_btn',
                    'header_show_admin_btn',
                    'header_brand_text',
                    'header_brand_icon',
                    'header_bg_color',
                    'header_text_color',
                    'header_accent_color',
                    'header_border_color',
                    'header_topbar_enabled',
                    'header_topbar_text',
                    'header_topbar_bg',
                    'header_custom_css',
                ];
                ?>
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'header_class' => 'profile-mini-row-wrap',
                    'body_class' => 'admin-card-body-flush',
                    'icon' => 'bi-window-stack',
                    'title' => 'Header Yapilandirmasi',
                ]) ?>
                        <div class="sidebar-subtabs">
                            <?php $hdFirst = true; foreach ($headerSubTabs as $hdId => $hdTab): ?>
                                <button type="button" class="sidebar-subtab-btn <?= $hdFirst ? 'active' : '' ?>" data-subtab="<?= $hdId ?>">
                                    <i class="bi <?= htmlspecialchars((string) $hdTab['icon']) ?>"></i><?= htmlspecialchars((string) $hdTab['title']) ?>
                                </button>
                            <?php $hdFirst = false; endforeach; ?>
                        </div>

                        <?php $hdFirst = true; foreach ($headerSubTabs as $hdId => $hdTab): ?>
                        <div class="sidebar-subtab-panel<?= $hdFirst ? ' is-active' : '' ?>" id="<?= $hdId ?>">
                            <?php if ($hdId === 'hd_general'): ?>
                                <div class="p-3">
                                    <?= adminRenderSettingsGrid($definitions, $settings, 'lay_header', $headerGeneralKeys, 'admin-appearance-grid ui-grid') ?>
                                </div>
                            <?php else: ?>
                                <div class="card-body pt-0 pb-3 px-3 ui-panel__body">
                                    <textarea id="menu_items" name="menu_items" hidden><?= htmlspecialchars($menuBuilderRaw) ?></textarea>
                                    <div class="appearance-menu-builder" data-menu-builder>
                                        <div class="appearance-menu-builder-toolbar">
                                            <div>
                                                <strong>Topbar menu ogeleri</strong>
                                                <span>Header menu linklerini buradan ekle, sil ve sirala.</span>
                                            </div>
                                            <button type="button" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" data-menu-add><i class="bi bi-plus-lg"></i> Oge Ekle</button>
                                        </div>
                                        <div class="appearance-menu-row-list" data-menu-row-list>
                                            <?php foreach ($menuRows as $idx => $menuRow): ?>
                                                <?php $selectedIcon = (string) ($menuRow['icon'] ?? ''); ?>
                                                <div class="appearance-menu-row" data-menu-row>
                                                    <div class="appearance-menu-order">
                                                        <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                                        <input type="number" min="1" value="<?= $idx + 1 ?>" aria-label="Sira" data-menu-order>
                                                    </div>
                                                    <label class="appearance-menu-field">
                                                        <span>Menu ismi</span>
                                                        <input type="text" value="<?= htmlspecialchars((string) ($menuRow['label'] ?? '')) ?>" placeholder="Orn: Kategoriler" data-menu-label>
                                                    </label>
                                                    <label class="appearance-menu-field appearance-menu-field-grow">
                                                        <span>Menu baglantisi</span>
                                                        <input type="text" value="<?= htmlspecialchars((string) ($menuRow['url'] ?? '#')) ?>" placeholder="/kategori, {events} veya https://..." data-menu-url>
                                                    </label>
                                                    <label class="appearance-menu-field">
                                                        <span>Ikon secimi</span>
                                                        <select data-menu-icon-select>
                                                            <?php foreach ($menuIconPresets as $icon => $iconLabel): ?>
                                                                <option value="<?= htmlspecialchars($icon) ?>" <?= $selectedIcon === $icon ? 'selected' : '' ?>><?= htmlspecialchars($iconLabel) ?> (<?= htmlspecialchars($icon) ?>)</option>
                                                            <?php endforeach; ?>
                                                            <option value="custom" <?= $selectedIcon !== '' && !isset($menuIconPresets[$selectedIcon]) ? 'selected' : '' ?>>Ozel ikon</option>
                                                            <option value="" <?= $selectedIcon === '' ? 'selected' : '' ?>>Ikonsuz</option>
                                                        </select>
                                                    </label>
                                                    <label class="appearance-menu-field appearance-menu-icon-custom<?= $selectedIcon !== '' && !isset($menuIconPresets[$selectedIcon]) ? ' is-visible' : '' ?>">
                                                        <span>Ikon class</span>
                                                        <input type="text" value="<?= htmlspecialchars($selectedIcon) ?>" placeholder="bi-link-45deg" data-menu-icon>
                                                    </label>
                                                    <div class="appearance-menu-row-actions">
                                                        <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" title="Yukari tasi" data-menu-up><i class="bi bi-arrow-up"></i></button>
                                                        <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" title="Asagi tasi" data-menu-down><i class="bi bi-arrow-down"></i></button>
                                                        <button type="button" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-xs" title="Sil" data-menu-remove><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <template id="appearanceMenuItemTemplate">
                                        <div class="appearance-menu-row" data-menu-row>
                                            <div class="appearance-menu-order">
                                                <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                                                <input type="number" min="1" value="1" aria-label="Sira" data-menu-order>
                                            </div>
                                            <label class="appearance-menu-field">
                                                <span>Menu ismi</span>
                                                <input type="text" value="" placeholder="Orn: Kategoriler" data-menu-label>
                                            </label>
                                            <label class="appearance-menu-field appearance-menu-field-grow">
                                                <span>Menu baglantisi</span>
                                                <input type="text" value="" placeholder="/kategori, {events} veya https://..." data-menu-url>
                                            </label>
                                            <label class="appearance-menu-field">
                                                <span>Ikon secimi</span>
                                                <select data-menu-icon-select>
                                                    <?php foreach ($menuIconPresets as $icon => $iconLabel): ?>
                                                        <option value="<?= htmlspecialchars($icon) ?>"><?= htmlspecialchars($iconLabel) ?> (<?= htmlspecialchars($icon) ?>)</option>
                                                    <?php endforeach; ?>
                                                    <option value="custom">Ozel ikon</option>
                                                    <option value="">Ikonsuz</option>
                                                </select>
                                            </label>
                                            <label class="appearance-menu-field appearance-menu-icon-custom">
                                                <span>Ikon class</span>
                                                <input type="text" value="bi-link-45deg" placeholder="bi-link-45deg" data-menu-icon>
                                            </label>
                                            <div class="appearance-menu-row-actions">
                                                <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" title="Yukari tasi" data-menu-up><i class="bi bi-arrow-up"></i></button>
                                                <button type="button" class="ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs" title="Asagi tasi" data-menu-down><i class="bi bi-arrow-down"></i></button>
                                                <button type="button" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-xs" title="Sil" data-menu-remove><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $hdFirst = false; endforeach; ?>
                <?= adminRenderPanelClose('div') ?>
                <?php endif; ?>

                <?php if ($id === 'lay_footer'): ?>
                <?php
                $footerPreviewControls = [
                    'footer_brand_icon',
                    'footer_column1_enabled',
                    'footer_column2_enabled',
                    'footer_column3_enabled',
                    'footer_show_newsletter',
                    'footer_newsletter_title',
                    'footer_newsletter_text',
                    'footer_newsletter_placeholder',
                    'footer_newsletter_button_icon',
                    'footer_show_meta',
                    'footer_meta_left_icon',
                    'footer_meta_left_text',
                    'footer_meta_right_icon',
                    'footer_meta_right_text',
                ];
                ?>
                <!-- Footer Önizleme -->
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'body_class' => 'admin-card-body-flush',
                    'icon' => 'bi-eye',
                    'title' => 'Footer Önizleme',
                ]) ?>
                        <div class="appearance-footer-preview"
                             data-ui-style-color="--appearance-footer-bg:<?= htmlspecialchars($settings['footer_bg_color'] ?? '#1a2332', ENT_QUOTES, 'UTF-8') ?>;--appearance-footer-text:<?= htmlspecialchars($settings['footer_text_color'] ?? '#94a3b8', ENT_QUOTES, 'UTF-8') ?>">
                            <?php $fLayout = $settings['footer_layout'] ?? 'simple'; ?>
                            <?php if ($fLayout === 'simple'): ?>
                                <div class="appearance-footer-row">
                                    <span class="appearance-footer-brand"><?= htmlspecialchars($settings['footer_brand_text'] ?? 'İçerik Topic') ?></span>
                                    <span><?= htmlspecialchars($settings['footer_description'] ?? '') ?></span>
                                </div>
                            <?php elseif ($fLayout === 'centered'): ?>
                                <div class="appearance-footer-centered">
                                    <div class="appearance-footer-brand"><?= htmlspecialchars($settings['footer_brand_text'] ?? 'İçerik Topic') ?></div>
                                    <div><?= htmlspecialchars($settings['footer_description'] ?? '') ?></div>
                                </div>
                            <?php else: ?>
                                <div class="appearance-footer-grid">
                                    <div>
                                        <div class="appearance-footer-heading"><?= htmlspecialchars($settings['footer_column1_title'] ?? 'Hakkımızda') ?></div>
                                        <div class="appearance-footer-small"><?= htmlspecialchars(mb_substr($settings['footer_column1_content'] ?? '', 0, 80)) ?>...</div>
                                    </div>
                                    <div>
                                        <div class="appearance-footer-heading"><?= htmlspecialchars($settings['footer_column2_title'] ?? 'Hızlı Linkler') ?></div>
                                        <div class="appearance-footer-small">Link 1, Link 2, ...</div>
                                    </div>
                                    <div>
                                        <div class="appearance-footer-heading"><?= htmlspecialchars($settings['footer_column3_title'] ?? 'İletişim') ?></div>
                                        <div class="appearance-footer-small"><?= htmlspecialchars(mb_substr($settings['footer_column3_content'] ?? '', 0, 80)) ?>...</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (($settings['footer_copyright'] ?? '') !== ''): ?>
                                <div class="appearance-footer-copyright"><?= htmlspecialchars($settings['footer_copyright']) ?></div>
                            <?php endif; ?>
                        </div>
                <?= adminRenderPanelClose('div') ?>
                <?php endif; ?>

                <?php if ($id === 'lay_sidebar'): ?>
                <?php
                $sidebarBuilderJson = json_encode($sidebarBuilderConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $sidebarBuilderInitialJson = json_encode($sidebarBuilderConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                $sidebarWidgetCatalogJson = json_encode($sidebarWidgetCatalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                ?>
                <input type="hidden" name="sidebar_builder_config" id="sidebar_builder_config" value="<?= htmlspecialchars((string) $sidebarBuilderJson, ENT_QUOTES, 'UTF-8') ?>">
                <script type="application/json" id="sidebarBuilderInitial"><?= $sidebarBuilderInitialJson ?: '{}' ?></script>
                <script type="application/json" id="sidebarBuilderCatalog"><?= $sidebarWidgetCatalogJson ?: '{}' ?></script>

                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced sidebar-builder-card',
                    'attrs' => ['data-sidebar-builder' => true],
                    'header_class' => 'sidebar-builder-title',
                    'body_class' => 'admin-card-body-flush',
                    'icon' => 'bi-layout-sidebar',
                    'title' => 'Sidebar Builder',
                    'subtitle' => 'Sol ve sag alanlari surukle-birak yonet',
                ]) ?>
                    <div class="sidebar-builder-shell">
                        <div class="sidebar-builder-hero">
                            <div class="sidebar-builder-summary">
                                <div>
                                    <strong>Sidebar kontrol merkezi</strong>
                                    <span>Widget sirasi, sayfa kosullari ve cihaz davranisi tek ekranda.</span>
                                </div>
                                <div class="sidebar-builder-metrics">
                                    <div><strong data-sidebar-metric="catalog">0</strong><span>Widget</span></div>
                                    <div><strong data-sidebar-metric="active">0</strong><span>Aktif</span></div>
                                    <div><strong data-sidebar-metric="conditions">0</strong><span>Kosul</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="sidebar-builder-grid">
                            <aside class="sidebar-builder-library">
                                <div class="sidebar-builder-panel-head">
                                    <div><strong>Widget kutuphanesi</strong><span>Surukle veya Ekle</span></div>
                                </div>
                                <input class="ui-admin-form-control sidebar-builder-search" type="search" placeholder="Widget ara..." data-sidebar-search>
                                <div class="sidebar-builder-library-list" data-sidebar-library></div>
                            </aside>

                            <section class="sidebar-builder-canvas">
                                <div class="sidebar-builder-dropcols">
                                    <div class="sidebar-builder-dropcol" data-sidebar-area="left">
                                        <div class="sidebar-builder-drophead"><strong>Sol Sidebar</strong><span data-sidebar-count="left">0 widget</span></div>
                                        <div class="sidebar-builder-dropzone" data-sidebar-dropzone="left"></div>
                                    </div>
                                    <div class="sidebar-builder-dropcol" data-sidebar-area="right">
                                        <div class="sidebar-builder-drophead"><strong>Sag Sidebar</strong><span data-sidebar-count="right">0 widget</span></div>
                                        <div class="sidebar-builder-dropzone" data-sidebar-dropzone="right"></div>
                                    </div>
                                </div>
                            </section>

                            <aside class="sidebar-builder-inspector">
                                <div class="sidebar-builder-panel-head">
                                    <div><strong>Widget ayarlari</strong><span data-sidebar-selected-label>Secim yok</span></div>
                                </div>
                                <div class="sidebar-builder-inspector-body" data-sidebar-inspector>
                                    <div class="sidebar-builder-empty ui-empty">
                                        <i class="bi bi-cursor"></i>
                                        <strong>Bir widget sec</strong>
                                        <span>Orta alandaki kartlardan birini secince ayarlari burada acilir.</span>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                <?= adminRenderPanelClose('div') ?>

                <?php
                $sidebarSubTabs = [
                    'sb_general'  => ['title' => 'Genel',      'icon' => 'bi-gear',          'keys' => ['sidebar_enabled','sidebar_position','sidebar_width']],
                    'sb_home'     => ['title' => 'Anasayfa',   'icon' => 'bi-house-door',    'keys' => ['sidebar_home_sticky','sidebar_home_template','sidebar_home_popular','sidebar_home_categories','sidebar_home_stats','sidebar_home_custom']],
                    'sb_topic'    => ['title' => 'Konu Detay', 'icon' => 'bi-file-text',     'keys' => ['sidebar_topic_sticky','sidebar_topic_template','sidebar_topic_popular','sidebar_topic_related','sidebar_topic_categories','sidebar_topic_author','sidebar_topic_custom']],
                    'sb_category' => ['title' => 'Kategori',   'icon' => 'bi-folder2-open',  'keys' => ['sidebar_category_sticky','sidebar_category_template','sidebar_category_list','sidebar_category_popular','sidebar_category_stats','sidebar_category_custom']],
                    'sb_search'   => ['title' => 'Arama',      'icon' => 'bi-search',        'keys' => ['sidebar_search_sticky','sidebar_search_template','sidebar_search_filters','sidebar_search_categories','sidebar_search_popular','sidebar_search_custom']],
                    'sb_widget'   => ['title' => 'Widget',     'icon' => 'bi-puzzle',        'keys' => ['sidebar_popular_count','sidebar_popular_sort','sidebar_widget_position']],
                ];
                $sidebarSettingKeys = [];
                foreach ($sidebarSubTabs as $sbTab) {
                    foreach ($sbTab['keys'] as $sbKey) {
                        $sidebarSettingKeys[$sbKey] = true;
                    }
                }
                ?>
                <div hidden aria-hidden="true">
                    <?php foreach (array_keys($sidebarSettingKeys) as $sbKey): ?>
                        <?php
                            if (!isset($definitions[$sbKey])) {
                                continue;
                            }
                            $def = $definitions[$sbKey];
                            $type = (string) ($def['type'] ?? 'text');
                            $value = (string) ($settings[$sbKey] ?? ($def['default'] ?? ''));
                        ?>
                        <?php if ($type === 'bool'): ?>
                            <?php if ($value === '1'): ?><input type="hidden" name="<?= htmlspecialchars($sbKey) ?>" value="1"><?php endif; ?>
                        <?php elseif ($type === 'text'): ?>
                            <textarea name="<?= htmlspecialchars($sbKey) ?>" hidden><?= htmlspecialchars($value) ?></textarea>
                        <?php else: ?>
                            <input type="hidden" name="<?= htmlspecialchars($sbKey) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($id === 'lay_menu'): ?>
                <?php
                $menuSubTabs = [
                    'mn_categories' => ['title' => 'Kategoriler',     'icon' => 'bi-grid',       'keys' => ['menu_show_categories', 'menu_category_limit']],
                    'mn_cta'        => ['title' => 'Aksiyon Butonu',  'icon' => 'bi-megaphone',  'keys' => ['menu_cta_enabled', 'menu_cta_text', 'menu_cta_url', 'menu_cta_color', 'menu_cta_icon']],
                ];
                ?>
                <?= adminRenderPanelOpen([
                    'tag' => 'div',
                    'class' => 'admin-card-spaced',
                    'header_class' => 'profile-mini-row-wrap',
                    'body_class' => 'admin-card-body-flush',
                    'icon' => 'bi-list-nested',
                    'title' => 'Menü Yapılandırması',
                ]) ?>
                        <!-- Sub-tab navigation -->
                        <div class="sidebar-subtabs">
                            <?php $mnFirst = true; foreach ($menuSubTabs as $mnId => $mnTab): ?>
                                <button type="button" class="sidebar-subtab-btn <?= $mnFirst ? 'active' : '' ?>" data-subtab="<?= $mnId ?>">
                                    <i class="bi <?= $mnTab['icon'] ?>"></i><?= $mnTab['title'] ?>
                                </button>
                            <?php $mnFirst = false; endforeach; ?>
                        </div>

                        <!-- Sub-tab contents -->
                        <?php $mnFirst = true; foreach ($menuSubTabs as $mnId => $mnTab): ?>
                        <div class="sidebar-subtab-panel<?= $mnFirst ? ' is-active' : '' ?>" id="<?= $mnId ?>">
                            <div class="admin-appearance-grid p-3 ui-grid">
                                <?php foreach ($mnTab['keys'] as $mnKey): ?>
                                    <?php if (!isset($definitions[$mnKey])) continue; $def = $definitions[$mnKey]; ?>
                                    <div class="<?= $def['type'] === 'text' ? 'admin-field-wide' : '' ?>">
                                        <?php if ($def['type'] === 'bool'): ?>
                                            <label class="ui-admin-switch">
                                                <input type="checkbox" name="<?= htmlspecialchars($mnKey) ?>" value="1" <?= ($settings[$mnKey] ?? '0') === '1' ? 'checked' : '' ?>>
                                                <span class="ui-admin-switch-label"><?= htmlspecialchars($def['label']) ?></span>
                                            </label>
                                        <?php elseif ($def['type'] === 'color'): ?>
                                            <?php
                                                $colorValue = (string) ($settings[$mnKey] ?? '');
                                                $colorDefault = (string) ($def['default'] ?? '#000000');
                                                $resolvedColor = preg_match('/^#[0-9a-fA-F]{6}$/', $colorValue) ? $colorValue : $colorDefault;
                                            ?>
                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($mnKey) ?>"><?= htmlspecialchars($def['label']) ?></label>
                                            <div class="admin-color-field" data-color-field>
                                                <input id="<?= htmlspecialchars($mnKey) ?>" name="<?= htmlspecialchars($mnKey) ?>" type="color" class="admin-color-input" value="<?= htmlspecialchars($resolvedColor) ?>" data-color-input>
                                                <div class="admin-color-meta">
                                                    <strong data-color-value><?= htmlspecialchars(strtoupper($resolvedColor)) ?></strong>
                                                    <span>Mevcut renk<?= $colorValue === '' ? ' (varsayılan)' : '' ?></span>
                                                </div>
                                                <span class="admin-color-default">Varsayılan: <?= htmlspecialchars(strtoupper($colorDefault)) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <label class="ui-admin-form-label" for="<?= htmlspecialchars($mnKey) ?>"><?= htmlspecialchars($def['label']) ?></label>
                                            <?php if ($def['type'] === 'text'): ?>
                                                <textarea id="<?= htmlspecialchars($mnKey) ?>" name="<?= htmlspecialchars($mnKey) ?>" rows="3" class="ui-admin-form-control"><?= htmlspecialchars($settings[$mnKey] ?? '') ?></textarea>
                                            <?php elseif ($def['type'] === 'select'): ?>
                                                <select id="<?= htmlspecialchars($mnKey) ?>" name="<?= htmlspecialchars($mnKey) ?>" class="ui-admin-form-select">
                                                    <?php foreach (($def['options'] ?? []) as $val => $lbl): ?>
                                                        <option value="<?= htmlspecialchars((string)$val) ?>" <?= ($settings[$mnKey] ?? '') === (string)$val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <input id="<?= htmlspecialchars($mnKey) ?>" name="<?= htmlspecialchars($mnKey) ?>" type="<?= $def['type'] === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($settings[$mnKey] ?? '') ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $mnFirst = false; endforeach; ?>
                <?= adminRenderPanelClose('div') ?>

                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="settings-savebar">
        <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i>Ayarları Kaydet</button>
        <a href="<?= $baseUri ?>/index.php?_refresh=<?= time() ?>" target="_blank" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-box-arrow-up-right"></i>Siteyi Önizle</a>
    </div>
</form>

<script src="<?= asset_url('admin/assets/appearance-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>

