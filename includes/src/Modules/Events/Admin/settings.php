<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTablesReady($pdo ?? null);
$errors = [];
$savedInput = null;

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['_events_action'] ?? '') === 'save_config') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: events-settings.php');
        exit;
    }

    try {
        $normalized = eventsNormalizeConfigInput($_POST);
        $savedInput = $normalized['data'];
        $errors = $normalized['errors'];

        if ($errors === []) {
            $definitions = eventsDefaultConfig();
            $stmt = $pdo->prepare("INSERT INTO events_config (config_key, config_value, value_type, created_at, updated_at)
                VALUES (:key, :value, :type, NOW(), NOW())
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), value_type = VALUES(value_type), updated_at = NOW()");

            foreach ($savedInput as $key => $value) {
                if (!isset($definitions[$key])) {
                    continue;
                }
                $stmt->execute([
                    'key' => $key,
                    'value' => $value,
                    'type' => $definitions[$key]['type'],
                ]);
            }

            eventsAuditLog($pdo, 'config_update', 'events_config', null, [
                'source' => 'ui-events-settings',
                'keys' => array_keys($savedInput),
            ]);
            flash('success', 'Etkinlik ayarları kaydedildi.');
            header('Location: events-settings.php');
            exit;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Events settings save failed.', ['error' => $e->getMessage()], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'Ayarlar kaydedilemedi.');
    }
}

eventsAdminStyles($baseUri ?? '');
$config = eventsGetConfig($pdo ?? null, true);
if ($savedInput) {
    $config = array_replace($config, $savedInput);
}

$sections = eventsConfigUiSections();
$fieldByKey = [];
foreach ($sections as $section) {
    foreach (($section['fields'] ?? []) as $field) {
        $fieldKey = (string)($field['key'] ?? '');
        if ($fieldKey !== '') {
            $fieldByKey[$fieldKey] = $field;
        }
    }
}
$tabs = [
    'general' => [
        'label' => 'Genel',
        'icon' => 'bi-gear',
        'description' => 'Sistem durumu, modül erişimleri ve güvenlik sınırları.',
        'sections' => ['general', 'modules', 'security'],
    ],
    'wheel' => [
        'label' => 'Çark & Çekiliş',
        'icon' => 'bi-arrow-clockwise',
        'description' => 'Çark limitleri, ses davranışı, garanti ve çekiliş otomasyonu.',
        'sections' => ['wheel', 'raffle'],
    ],
    'user' => [
        'label' => 'Kullanıcı',
        'icon' => 'bi-person',
        'description' => 'Aktivite puanı, bildirim, arayüz ve bakım.',
        'sections' => ['activity', 'notifications', 'frontend', 'system'],
    ],
];
$settingsMarkers = 'events_activity_points_enabled Modül Görünürlüğü ' . implode(' ', array_merge(eventsConfigEditableKeys(), array_map(
    static fn(array $section): string => (string)($section['title'] ?? ''),
    $sections
)));

$tabFieldCount = static function (array $tab) use ($sections): int {
    $count = 0;
    foreach (($tab['sections'] ?? []) as $sectionKey) {
        $count += count($sections[(string)$sectionKey]['fields'] ?? []);
    }

    return $count;
};

