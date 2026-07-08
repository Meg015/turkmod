<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTablesReady($pdo ?? null);
$errors = [];
$adminId = (int)($_SESSION['_auth_user_id'] ?? 0);

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['_events_action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'GÃ¼venlik doÄŸrulamasÄ± baÅŸarÄ±sÄ±z.');
        header('Location: events-rewards.php');
        exit;
    }

    try {
        if ($action === 'manual_reward') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $rewardInput = eventsNormalizeWheelRewardInput([
                'name' => $_POST['reward_name'] ?? '',
                'type' => $_POST['reward_type'] ?? 'custom',
                'value' => $_POST['reward_value'] ?? '',
                'probability' => '1',
                'is_active' => '1',
            ]);
            if ($userId <= 0) {
                $errors['user_id'] = 'KullanÄ±cÄ± seÃ§imi gerekli.';
            }
            if (!$rewardInput['valid']) {
                $errors = array_merge($errors, $rewardInput['errors']);
            }

            if ($errors === []) {
                $expiresAt = eventsCalculateExpiryAt(trim((string)($_POST['expires_in_days'] ?? '')) === '' ? null : (int)$_POST['expires_in_days']);
                $stmt = $pdo->prepare("INSERT INTO events_user_rewards (user_id, source_type, source_id, reward_name, reward_type, reward_value, status, expires_at, created_at, updated_at)
                    VALUES (?, 'admin', NULL, ?, ?, ?, 'pending', ?, NOW(), NOW())");
                $stmt->execute([$userId, $rewardInput['data']['name'], $rewardInput['data']['type'], $rewardInput['data']['value'], $expiresAt]);
                $rewardId = (int)$pdo->lastInsertId();
                eventsAuditLog($pdo, 'reward_claim', 'user_reward', $rewardId, ['manual_create' => true], $adminId);
                flash('success', 'Manuel Ã¶dÃ¼l oluÅŸturuldu.');
                header('Location: events-rewards.php?tab=distributed');
                exit;
            }
        }

        if ($action === 'apply_reward') {
            $rewardId = (int)($_POST['reward_id'] ?? 0);
            $type = (string)($_POST['reward_type'] ?? '');
            $result = $type === 'points'
                ? eventsApplyPointsReward($pdo, $rewardId, eventsGetConfig($pdo, true), $adminId)
                : eventsApplyCustomReward($pdo, $rewardId, $adminId, (string)($_POST['note'] ?? ''), $baseUri ?? '');
                flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Ã–dÃ¼l teslim edildi.' : (string)$result['message']);
            header('Location: events-rewards.php?tab=distributed');
            exit;
        }

        if ($action === 'cancel_reward') {
            $rewardId = (int)($_POST['reward_id'] ?? 0);
            if ($rewardId > 0) {
                $stmt = $pdo->prepare("UPDATE events_user_rewards SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$rewardId]);
                eventsAuditLog($pdo, 'reward_cancel', 'user_reward', $rewardId, [], $adminId);
                flash('success', 'Ã–dÃ¼l iptal edildi.');
            }
            header('Location: events-rewards.php?tab=distributed');
            exit;
        }

        // Ã–dÃ¼l KataloÄŸu KayÄ±t/GÃ¼ncelleme
        if ($action === 'save_item') {
            $normalized = eventsNormalizePrizePoolItemInput($_POST);
            if (!$normalized['valid']) {
                $errors = $normalized['errors'];
            } else {
                $data = $normalized['data'];
                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId > 0) {
                    $data['id'] = $itemId;
                    $stmt = $pdo->prepare("UPDATE events_prize_pool_items SET
                        name = :name, type = :type, value = :value, quantity = :quantity,
                        remaining_quantity = :remaining_quantity, weight = :weight, description = :description,
                        expires_in_days = :expires_in_days, is_active = :is_active, updated_at = NOW()
                        WHERE id = :id");
                    $stmt->execute($data);
                    eventsAuditLog($pdo, 'pool_item_update', 'pool_item', $itemId, $data);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO events_prize_pool_items
                        (name, type, value, quantity, remaining_quantity, weight, description, expires_in_days, is_active, created_at, updated_at)
                        VALUES (:name, :type, :value, :quantity, :remaining_quantity, :weight, :description, :expires_in_days, :is_active, NOW(), NOW())");
                    $stmt->execute($data);
                    $itemId = (int)$pdo->lastInsertId();
                    eventsAuditLog($pdo, 'pool_item_create', 'pool_item', $itemId, $data);
                }
                flash('success', 'Ã–dÃ¼l kataloÄŸa kaydedildi.');
                header('Location: events-rewards.php?tab=catalog');
                exit;
            }
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Admin reward action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'Ä°ÅŸlem tamamlanamadÄ±.');
    }
}

