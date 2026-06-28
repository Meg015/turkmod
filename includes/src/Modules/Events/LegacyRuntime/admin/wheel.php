<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;
$ready = eventsTablesReady($pdo ?? null);
$errors = [];

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['_events_action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: events-wheel.php');
        exit;
    }

    try {
        if ($action === 'save_config') {
            $keys = ['wheel_daily_limit', 'wheel_hourly_limit', 'wheel_min_points', 'wheel_extra_spin_cost', 'wheel_reward_expiry_days'];
            $stmt = $pdo->prepare("INSERT INTO events_config (config_key, config_value, value_type, created_at, updated_at)
                VALUES (:key, :value, 'int', NOW(), NOW())
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()");
            foreach ($keys as $key) {
                $stmt->execute(['key' => $key, 'value' => (string)max(0, (int)($_POST[$key] ?? 0))]);
            }
            eventsAuditLog($pdo, 'wheel_config_update', 'events_config', null, array_intersect_key($_POST, array_flip($keys)));
            flash('success', 'Çark ayarları kaydedildi.');
            header('Location: events-wheel.php');
            exit;
        }

        if ($action === 'save_reward') {
            $normalized = eventsNormalizeWheelRewardInput($_POST);
            if (!$normalized['valid']) {
                $errors = $normalized['errors'];
            } else {
                $data = $normalized['data'];
                $rewardId = (int)($_POST['reward_id'] ?? 0);
                if ($rewardId > 0) {
                    $stmt = $pdo->prepare("UPDATE events_wheel_rewards
                        SET name = :name, type = :type, value = :value, probability = :probability, image_url = :image_url,
                            display_order = :display_order, min_user_points = :min_user_points, quantity = :quantity,
                            remaining_quantity = :remaining_quantity, expires_in_days = :expires_in_days, is_active = :is_active,
                            updated_at = NOW()
                        WHERE id = :id");
                    $data['id'] = $rewardId;
                    $stmt->execute($data);
                    eventsAuditLog($pdo, 'wheel_reward_update', 'wheel_reward', $rewardId, $data);
                    flash('success', 'Çark ödülü güncellendi.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO events_wheel_rewards
                        (name, type, value, probability, image_url, display_order, min_user_points, quantity, remaining_quantity, expires_in_days, is_active, created_at, updated_at)
                        VALUES (:name, :type, :value, :probability, :image_url, :display_order, :min_user_points, :quantity, :remaining_quantity, :expires_in_days, :is_active, NOW(), NOW())");
                    $stmt->execute($data);
                    $rewardId = (int)$pdo->lastInsertId();
                    eventsAuditLog($pdo, 'wheel_reward_create', 'wheel_reward', $rewardId, $data);
                    flash('success', 'Çark ödülü oluşturuldu.');
                }
                header('Location: events-wheel.php');
                exit;
            }
        }

        if ($action === 'toggle_reward') {
            $rewardId = (int)($_POST['reward_id'] ?? 0);
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            if ($rewardId > 0) {
                $stmt = $pdo->prepare("UPDATE events_wheel_rewards SET is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$isActive, $rewardId]);
                eventsAuditLog($pdo, $isActive ? 'wheel_reward_update' : 'wheel_reward_deactivate', 'wheel_reward', $rewardId, ['is_active' => $isActive]);
                flash('success', $isActive ? 'Ödül aktif edildi.' : 'Ödül pasifleştirildi.');
            }
            header('Location: events-wheel.php');
            exit;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Wheel admin action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'İşlem tamamlanamadı.');
    }
}

eventsAdminStyles($baseUri ?? '');

$config = eventsGetConfig($pdo ?? null, true);
$rewards = [];
$editReward = null;
if ($ready) {
    try {
        $rewards = $pdo->query("SELECT * FROM events_wheel_rewards ORDER BY is_active DESC, display_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $editId = (int)($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM events_wheel_rewards WHERE id = ? LIMIT 1");
            $stmt->execute([$editId]);
            $editReward = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Wheel admin list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$totalActiveWeight = 0;
foreach ($rewards as $r) {
    if ((int)$r['is_active'] === 1) {
        $totalActiveWeight += (float)$r['probability'];
    }
}

$rewardFormDefaults = [
    'id' => 0,
    'name' => '',
    'type' => 'custom',
    'value' => '',
    'probability' => '1',
    'image_url' => '',
    'display_order' => '0',
    'min_user_points' => '',
    'quantity' => '',
    'remaining_quantity' => '',
    'expires_in_days' => '',
    'is_active' => 1,
];
$newReward = $rewardFormDefaults;
$initialRewardKey = $editReward
    ? 'reward-' . (int)$editReward['id']
    : ($rewards !== [] ? 'reward-' . (int)$rewards[0]['id'] : 'new');
$requestedPanel = (string)($_GET['panel'] ?? '');
if (in_array($requestedPanel, ['new', 'settings'], true)) {
    $initialRewardKey = $requestedPanel;
}

$wheelStockLabel = static function (array $reward): string {
    if (($reward['remaining_quantity'] ?? null) === null || (string)($reward['remaining_quantity'] ?? '') === '') {
        return 'Sınırsız';
    }

    $quantity = ($reward['quantity'] ?? null) === null || (string)($reward['quantity'] ?? '') === ''
        ? (int)$reward['remaining_quantity']
        : (int)$reward['quantity'];

    return (int)$reward['remaining_quantity'] . ' / ' . $quantity;
};

$wheelChanceLabel = static function (array $reward, float $totalActiveWeight): string {
    if ((int)($reward['is_active'] ?? 1) !== 1) {
        return '-';
    }

    $weight = (float)($reward['probability'] ?? 0);
    if ($totalActiveWeight <= 0 || $weight <= 0) {
        return '%0';
    }

    $percentage = round(($weight / $totalActiveWeight) * 100, 2);
    return '%' . rtrim(rtrim((string)$percentage, '0'), '.');
};

$renderWheelConfigForm = static function (array $config, string $baseUri): void {
    ?>
    <form
        class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal ui-events-wheel-detail-form"
        method="post"
        action="<?= htmlspecialchars(rtrim($baseUri, '/') . '/admin/events-wheel.php') ?>"
        data-ui-events-wheel-panel="settings"
        hidden
    >
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_config">

        <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
            <div class="ui-events-rule-detail-titlebar">
                <div>
                    <h3>Çark ayarları</h3>
                    <code>spin-limits</code>
                </div>
                <div class="ui-events-rule-title-actions">
                    <div class="ui-events-rule-badges">
                        <span class="ui-events-badge ui-events-badge-muted">Limitler</span>
                        <span class="ui-events-badge ui-events-badge-muted">Kullanım</span>
                    </div>
                    <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-wheel-close aria-label="Ayarları kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="ui-events-rule-detail-summary">
                <div class="ui-events-rule-summary-card is-primary ui-card"><span>Günlük limit</span><strong><?= e(eventsConfigReadableNumberValue($config['wheel_daily_limit'] ?? 0, ['key' => 'wheel_daily_limit', 'type' => 'number', 'min' => 0, 'max' => 1000])) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Saatlik limit</span><strong><?= e(eventsConfigReadableNumberValue($config['wheel_hourly_limit'] ?? 0, ['key' => 'wheel_hourly_limit', 'type' => 'number', 'min' => 0, 'max' => 1000])) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Min puan</span><strong><?= e(eventsConfigReadableNumberValue($config['wheel_min_points'] ?? 0, ['key' => 'wheel_min_points', 'type' => 'number', 'min' => 0, 'max' => 1000000000])) ?></strong></div>
            </div>
        </div>

        <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
            <div class="ui-events-rule-section-title">Çark ayarları</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="wheel-daily-limit">Günlük limit</label>
                    <input id="wheel-daily-limit" class="ui-events-rule-soft-control is-accent" type="number" name="wheel_daily_limit" min="0" max="1000" value="<?= e((string)($config['wheel_daily_limit'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'wheel_daily_limit', 'type' => 'number', 'min' => 0, 'max' => 1000]) ?>>
                    <?= eventsRenderReadableNumberValue($config['wheel_daily_limit'] ?? 0, ['key' => 'wheel_daily_limit', 'type' => 'number', 'min' => 0, 'max' => 1000]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-hourly-limit">Saatlik limit</label>
                    <input id="wheel-hourly-limit" class="ui-events-rule-soft-control" type="number" name="wheel_hourly_limit" min="0" max="1000" value="<?= e((string)($config['wheel_hourly_limit'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'wheel_hourly_limit', 'type' => 'number', 'min' => 0, 'max' => 1000]) ?>>
                    <?= eventsRenderReadableNumberValue($config['wheel_hourly_limit'] ?? 0, ['key' => 'wheel_hourly_limit', 'type' => 'number', 'min' => 0, 'max' => 1000]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-min-points">Min puan</label>
                    <input id="wheel-min-points" class="ui-events-rule-soft-control" type="number" name="wheel_min_points" min="0" max="1000000000" value="<?= e((string)($config['wheel_min_points'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'wheel_min_points', 'type' => 'number', 'min' => 0, 'max' => 1000000000]) ?>>
                    <?= eventsRenderReadableNumberValue($config['wheel_min_points'] ?? 0, ['key' => 'wheel_min_points', 'type' => 'number', 'min' => 0, 'max' => 1000000000]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-extra-spin-cost">Ekstra hak puanı</label>
                    <input id="wheel-extra-spin-cost" class="ui-events-rule-soft-control" type="number" name="wheel_extra_spin_cost" min="0" max="1000000000" value="<?= e((string)($config['wheel_extra_spin_cost'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'wheel_extra_spin_cost', 'type' => 'number', 'min' => 0, 'max' => 1000000000]) ?>>
                    <?= eventsRenderReadableNumberValue($config['wheel_extra_spin_cost'] ?? 0, ['key' => 'wheel_extra_spin_cost', 'type' => 'number', 'min' => 0, 'max' => 1000000000]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-expiry-days">Geçerlilik günü</label>
                    <input id="wheel-reward-expiry-days" class="ui-events-rule-soft-control" type="number" name="wheel_reward_expiry_days" min="0" max="3650" value="<?= e((string)($config['wheel_reward_expiry_days'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'wheel_reward_expiry_days', 'type' => 'number', 'min' => 0, 'max' => 3650]) ?>>
                    <?= eventsRenderReadableNumberValue($config['wheel_reward_expiry_days'] ?? 0, ['key' => 'wheel_reward_expiry_days', 'type' => 'number', 'min' => 0, 'max' => 3650]) ?>
                </div>
            </div>
        </div>

        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar">
            <span>Çark kullanım limitleri ve ödül geçerlilik süresi uygulanır.</span>
            <button class="ui-admin-btn ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Ayarları Kaydet</button>
        </div>
    </form>
    <?php
};

$renderWheelRewardForm = static function (array $reward, string $panelKey, bool $isActive, string $baseUri, float $totalActiveWeight, callable $stockLabel, callable $chanceLabel): void {
    $rewardId = (int)($reward['id'] ?? 0);
    $name = trim((string)($reward['name'] ?? ''));
    $type = (string)($reward['type'] ?? 'custom');
    $value = (string)($reward['value'] ?? '');
    $probability = (string)($reward['probability'] ?? '1');
    $statusActive = (int)($reward['is_active'] ?? 1) === 1;
    $displayName = $name !== '' ? $name : 'Yeni çark ödülü';
    $displayValue = $value !== '' ? $value : 'ödül-değeri';
    ?>
    <form
        class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal ui-events-wheel-detail-form<?= $isActive ? ' is-active' : '' ?>"
        method="post"
        action="<?= htmlspecialchars(rtrim($baseUri, '/') . '/admin/events-wheel.php') ?>"
        data-ui-events-wheel-panel="<?= e($panelKey) ?>"
        <?= $isActive ? '' : 'hidden' ?>
    >
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_reward">
        <input type="hidden" name="reward_id" value="<?= $rewardId ?>">

        <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
            <div class="ui-events-rule-detail-titlebar">
                <div>
                    <h3><?= e($displayName) ?></h3>
                    <code><?= e($displayValue) ?></code>
                </div>
                <div class="ui-events-rule-title-actions">
                    <div class="ui-events-rule-badges">
                        <span class="ui-events-badge <?= $statusActive ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= $statusActive ? 'Ödül aktif' : 'Ödül pasif' ?></span>
                        <span class="ui-events-badge ui-events-badge-muted"><?= e($type) ?></span>
                    </div>
                    <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-wheel-close aria-label="Ayarları kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="ui-events-rule-detail-summary">
                <div class="ui-events-rule-summary-card is-primary ui-card"><span>Şans</span><strong><?= e($chanceLabel($reward, $totalActiveWeight)) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Stok</span><strong><?= e($stockLabel($reward)) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Tür</span><strong><?= e($type) ?></strong></div>
            </div>
        </div>

        <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
            <div class="ui-events-rule-section-title">Hızlı ayarlar</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-name-<?= e($panelKey) ?>">Ödül adı</label>
                    <input id="wheel-reward-name-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="name" maxlength="191" value="<?= e($name) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-type-<?= e($panelKey) ?>">Tür</label>
                    <select id="wheel-reward-type-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="type">
                        <?php foreach (eventsAllowedRewardTypes() as $allowedType): ?>
                            <option value="<?= e($allowedType) ?>" <?= $type === $allowedType ? 'selected' : '' ?>><?= e($allowedType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-value-<?= e($panelKey) ?>">Değer</label>
                    <input id="wheel-reward-value-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="value" maxlength="191" value="<?= e($value) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-probability-<?= e($panelKey) ?>">Şans yüzdesi (%)</label>
                    <input id="wheel-reward-probability-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-accent" type="number" step="0.0001" min="0.0001" max="100" name="probability" value="<?= e($probability) ?>" required <?= eventsReadableNumberDataAttributes(['key' => 'probability', 'type' => 'number', 'min' => 0.0001, 'max' => 100]) ?>>
                    <?= eventsRenderReadableNumberValue($probability, ['key' => 'probability', 'type' => 'number', 'min' => 0.0001, 'max' => 100]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-order-<?= e($panelKey) ?>">Sıra</label>
                    <input id="wheel-reward-order-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="display_order" value="<?= e((string)($reward['display_order'] ?? 0)) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'display_order', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue($reward['display_order'] ?? 0, ['key' => 'display_order', 'type' => 'number', 'min' => 0]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-min-points-<?= e($panelKey) ?>">Min puan</label>
                    <input id="wheel-reward-min-points-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="min_user_points" value="<?= e((string)($reward['min_user_points'] ?? '')) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'min_user_points', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue($reward['min_user_points'] ?? '', ['key' => 'min_user_points', 'type' => 'number', 'min' => 0]) ?>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Stok ve kapsam</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-quantity-<?= e($panelKey) ?>">Stok</label>
                    <input id="wheel-reward-quantity-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="quantity" placeholder="Sınırsız" value="<?= e((string)($reward['quantity'] ?? '')) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'sınırsız']]) ?>>
                    <?= eventsRenderReadableNumberValue($reward['quantity'] ?? '', ['key' => 'quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'sınırsız']]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-remaining-<?= e($panelKey) ?>">Kalan</label>
                    <input id="wheel-reward-remaining-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="remaining_quantity" placeholder="Stokla aynı" value="<?= e((string)($reward['remaining_quantity'] ?? '')) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'remaining_quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'stokla aynı']]) ?>>
                    <?= eventsRenderReadableNumberValue($reward['remaining_quantity'] ?? '', ['key' => 'remaining_quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'stokla aynı']]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="wheel-reward-expiry-<?= e($panelKey) ?>">Geçerlilik günü</label>
                    <input id="wheel-reward-expiry-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="expires_in_days" value="<?= e((string)($reward['expires_in_days'] ?? '')) ?>" <?= eventsReadableNumberDataAttributes(['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue($reward['expires_in_days'] ?? '', ['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?>
                </div>
                <label class="ui-events-rule-scope-card ui-card" for="wheel-reward-active-<?= e($panelKey) ?>">
                    <span><strong>Aktif</strong><em>Ödül çarkta yayınlanır.</em></span>
                    <span class="ui-events-toggle-switch">
                        <input id="wheel-reward-active-<?= e($panelKey) ?>" type="checkbox" name="is_active" value="1"<?= eventsAdminChecked($statusActive) ?>>
                        <span class="ui-events-toggle-slider"></span>
                    </span>
                </label>
            </div>

            <div class="ui-events-rule-section-title">Görsel</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field is-wide">
                    <label for="wheel-reward-image-<?= e($panelKey) ?>">Görsel URL</label>
                    <input id="wheel-reward-image-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="image_url" maxlength="500" value="<?= e((string)($reward['image_url'] ?? '')) ?>">
                </div>
            </div>
        </div>

        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar">
            <span>Değişiklik bu ödül dilimi için uygulanır.</span>
            <button class="ui-admin-btn ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Ödülü Kaydet</button>
        </div>
    </form>
    <?php
};
?>
<div data-ui-events-wheel-ajax-root>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="wheel">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Çark Yönetimi',
        'Çevirme limitleri, ödül dilimleri, stok ve şans oranlarını düzenli bir çalışma alanında yönetin.',
        'bi-arrow-clockwise'
    );
    ?>

    <div class="admin-card ui-events-admin-panel ui-events-rules-master-shell ui-events-admin-master-card ui-events-wheel-master-shell ui-section ui-panel ui-card" id="ui-events-admin-tab-wheel-management" role="tabpanel">
        <div class="card-body ui-events-rules-master-body ui-events-admin-master-body ui-events-wheel-master-body ui-panel__body">
            <?php eventsAdminErrorList($errors); ?>

            <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-wheel-toolbar data-ui-events-admin-component="actionbar">
                <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Çark yönetimi menüsü">
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-wheel-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                        <i class="bi bi-sliders"></i> Çark Yönetimi
                    </a>
                    <button class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" type="button" data-ui-events-wheel-new="new"><i class="bi bi-plus-lg"></i> Yeni Ödül</button>
                    <button class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" type="button" data-ui-events-wheel-settings="settings"><i class="bi bi-sliders"></i> Çark Ayarları</button>
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=history') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-wheel-history" data-ui-events-tab="history" data-ui-events-wheel-ajax-link><i class="bi bi-clock-history"></i> Çark Geçmişi</a>
                </div>
            </div>

            <div class="ui-events-rules-master-layout ui-events-admin-master-layout ui-events-wheel-master-layout ui-section" data-ui-events-wheel-master data-ui-events-admin-master data-ui-events-wheel-initial="<?= e($initialRewardKey) ?>"<?= $requestedPanel !== '' ? ' data-ui-events-wheel-open-initial="true"' : '' ?>>
                <div class="ui-events-rules-list-panel ui-events-admin-list-panel ui-events-wheel-list-panel ui-panel" aria-label="Çark ödülleri listesi">
                    <div class="ui-events-rules-table-head ui-panel__head">
                        <span>Ödül</span>
                        <span>Tür</span>
                        <span>Şans</span>
                        <span>Stok</span>
                        <span>Durum</span>
                        <span>Ayar</span>
                    </div>

                    <?php if ($rewards === []): ?>
                        <?php eventsAdminEmptyState('bi-arrow-clockwise', 'Çark Ödülü Bulunamadı', 'Henüz çark ödülü eklenmemiş. Yeni ödül butonuyla ilk dilimi oluşturabilirsiniz.'); ?>
                    <?php else: ?>
                        <?php foreach ($rewards as $index => $reward): ?>
                            <?php
                            $rewardId = (int)$reward['id'];
                            $rewardKey = 'reward-' . $rewardId;
                            $statusKey = (int)$reward['is_active'] === 1 ? 'active' : 'inactive';
                            $isSelected = ($editReward && (int)$editReward['id'] === $rewardId) || (!$editReward && $index === 0);
                            ?>
                            <button
                                class="ui-events-rule-row ui-events-wheel-row<?= $isSelected ? ' is-selected' : '' ?>"
                                type="button"
                                data-ui-events-wheel-target="<?= e($rewardKey) ?>"
                                data-ui-events-wheel-status="<?= e($statusKey) ?>"
                            >
                                <span class="ui-events-rule-row-name">
                                    <strong><?= e((string)$reward['name']) ?></strong>
                                    <code><?= e((string)$reward['value']) ?></code>
                                </span>
                                <span class="ui-events-rule-row-value"><?= e((string)$reward['type']) ?></span>
                                <span class="ui-events-rule-row-value"><?= e($wheelChanceLabel($reward, $totalActiveWeight)) ?></span>
                                <span class="ui-events-rule-row-value"><?= e($wheelStockLabel($reward)) ?></span>
                                <span class="ui-events-badge <?= (int)$reward['is_active'] === 1 ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= (int)$reward['is_active'] === 1 ? 'Ödül aktif' : 'Ödül pasif' ?></span>
                                <span class="ui-events-rule-open-action"><i class="bi bi-sliders"></i><span>Ayarlar</span></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ui-events-rule-detail-panel ui-events-admin-detail-panel ui-events-admin-modal-panel ui-events-admin-detail-overlay ui-events-wheel-detail-panel ui-panel" data-ui-events-wheel-modal data-ui-events-admin-modal role="dialog" aria-modal="true" aria-label="Cark ayarlari" hidden>
                    <?php $renderWheelConfigForm($config, (string)($baseUri ?? '')); ?>
                    <?php $renderWheelRewardForm($newReward, 'new', $initialRewardKey === 'new', (string)($baseUri ?? ''), $totalActiveWeight, $wheelStockLabel, $wheelChanceLabel); ?>
                    <?php foreach ($rewards as $reward): ?>
                        <?php $rewardId = (int)$reward['id']; ?>
                        <?php $rewardKey = 'reward-' . $rewardId; ?>
                        <?php $renderWheelRewardForm(array_merge($rewardFormDefaults, $reward), $rewardKey, $initialRewardKey === $rewardKey, (string)($baseUri ?? ''), $totalActiveWeight, $wheelStockLabel, $wheelChanceLabel); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