$enabledModules = [
    'Çark' => eventsConfigBool($config, 'events_wheel_enabled'),
    'Çekiliş' => eventsConfigBool($config, 'events_raffles_enabled'),
    'Görev' => eventsConfigBool($config, 'events_tasks_enabled'),
    'Ödül' => eventsConfigBool($config, 'events_rewards_enabled'),
];
$activeModuleCount = count(array_filter($enabledModules));
$systemEnabled = eventsConfigBool($config, 'events_system_enabled');
$queueEnabled = eventsConfigBool($config, 'email_notifications_enabled') && eventsConfigBool($config, 'email_queue_enabled');
$readableSetting = static function (string $key, string $fallback = '') use ($config, $fieldByKey): string {
    if (!isset($fieldByKey[$key])) {
        return $fallback;
    }

    $value = eventsConfigReadableNumberValue($config[$key] ?? '', $fieldByKey[$key]);
    return $value !== '' ? $value : $fallback;
};
$summaryCards = [
    [
        'label' => 'Sistem',
        'value' => $systemEnabled ? 'Aktif' : 'Kapalı',
        'detail' => $systemEnabled ? 'Etkinlik merkezi erişime açık' : 'Public etkinlik akışı durduruldu',
        'icon' => $systemEnabled ? 'bi-check2-circle' : 'bi-pause-circle',
        'tone' => $systemEnabled ? 'success' : 'danger',
    ],
    [
        'label' => 'Modüller',
        'value' => $activeModuleCount . '/4',
        'detail' => implode(', ', array_keys(array_filter($enabledModules))) ?: 'Hiçbir modül açık değil',
        'icon' => 'bi-grid-1x2',
        'tone' => $activeModuleCount > 0 ? 'info' : 'danger',
    ],
    [
        'label' => 'Çark limiti',
        'value' => 'Günlük ' . $readableSetting('wheel_daily_limit', (string)($config['wheel_daily_limit'] ?? '0')),
        'detail' => 'Saatlik ' . $readableSetting('wheel_hourly_limit', (string)($config['wheel_hourly_limit'] ?? '0')) . ', bekleme ' . $readableSetting('wheel_spin_cooldown_seconds', (string)($config['wheel_spin_cooldown_seconds'] ?? '0') . ' sn'),
        'icon' => 'bi-arrow-clockwise',
        'tone' => 'accent',
    ],
    [
        'label' => 'E-posta & API',
        'value' => $queueEnabled ? 'Kuyruk açık' : 'Kuyruk kapalı',
        'detail' => 'API limit: ' . $readableSetting('api_rate_limit_max', (string)($config['api_rate_limit_max'] ?? '45') . ' istek') . ' / ' . $readableSetting('api_rate_limit_window', (string)($config['api_rate_limit_window'] ?? '60') . ' sn'),
        'icon' => 'bi-diagram-3',
        'tone' => $queueEnabled ? 'info' : 'muted',
    ],
];

$fieldIcon = static function (string $type): string {
    return match ($type) {
        'bool' => 'bi-toggle-on',
        'number' => 'bi-123',
        'select' => 'bi-menu-button-wide',
        'textarea' => 'bi-card-text',
        default => 'bi-input-cursor-text',
    };
};