eventsAdminStyles($baseUri ?? '');
$rewards = [];
$users = [];
$catalogItems = [];
$editItem = null;

$activeTab = $_GET['tab'] ?? 'catalog';

if ($ready) {
    try {
        eventsExpirePendingRewards($pdo, $adminId);
        $rewards = $pdo->query("SELECT ur.*, u.username AS user_name, u.email AS user_email FROM events_user_rewards ur LEFT JOIN users u ON u.id = ur.user_id ORDER BY ur.id DESC LIMIT 150")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = $pdo->query("SELECT id, username, email FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 250")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $catalogItems = $pdo->query("SELECT * FROM events_prize_pool_items ORDER BY is_active DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $editItemId = (int)($_GET['edit_item'] ?? 0);
        if ($editItemId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM events_prize_pool_items WHERE id = ? LIMIT 1");
            $stmt->execute([$editItemId]);
            $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($editItem) $activeTab = 'catalog';
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Admin rewards list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$poolItemFormDefaults = [
    'id' => 0,
    'name' => '',
    'type' => 'custom',
    'value' => '',
    'quantity' => 1,
    'remaining_quantity' => '',
    'weight' => 1,
    'description' => '',
    'expires_in_days' => '',
    'is_active' => 1,
];
$newItem = $poolItemFormDefaults;
$initialItemKey = $editItem
    ? 'item-' . (int)$editItem['id']
    : ($catalogItems !== [] ? 'item-' . (int)$catalogItems[0]['id'] : 'new');

$renderItemForm = static function (array $item, string $panelKey, bool $isActive, string $baseUri): void {
    $itemId = (int)($item['id'] ?? 0);
    $name = trim((string)($item['name'] ?? ''));
    $type = (string)($item['type'] ?? 'custom');
    $statusActive = (int)($item['is_active'] ?? 1) === 1;
    $displayTitle = $name !== '' ? $name : 'Yeni Ã¶dÃ¼l';
    $displayId = $itemId > 0 ? 'item-' . $itemId : 'new-item';
    ?>
    <form
        class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal ui-events-item-detail-form<?= $isActive ? ' is-active' : '' ?>"
        method="post"
        action="<?= htmlspecialchars(rtrim($baseUri, '/') . '/admin/events-rewards.php?tab=catalog') ?>"
        data-ui-events-item-panel="<?= e($panelKey) ?>"
        <?= $isActive ? '' : 'hidden' ?>
    >
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_item">
        <input type="hidden" name="item_id" value="<?= $itemId ?>">

        <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
            <div class="ui-events-rule-detail-titlebar">
                <div>
                    <h3><?= e($displayTitle) ?></h3>
                    <code><?= e($displayId) ?></code>
                </div>
                <div class="ui-events-rule-title-actions">
                    <div class="ui-events-rule-badges">
                        <span class="ui-events-badge <?= $statusActive ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= $statusActive ? 'Aktif' : 'Pasif' ?></span>
                    </div>
                    <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-item-close aria-label="AyarlarÄ± kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="ui-events-rule-detail-summary">
                <div class="ui-events-rule-summary-card is-primary ui-card"><span>DeÄŸer</span><strong><?= e((string)$item['value']) ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Kalan Stok</span><strong><?= trim((string)$item['remaining_quantity']) === '' ? 'SÄ±nÄ±rsÄ±z' : (int)$item['remaining_quantity'] ?> / <?= (int)$item['quantity'] ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>TÃ¼r</span><strong><?= e($type) ?></strong></div>
            </div>
        </div>

        <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
            <div class="ui-events-rule-section-title">HÄ±zlÄ± ayarlar</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field is-wide">
                    <label for="item-name-<?= e($panelKey) ?>">Ã–dÃ¼l AdÄ±</label>
                    <input id="item-name-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="name" maxlength="191" value="<?= e($name) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="item-type-<?= e($panelKey) ?>">TÃ¼r</label>
                    <select id="item-type-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="type" required>
                        <?php foreach (eventsAllowedRewardTypes() as $t): ?>
                            <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-events-rule-field">
                    <label for="item-value-<?= e($panelKey) ?>">Ã–dÃ¼l DeÄŸeri</label>
                    <input id="item-value-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="value" value="<?= e((string)$item['value']) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="item-quantity-<?= e($panelKey) ?>">BaÅŸlangÄ±Ã§ StoÄŸu</label>
                    <input id="item-quantity-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="quantity" value="<?= (int)$item['quantity'] ?>" required <?= eventsReadableNumberDataAttributes(['key' => 'quantity', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue((int)$item['quantity'], ['key' => 'quantity', 'type' => 'number', 'min' => 0]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="item-remaining-<?= e($panelKey) ?>">Kalan Stok</label>
                    <input id="item-remaining-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="remaining_quantity" value="<?= trim((string)$item['remaining_quantity']) === '' ? '' : (int)$item['remaining_quantity'] ?>" placeholder="Stokla aynÄ±" <?= eventsReadableNumberDataAttributes(['key' => 'remaining_quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'stokla aynÄ±']]) ?>>
                    <?= eventsRenderReadableNumberValue(trim((string)$item['remaining_quantity']) === '' ? '' : (int)$item['remaining_quantity'], ['key' => 'remaining_quantity', 'type' => 'number', 'min' => 0, 'display' => ['zeroLabel' => 'stokla aynÄ±']]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="item-expires-<?= e($panelKey) ?>">GeÃ§erlilik gÃ¼nÃ¼</label>
                    <input id="item-expires-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="0" name="expires_in_days" value="<?= trim((string)$item['expires_in_days']) === '' ? '' : (int)$item['expires_in_days'] ?>" placeholder="SÃ¼resiz" <?= eventsReadableNumberDataAttributes(['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?>>
                    <?= eventsRenderReadableNumberValue(trim((string)$item['expires_in_days']) === '' ? '' : (int)$item['expires_in_days'], ['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Kapsam</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <label class="ui-events-rule-scope-card ui-card" for="item-active-<?= e($panelKey) ?>">
                    <span><strong>Aktif</strong><em>Bu Ã¶dÃ¼l Ã§ekiliÅŸlerde seÃ§ilebilir.</em></span>
                    <span class="ui-events-toggle-switch">
                        <input id="item-active-<?= e($panelKey) ?>" type="checkbox" name="is_active" value="1"<?= eventsAdminChecked($statusActive) ?>>
                        <span class="ui-events-toggle-slider"></span>
                    </span>
                </label>
            </div>
            
            <div class="ui-events-rule-section-title">AÃ§Ä±klama</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field is-wide">
                    <label for="item-desc-<?= e($panelKey) ?>">AÃ§Ä±klama</label>
                    <textarea id="item-desc-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-textarea" name="description"><?= e((string)($item['description'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar" data-ui-events-dirty-message="Bu Ã¶dÃ¼lde kaydedilmemiÅŸ deÄŸiÅŸiklikler var.">
            <span data-ui-events-savebar-message>DeÄŸiÅŸiklik bu Ã¶dÃ¼l iÃ§in uygulanÄ±r.</span>
            <button class="ui-admin-btn ui-admin-btn-primary ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Ã–dÃ¼lÃ¼ Kaydet</button>
        </div>
    </form>
    <?php
};
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="rewards">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Ã–dÃ¼l Merkezi',
        'Ã‡ekiliÅŸlerde verilecek Ã¶dÃ¼lleri katalogda oluÅŸturun ve daÄŸÄ±tÄ±lan Ã¶dÃ¼lleri takip edin.',
        'bi-gift'
    );
    ?>

    <div class="ui-events-tabs-shell ui-events-admin-tabs-shell ui-section" data-ui-events-tabs-root data-ui-events-admin-component="tabs">
        <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" role="tablist">
            <a href="#ui-events-admin-tab-rewards-catalog" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'catalog' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'catalog' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-rewards-catalog" data-ui-events-tab="catalog">
                <i class="bi bi-tags"></i> Ã–dÃ¼l KataloÄŸu
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-admin-tab-rewards-distributed" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'distributed' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'distributed' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-rewards-distributed" data-ui-events-tab="distributed">
                <i class="bi bi-box-seam"></i> DaÄŸÄ±tÄ±lan Ã–dÃ¼ller
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-admin-tab-rewards-manual" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'manual' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'manual' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-rewards-manual" data-ui-events-tab="manual">
                <i class="bi bi-plus-circle"></i> Manuel Ã–dÃ¼l Ver
            </a>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-events-admin-tabs-body ui-panel__body ui-panel">

            <!-- Ã–DÃœL KATALOÄU SEKME -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'catalog' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-rewards-catalog" role="tabpanel" data-ui-events-tab-panel="catalog" <?= $activeTab === 'catalog' ? '' : 'hidden' ?>>
                
                <div class="admin-card ui-events-admin-panel ui-events-rules-master-shell ui-section ui-panel">
                    <div class="card-body ui-events-rules-master-body ui-events-admin-master-body ui-panel__body">
                        <?php eventsAdminErrorList($errors); ?>

                        <div class="ui-events-master-actionbar ui-events-admin-actionbar" data-ui-events-item-toolbar data-ui-events-admin-component="actionbar">
                            <button class="ui-admin-btn ui-admin-btn-primary" type="button" data-ui-events-item-new="new"><i class="bi bi-plus-lg"></i> Yeni Ã¶dÃ¼l</button>
                        </div>

                        <div class="ui-events-rules-master-layout ui-events-admin-master-layout ui-section" data-ui-events-item-master data-ui-events-admin-master data-ui-events-item-initial="<?= e($initialItemKey) ?>">
                            <div class="ui-events-rules-list-panel ui-events-admin-list-panel ui-panel" aria-label="Ã–dÃ¼ller listesi">
                                <div class="ui-events-rules-table-head ui-panel__head">
                                    <span>Ã–dÃ¼l AdÄ±</span>
                                    <span>TÃ¼r</span>
                                    <span>DeÄŸer</span>
                                    <span>Kalan Stok</span>
                                    <span>Durum</span>
                                    <span>Ayar</span>
                                </div>

                                <?php if ($catalogItems === []): ?>
                                    <?php eventsAdminEmptyState('bi-tags', 'Ã–dÃ¼l BulunamadÄ±', 'Katalogda henÃ¼z bir Ã¶dÃ¼l oluÅŸturmadÄ±nÄ±z.'); ?>
                                <?php else: ?>
                                    <?php foreach ($catalogItems as $index => $item): ?>
                                        <?php
                                        $itemId = (int)$item['id'];
                                        $itemKey = 'item-' . $itemId;
                                        $isSelected = ($editItem && (int)$editItem['id'] === $itemId) || (!$editItem && $index === 0);
                                        ?>
                                        <button
                                            class="ui-events-rule-row ui-events-task-row<?= $isSelected ? ' is-selected' : '' ?>"
                                            type="button"
                                            data-ui-events-item-target="<?= e($itemKey) ?>"
                                        >
                                            <span class="ui-events-rule-row-name">
                                                <strong><?= e((string)$item['name']) ?></strong>
                                            </span>
                                            <span class="ui-events-rule-row-value"><span class="ui-events-badge ui-events-badge-muted"><?= e((string)$item['type']) ?></span></span>
                                            <span class="ui-events-rule-row-value"><?= e((string)$item['value']) ?></span>
                                            <span class="ui-events-rule-row-value"><?= trim((string)$item['remaining_quantity']) === '' ? 'SÄ±nÄ±rsÄ±z' : ((int)$item['remaining_quantity'] . ' / ' . (int)$item['quantity']) ?></span>
                                            <span class="ui-events-badge <?= (int)$item['is_active'] === 1 ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= (int)$item['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span>
                                            <span class="ui-events-rule-open-action"><i class="bi bi-sliders"></i><span>Ayarlar</span></span>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="ui-events-rule-detail-panel ui-events-admin-detail-panel ui-events-admin-modal-panel ui-events-admin-detail-overlay ui-panel" data-ui-events-item-modal data-ui-events-admin-modal role="dialog" aria-modal="true" aria-label="Odul ayarlari" hidden>
                                <?php $renderItemForm($newItem, 'new', $initialItemKey === 'new', $baseUri ?? ''); ?>
                                <?php foreach ($catalogItems as $item): ?>
                                    <?php $itemKey = 'item-' . (int)$item['id']; ?>
                                    <?php $renderItemForm(array_merge($poolItemFormDefaults, $item), $itemKey, $initialItemKey === $itemKey, $baseUri ?? ''); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MANUEL Ã–DÃœL VER SEKME -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'manual' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-rewards-manual" role="tabpanel" data-ui-events-tab-panel="manual" <?= $activeTab === 'manual' ? '' : 'hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-events-admin-panel-spaced ui-panel">
                    <?php eventsAdminPanelHeader('bi-plus-circle', 'Manuel Ã–dÃ¼l Ver', 'Belirli bir kullanÄ±cÄ± iÃ§in anÄ±nda Ã¶dÃ¼l atayÄ±n.'); ?>
                    <div class="card-body ui-events-admin-panel-body ui-panel__body ui-panel">
                        <form method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-rewards.php?tab=manual') ?>">
                            <?= csrf_field() ?><input type="hidden" name="_events_action" value="manual_reward">
                            <div class="ui-events-rule-form-grid ui-grid">
                                <div class="ui-events-rule-field"><label>KullanÄ±cÄ±</label><select class="ui-events-rule-soft-control" name="user_id" required><option value="">SeÃ§in</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>">#<?= (int)$user['id'] ?> <?= e((string)$user['username']) ?> Â· <?= e((string)$user['email']) ?></option><?php endforeach; ?></select></div>
                                <div class="ui-events-rule-field"><label>Ã–dÃ¼l adÄ±</label><input class="ui-events-rule-soft-control" name="reward_name" required></div>
                                <div class="ui-events-rule-field"><label>TÃ¼r</label><select class="ui-events-rule-soft-control" name="reward_type"><?php foreach (eventsAllowedRewardTypes() as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
                                <div class="ui-events-rule-field"><label>DeÄŸer</label><input class="ui-events-rule-soft-control" name="reward_value" required></div>
                                <div class="ui-events-rule-field"><label>GeÃ§erlilik gÃ¼nÃ¼</label><input class="ui-events-rule-soft-control" type="number" min="0" name="expires_in_days" placeholder="SÃ¼resiz" <?= eventsReadableNumberDataAttributes(['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?>><?= eventsRenderReadableNumberValue('', ['key' => 'expires_in_days', 'type' => 'number', 'min' => 0]) ?></div>
                            </div>
                            <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-admin-savebar"><span data-ui-events-savebar-message>Manuel Ã¶dÃ¼l tek kullanÄ±cÄ± iÃ§in oluÅŸturulur.</span><button class="ui-admin-btn ui-admin-btn-primary" type="submit"><i class="bi bi-gift"></i> Ã–dÃ¼l OluÅŸtur</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- DAÄITILAN Ã–DÃœLLER SEKME -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'distributed' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-rewards-distributed" role="tabpanel" data-ui-events-tab-panel="distributed" <?= $activeTab === 'distributed' ? '' : 'hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <?php eventsAdminPanelHeader('bi-gift', 'DaÄŸÄ±tÄ±lan KullanÄ±cÄ± Ã–dÃ¼lleri', 'Ã‡ekiliÅŸten, Ã§arktan veya manuel kazanÄ±lmÄ±ÅŸ tÃ¼m Ã¶dÃ¼ller.'); ?>
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($rewards === []): ?>
                            <?php eventsAdminEmptyState('bi-gift', 'DaÄŸÄ±tÄ±lan Ã–dÃ¼l Yok', 'KullanÄ±cÄ±lara henÃ¼z hiÃ§ Ã¶dÃ¼l verilmedi.'); ?>
                        <?php else: ?>
                            <div class="ui-events-admin-filter-bar ui-events-admin-filter-bar-flush">
                                <input type="text" id="ui-events-rewards-search" class="ui-events-filter-input" placeholder="KullanÄ±cÄ± adÄ±, email veya Ã¶dÃ¼l ara...">
                                <select id="ui-events-rewards-status-filter" class="ui-events-filter-select">
                                    <option value="all">TÃ¼m Durumlar</option>
                                    <option value="pending">Beklemede</option>
                                    <option value="claimed">Teslim Edildi</option>
                                    <option value="cancelled">Ä°ptal Edilenler</option>
                                </select>
                                <select id="ui-events-rewards-type-filter" class="ui-events-filter-select">
                                    <option value="all">TÃ¼m Kaynaklar</option>
                                    <?php 
                                    $sources = array_unique(array_column($rewards, 'source_type'));
                                    foreach($sources as $source): ?>
                                    <option value="<?= e((string)$source) ?>"><?= e(eventsSourceLabel((string)$source)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <table class="ui-events-table">
                                <thead><tr><th>KullanÄ±cÄ±</th><th>Ã–dÃ¼l</th><th>Kaynak</th><th>Durum</th><th>Tarih</th><th>Ä°ÅŸlem</th></tr></thead>
                                <tbody>
                                <?php foreach ($rewards as $reward): ?>
                                    <tr class="ui-events-reward-row" data-reward-source="<?= e((string)$reward['source_type']) ?>" data-reward-status="<?= e((string)$reward['status']) ?>">
                                        <td><?= e((string)($reward['user_name'] ?? ('#' . $reward['user_id']))) ?><br><span class="ui-events-list-meta"><?= e((string)($reward['user_email'] ?? '')) ?></span></td>
                                        <td>
                                            <strong><?= e((string)$reward['reward_name']) ?></strong>
                                            <span class="ui-events-list-meta ui-events-admin-reward-meta">
                                                <span><?= e((string)$reward['reward_type']) ?></span>
                                                <span class="ui-events-meta-dot" aria-hidden="true">Â·</span>
                                                <?= eventsAdminPrizeCodeSnippet((string)$reward['reward_value']) ?>
                                            </span>
                                        </td>
                                        <td><span class="ui-events-badge ui-events-badge-muted"><?= e(eventsSourceLabel((string)$reward['source_type'])) ?></span></td>
                                        <td><span class="ui-events-badge <?= (string)$reward['status'] === 'claimed' ? 'ui-events-badge-success' : ((string)$reward['status'] === 'pending' ? 'ui-events-badge-warning' : 'ui-events-badge-muted') ?>"><?= e(eventsStatusLabel((string)$reward['status'])) ?></span></td>
                                        <td><?= e(eventsFormatDateTime((string)$reward['created_at'])) ?></td>
                                        <td>
                                            <div class="ui-events-action-dropdown">
                                                <button class="ui-events-action-dropdown-btn"><i class="bi bi-three-dots-vertical"></i></button>
                                                <div class="ui-events-action-dropdown-menu">
                                                    <?php if (eventsRewardCanBeAppliedStatus((string)$reward['status'])): ?>
                                                        <?php $applyLabel = (string)$reward['status'] === 'cancelled' ? 'Tekrar Teslim Et' : 'Teslim Et'; ?>
                                                        <form class="ui-events-inline-form" method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-rewards.php?tab=distributed') ?>" data-ui-events-confirm="Bu Ã¶dÃ¼l kullanÄ±cÄ±ya teslim edilecek. Devam edilsin mi?" data-ui-events-confirm-title="Ã–dÃ¼l teslim edilsin mi?" data-ui-events-confirm-ok="<?= e($applyLabel) ?>" data-ui-events-confirm-tone="success"><?= csrf_field() ?><input type="hidden" name="_events_action" value="apply_reward"><input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>"><input type="hidden" name="reward_type" value="<?= e((string)$reward['reward_type']) ?>"><button class="ui-events-action-dropdown-item" type="submit"><i class="bi bi-check2"></i> <?= e($applyLabel) ?></button></form>
                                                        <?php if ((string)$reward['status'] === 'pending'): ?>
                                                            <form class="ui-events-inline-form" method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-rewards.php?tab=distributed') ?>" data-ui-events-confirm="Bu bekleyen Ã¶dÃ¼l iptal edilecek. KullanÄ±cÄ±ya teslim edilmeyecek." data-ui-events-confirm-title="Ã–dÃ¼l iptal edilsin mi?" data-ui-events-confirm-ok="Ä°ptal Et" data-ui-events-confirm-tone="danger"><?= csrf_field() ?><input type="hidden" name="_events_action" value="cancel_reward"><input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>"><button class="ui-events-action-dropdown-item is-danger" type="submit"><i class="bi bi-x"></i> Ä°ptal Et</button></form>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="ui-events-action-dropdown-item is-disabled"><i class="bi bi-dash"></i> Ä°ÅŸlem Yok</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