$renderField = static function (array $field) use ($config, $errors, $ready, $fieldIcon): void {
    $key = (string)$field['key'];
    $type = (string)$field['type'];
    $inputId = 'ui-events-config-' . $key;
    $value = eventsConfigFieldDisplayValue($config, $field);
    $hasError = isset($errors[$key]);
    $isFull = $type === 'textarea' ? ' is-full' : '';
    $isNumber = $type === 'number';
    $disabledAttr = !$ready ? ' disabled' : '';
    $searchText = trim($key . ' ' . (string)($field['label'] ?? '') . ' ' . (string)($field['help'] ?? ''));
    $maxlengthAttr = isset($field['maxlength'])
        ? ' maxlength="' . e((string)$field['maxlength']) . '"'
        : ($type === 'textarea' ? ' maxlength="300"' : '');
    $readableAttrs = $isNumber ? ' ' . eventsReadableNumberDataAttributes($field) : '';
    ?>
    <div class="ui-events-setting-field ui-events-compact-item<?= $isFull ?><?= $hasError ? ' has-error' : '' ?>" data-ui-events-setting-row data-ui-events-setting-text="<?= e($searchText) ?>">
        <div class="ui-events-setting-copy ui-events-compact-header ui-panel__head">
            <span class="ui-events-setting-field-icon" aria-hidden="true"><i class="bi <?= e($fieldIcon($type)) ?>"></i></span>
            <span class="ui-events-setting-copy-text">
                <label for="<?= e($inputId) ?>" class="ui-events-compact-label">
                    <?= e((string)$field['label']) ?>
                </label>
                <?php if (!empty($field['help'])): ?>
                    <small class="ui-events-compact-help"><?= e((string)$field['help']) ?></small>
                <?php endif; ?>
            </span>
        </div>

        <?php if ($type === 'bool'): ?>
            <label class="ui-events-toggle-switch ui-events-settings-toggle" aria-label="<?= e((string)$field['label']) ?>">
                <input id="<?= e($inputId) ?>" type="checkbox" name="<?= e($key) ?>" value="1"<?= eventsAdminChecked(eventsConfigBool($config, $key)) ?><?= $disabledAttr ?>>
                <span class="ui-events-toggle-slider"></span>
            </label>
        <?php else: ?>
            <div class="ui-events-compact-input-wrap">
                <?php if ($type === 'textarea'): ?>
                    <textarea id="<?= e($inputId) ?>" name="<?= e($key) ?>"<?= $maxlengthAttr ?> class="ui-events-compact-input"<?= $disabledAttr ?><?= $hasError ? ' aria-invalid="true"' : '' ?>><?= e($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                    <select id="<?= e($inputId) ?>" name="<?= e($key) ?>" class="ui-events-compact-input ui-events-compact-select"<?= $disabledAttr ?><?= $hasError ? ' aria-invalid="true"' : '' ?>>
                        <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                            <option value="<?= e((string)$optionValue) ?>"<?= (string)$optionValue === $value ? ' selected' : '' ?>><?= e((string)$optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?php
                    $minAttr = $isNumber ? ' min="' . e((string)($field['min'] ?? 0)) . '"' : '';
                    $maxAttr = $isNumber && isset($field['max']) ? ' max="' . e((string)$field['max']) . '"' : '';
                    $stepAttr = $isNumber ? ' step="' . e((string)($field['step'] ?? ($key === 'wheel_pity_threshold' ? '0.1' : '1'))) . '"' : '';
                    ?>
                    <input id="<?= e($inputId) ?>" type="<?= $isNumber ? 'number' : 'text' ?>"<?= $minAttr ?><?= $maxAttr ?><?= $stepAttr ?><?= $maxlengthAttr ?> name="<?= e($key) ?>" value="<?= e($value) ?>" placeholder="<?= e((string)($field['placeholder'] ?? '')) ?>" class="ui-events-compact-input"<?= $readableAttrs ?><?= $disabledAttr ?><?= $hasError ? ' aria-invalid="true"' : '' ?>>
                    <?php if ($isNumber): ?>
                        <?= eventsRenderReadableNumberValue($value, $field) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasError): ?>
            <em class="ui-events-field-error"><?= e((string)$errors[$key]) ?></em>
        <?php endif; ?>
    </div>
    <?php
};

$renderSection = static function (string $sectionKey) use ($sections, $renderField): void {
    if (!isset($sections[$sectionKey])) {
        return;
    }

    $section = $sections[$sectionKey];
    $fields = $section['fields'] ?? [];
    ?>
    <section class="ui-events-admin-panel ui-events-settings-section ui-panel" data-ui-events-settings-section="<?= e($sectionKey) ?>">
        <div class="ui-events-settings-section-head ui-panel__head">
            <span class="ui-events-settings-section-icon"><i class="bi <?= e((string)$section['icon']) ?>"></i></span>
            <div class="ui-events-settings-section-copy">
                <h2><?= e((string)$section['title']) ?></h2>
                <?php if (!empty($section['description'])): ?>
                    <p><?= e((string)$section['description']) ?></p>
                <?php endif; ?>
            </div>
            <span class="ui-events-settings-section-count" data-ui-events-section-count><?= count($fields) ?> ayar</span>
        </div>
        <div class="ui-events-settings-section-body ui-events-compact-grid ui-grid">
            <?php foreach ($fields as $field): ?>
                <?php $renderField($field); ?>
            <?php endforeach; ?>
        </div>
        <div class="ui-events-settings-section-empty" data-ui-events-section-empty hidden>
            Bu grupta aramayla eşleşen ayar yok.
        </div>
    </section>
    <?php
};
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="settings">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Etkinlik Ayarları',
        'Modül görünürlüğünü, çark limitlerini, çekiliş davranışını, bildirimleri ve aktivite puanı seçeneklerini düzenleyin.',
        'bi-sliders2-vertical',
        '<button class="ui-admin-btn ui-admin-btn-primary" type="submit" form="ui-events-settings-form"' . (!$ready ? ' disabled' : '') . '><i class="bi bi-save"></i> Kaydet</button>'
    );
    ?>

    <div class="ui-events-settings-overview" aria-label="Etkinlik ayar özeti">
        <?php foreach ($summaryCards as $card): ?>
            <div class="ui-events-settings-summary-card is-<?= e((string)$card['tone']) ?>">
                <span class="ui-events-settings-summary-icon"><i class="bi <?= e((string)$card['icon']) ?>"></i></span>
                <span>
                    <small><?= e((string)$card['label']) ?></small>
                    <strong><?= e((string)$card['value']) ?></strong>
                    <em><?= e((string)$card['detail']) ?></em>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ui-events-settings-commandbar ui-panel">
        <label class="ui-events-settings-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span class="visually-hidden">Ayar ara</span>
            <input type="search" placeholder="Ayar adı, açıklama veya anahtar ara" data-ui-events-settings-search autocomplete="off">
        </label>
        <div class="ui-events-settings-commandbar-meta" aria-label="Etkinlik ayar durumu">
            <span><i class="bi bi-shield-check"></i> CSRF korumalı</span>
            <span><i class="bi bi-lightning-charge"></i> Anında uygulanır</span>
            <span><i class="bi bi-list-check"></i> <?= count(eventsConfigEditableKeys()) ?> ayar</span>
        </div>
    </div>

    <div class="ui-events-settings-tabs ui-events-admin-tablist" data-ui-events-admin-component="tabs" role="tablist" aria-label="Etkinlik ayar grupları">
        <?php foreach ($tabs as $tabKey => $tab): ?>
            <button class="ui-events-tab-btn ui-events-admin-tab<?= $tabKey === 'general' ? ' active' : '' ?>" data-tab="<?= e($tabKey) ?>" type="button" role="tab" aria-selected="<?= $tabKey === 'general' ? 'true' : 'false' ?>">
                <i class="bi <?= e((string)$tab['icon']) ?>"></i>
                <span>
                    <strong><?= e((string)$tab['label']) ?></strong>
                    <small><?= $tabFieldCount($tab) ?> ayar</small>
                </span>
            </button>
        <?php endforeach; ?>
    </div>

    <form id="ui-events-settings-form" method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-settings.php') ?>" class="ui-events-settings-form ui-events-settings-pro-form ui-events-admin-form" data-settings-markers="<?= e($settingsMarkers) ?>" data-ui-events-settings-form>
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_config">

        <?php eventsAdminErrorList(array_values($errors)); ?>

        <?php foreach ($tabs as $tabKey => $tab): ?>
            <div class="ui-events-tab-content ui-events-admin-tab-panel ui-events-settings-panel ui-section ui-panel<?= $tabKey === 'general' ? ' active' : '' ?>" data-tab="<?= e($tabKey) ?>" role="tabpanel">
                <div class="ui-events-settings-panel-intro">
                    <span><i class="bi <?= e((string)$tab['icon']) ?>"></i></span>
                    <div>
                        <h2><?= e((string)$tab['label']) ?></h2>
                        <p><?= e((string)$tab['description']) ?></p>
                    </div>
                </div>
                <?php foreach (($tab['sections'] ?? []) as $sectionKey): ?>
                    <?php $renderSection((string)$sectionKey); ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="ui-events-settings-empty-search" data-ui-events-settings-empty-search hidden>
            <i class="bi bi-search"></i>
            Arama ile eşleşen ayar bulunamadı.
        </div>

        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-settings-footer-savebar ui-events-admin-savebar ui-panel__foot" data-ui-events-dirty-message="Kaydedilmemiş değişiklikler var. Kaydetmeden çıkarsan değişiklikler uygulanmaz.">
            <span data-ui-events-savebar-message><i class="bi bi-info-circle"></i> Kaydettiğin değişiklikler kullanıcı ekranlarına ve etkinlik işlemlerine hemen uygulanır.</span>
            <button class="ui-admin-btn ui-admin-btn-primary" type="submit"<?= !$ready ? ' disabled' : '' ?>><i class="bi bi-save"></i> Ayarları Kaydet</button>
        </div>
    </form>
</div>
