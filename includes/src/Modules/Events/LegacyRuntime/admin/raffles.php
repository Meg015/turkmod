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
        header('Location: events-raffles.php');
        exit;
    }

    try {
        if ($action === 'save_raffle') {
            $normalized = eventsNormalizeRaffleInput($_POST);
            if (!$normalized['valid']) {
                $errors = $normalized['errors'];
            } else {
                $data = $normalized['data'];
                $itemIds = $data['item_ids'] ?? [];
                unset($data['item_ids']);
                $raffleId = (int)($_POST['raffle_id'] ?? 0);

                $pdo->beginTransaction();
                if ($raffleId > 0) {
                    $stmt = $pdo->prepare("UPDATE events_raffles SET
                        name = :name, description = :description, start_date = :start_date, end_date = :end_date,
                        draw_date = :draw_date, max_entries_per_user = :max_entries_per_user, winner_count = :winner_count,
                        status = :status, is_active = :is_active, updated_at = NOW()
                        WHERE id = :id");
                    $data['id'] = $raffleId;
                    $stmt->execute($data);
                    $pdo->prepare("DELETE FROM events_raffle_items WHERE raffle_id = ?")->execute([$raffleId]);
                    eventsAuditLog($pdo, 'raffle_update', 'raffle', $raffleId, $data);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO events_raffles
                        (name, description, start_date, end_date, draw_date, max_entries_per_user, winner_count, status, is_active, created_at, updated_at)
                        VALUES (:name, :description, :start_date, :end_date, :draw_date, :max_entries_per_user, :winner_count, :status, :is_active, NOW(), NOW())");
                    $stmt->execute($data);
                    $raffleId = (int)$pdo->lastInsertId();
                    eventsAuditLog($pdo, 'raffle_create', 'raffle', $raffleId, $data);
                }

                if ($itemIds !== []) {
                    $poolStmt = $pdo->prepare("INSERT IGNORE INTO events_raffle_items (raffle_id, item_id, created_at) VALUES (?, ?, NOW())");
                    foreach ($itemIds as $itemId) {
                        $poolStmt->execute([$raffleId, $itemId]);
                    }
                }
                $pdo->commit();
                flash('success', 'Çekiliş kaydedildi.');
                header('Location: events-raffles.php');
                exit;
            }
        }

        if ($action === 'set_status') {
            $raffleId = (int)($_POST['raffle_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            if ($raffleId > 0 && in_array($status, eventsAllowedRaffleStatuses(), true)) {
                $stmt = $pdo->prepare("UPDATE events_raffles SET status = ?, updated_at = NOW() WHERE id = ? AND status != 'drawn'");
                $stmt->execute([$status, $raffleId]);
                eventsAuditLog($pdo, $status === 'cancelled' ? 'raffle_cancel' : 'raffle_update', 'raffle', $raffleId, ['status' => $status]);
                flash('success', 'Çekiliş durumu güncellendi.');
            }
            header('Location: events-raffles.php');
            exit;
        }
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eventsErrorLog($pdo, 'Raffle admin action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'İşlem tamamlanamadı.');
    }
}

eventsAdminStyles($baseUri ?? '');
$raffles = [];
$pools = [];
$editRaffle = null;
$editItemIds = [];
$raffleItemIdsByRaffle = [];
if ($ready) {
    try {
        $raffles = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS entry_count FROM events_raffles r ORDER BY r.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pools = $pdo->query("SELECT id, name, type, value, is_active FROM events_prize_pool_items ORDER BY is_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $raffleIds = array_values(array_filter(array_map('intval', array_column($raffles, 'id'))));
        if ($raffleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($raffleIds), '?'));
            $poolStmt = $pdo->prepare("SELECT raffle_id, item_id FROM events_raffle_items WHERE raffle_id IN ({$placeholders})");
            $poolStmt->execute($raffleIds);
            foreach ($poolStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $raffleItemIdsByRaffle[(int)$row['raffle_id']][] = (int)$row['item_id'];
            }
        }
        $editId = (int)($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM events_raffles WHERE id = ? LIMIT 1");
            $stmt->execute([$editId]);
            $editRaffle = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $poolStmt = $pdo->prepare("SELECT item_id FROM events_raffle_items WHERE raffle_id = ?");
            $poolStmt->execute([$editId]);
            $editItemIds = array_map('intval', $poolStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $raffleItemIdsByRaffle[$editId] = $editItemIds;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Raffle admin list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$raffleFormDefaults = [
    'id' => 0,
    'name' => '',
    'description' => '',
    'start_date' => date('Y-m-d\TH:i'),
    'end_date' => date('Y-m-d\TH:i', strtotime('+7 days')),
    'draw_date' => '',
    'max_entries_per_user' => 1,
    'winner_count' => 1,
    'status' => 'draft',
    'is_active' => 1,
];
$newRaffle = $raffleFormDefaults;
$initialRaffleKey = $editRaffle
    ? 'raffle-' . (int)$editRaffle['id']
    : ($raffles !== [] ? 'raffle-' . (int)$raffles[0]['id'] : 'new');

$raffleDateInput = static function (mixed $value): string {
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
};

$renderRaffleForm = static function (array $raffle, array $selectedItemIds, string $panelKey, bool $isActive, array $items, string $baseUri, callable $dateInput): void {
    $raffleId = (int)($raffle['id'] ?? 0);
    $name = trim((string)($raffle['name'] ?? ''));
    $description = (string)($raffle['description'] ?? '');
    $status = (string)($raffle['status'] ?? 'draft');
    $statusLabel = eventsStatusLabel($status);
    $active = (int)($raffle['is_active'] ?? 1) === 1;
    $entryCount = (int)($raffle['entry_count'] ?? 0);
    $winnerCount = max(1, (int)($raffle['winner_count'] ?? 1));
    $maxEntries = max(1, (int)($raffle['max_entries_per_user'] ?? 1));
    $displayName = $name !== '' ? $name : 'Yeni çekiliş';
    $displayCode = $raffleId > 0 ? 'raffle-' . $raffleId : 'new-raffle';
    ?>
    <form
        class="ui-events-rule-detail-form ui-events-admin-detail-form ui-admin-detail-modal ui-events-admin-detail-modal ui-events-raffle-detail-form<?= $isActive ? ' is-active' : '' ?>"
        method="post"
        action="<?= htmlspecialchars(rtrim($baseUri, '/') . '/admin/events-raffles.php') ?>"
        data-ui-events-raffle-panel="<?= e($panelKey) ?>"
        <?= $isActive ? '' : 'hidden' ?>
    >
        <?= csrf_field() ?>
        <input type="hidden" name="_events_action" value="save_raffle">
        <input type="hidden" name="raffle_id" value="<?= $raffleId ?>">

        <div class="ui-events-rule-detail-head ui-admin-detail-modal-head ui-panel__head">
            <div class="ui-events-rule-detail-titlebar">
                <div>
                    <h3><?= e($displayName) ?></h3>
                    <code><?= e($displayCode) ?></code>
                </div>
                <div class="ui-events-rule-title-actions">
                    <div class="ui-events-rule-badges">
                        <span class="ui-events-badge <?= ($active && $status === 'active') ? 'ui-events-badge-success' : 'ui-events-badge-muted' ?>"><?= e($statusLabel) ?></span>
                        <span class="ui-events-badge ui-events-badge-muted"><?= $entryCount ?> katılım</span>
                    </div>
                    <button class="ui-events-rule-modal-close ui-admin-detail-close" type="button" data-ui-events-raffle-close aria-label="Ayarları kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="ui-events-rule-detail-summary">
                <div class="ui-events-rule-summary-card is-primary ui-card"><span>Katılım</span><strong><?= $entryCount ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Kazanan</span><strong><?= $winnerCount ?></strong></div>
                <div class="ui-events-rule-summary-card ui-card"><span>Durum</span><strong><?= e($statusLabel) ?></strong></div>
            </div>
        </div>

        <div class="ui-events-rule-detail-body ui-admin-detail-modal-body ui-panel__body">
            <div class="ui-events-rule-section-title">Hızlı ayarlar</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field is-wide">
                    <label for="raffle-name-<?= e($panelKey) ?>">Ad</label>
                    <input id="raffle-name-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="name" maxlength="191" value="<?= e($name) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-start-<?= e($panelKey) ?>">Başlangıç</label>
                    <input id="raffle-start-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="start_date" value="<?= e($dateInput($raffle['start_date'] ?? '')) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-end-<?= e($panelKey) ?>">Bitiş</label>
                    <input id="raffle-end-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="end_date" value="<?= e($dateInput($raffle['end_date'] ?? '')) ?>" required>
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-draw-<?= e($panelKey) ?>">Çekim</label>
                    <input id="raffle-draw-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="datetime-local" name="draw_date" value="<?= e($dateInput($raffle['draw_date'] ?? '')) ?>">
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-user-limit-<?= e($panelKey) ?>">Kullanıcı limit</label>
                    <input id="raffle-user-limit-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" type="number" min="1" name="max_entries_per_user" value="<?= $maxEntries ?>" <?= eventsReadableNumberDataAttributes(['key' => 'max_entries_per_user', 'type' => 'number', 'min' => 1]) ?>>
                    <?= eventsRenderReadableNumberValue($maxEntries, ['key' => 'max_entries_per_user', 'type' => 'number', 'min' => 1]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-winner-count-<?= e($panelKey) ?>">Kazanan</label>
                    <input id="raffle-winner-count-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-accent" type="number" min="1" name="winner_count" value="<?= $winnerCount ?>" <?= eventsReadableNumberDataAttributes(['key' => 'winner_count', 'type' => 'number', 'min' => 1]) ?>>
                    <?= eventsRenderReadableNumberValue($winnerCount, ['key' => 'winner_count', 'type' => 'number', 'min' => 1]) ?>
                </div>
                <div class="ui-events-rule-field">
                    <label for="raffle-status-<?= e($panelKey) ?>">Durum</label>
                    <select id="raffle-status-<?= e($panelKey) ?>" class="ui-events-rule-soft-control" name="status">
                        <?php foreach (eventsAllowedRaffleStatuses() as $allowedStatus): ?>
                            <option value="<?= e($allowedStatus) ?>" <?= $status === $allowedStatus ? 'selected' : '' ?>><?= e(eventsStatusLabel($allowedStatus)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Kapsam</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <label class="ui-events-rule-scope-card ui-card" for="raffle-active-<?= e($panelKey) ?>">
                    <span><strong>Aktif</strong><em>Çekiliş kullanıcı tarafında yayınlanır.</em></span>
                    <span class="ui-events-toggle-switch">
                        <input id="raffle-active-<?= e($panelKey) ?>" type="checkbox" name="is_active" value="1"<?= eventsAdminChecked($active) ?>>
                        <span class="ui-events-toggle-slider"></span>
                    </span>
                </label>
                <div class="ui-events-rule-field">
                    <label>Özet</label>
                    <div class="ui-events-rule-soft-control ui-events-raffle-readonly"><?= $entryCount ?> katılım · kullanıcı başı <?= $maxEntries ?></div>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Açıklama</div>
            <div class="ui-events-rule-form-grid ui-grid">
                <div class="ui-events-rule-field is-wide">
                    <label for="raffle-description-<?= e($panelKey) ?>">Açıklama</label>
                    <textarea id="raffle-description-<?= e($panelKey) ?>" class="ui-events-rule-soft-control is-textarea" name="description"><?= e($description) ?></textarea>
                </div>
            </div>

            <div class="ui-events-rule-section-title">Çekiliş Ödülleri</div>
            <div class="ui-events-raffle-pool-grid ui-grid">
                <?php if ($items === []): ?>
                    <?php eventsAdminEmptyState('bi-tags', 'Ödül Kataloğu Boş', 'Önce Ödül Kataloğu sekmesinden ödül oluşturun.'); ?>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php $itemId = (int)$item['id']; ?>
                        <label class="ui-events-rule-scope-card ui-card" for="raffle-item-<?= e($panelKey) ?>-<?= $itemId ?>">
                            <span><strong><?= e((string)$item['name']) ?> (<?= e((string)$item['value']) ?>)</strong><em><?= (int)$item['is_active'] === 1 ? 'Aktif ödül' : 'Pasif ödül' ?></em></span>
                            <span class="ui-events-toggle-switch">
                                <input id="raffle-item-<?= e($panelKey) ?>-<?= $itemId ?>" type="checkbox" name="item_ids[]" value="<?= $itemId ?>"<?= in_array($itemId, $selectedItemIds, true) ? ' checked' : '' ?>>
                                <span class="ui-events-toggle-slider"></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="ui-events-settings-savebar ui-events-form-toolbar ui-events-rule-savebar ui-events-admin-savebar">
            <span>Değişiklik bu çekiliş için uygulanır.</span>
            <button class="ui-admin-btn ui-admin-btn-primary ui-events-rule-save" type="submit"><i class="bi bi-save"></i> Çekilişi Kaydet</button>
        </div>
    </form>
    <?php
};
?>
<div data-ui-events-wheel-ajax-root>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="raffles">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Çekiliş Yönetimi',
        'Çekiliş takvimini, katılım limitlerini, durum geçişlerini ve ödül havuzu eşleşmelerini yönetin.',
        'bi-ticket-perforated'
    );
    ?>

    <div class="admin-card ui-events-admin-panel ui-events-rules-master-shell ui-events-admin-master-card ui-events-raffles-master-shell ui-section ui-panel ui-card">
        <div class="card-body ui-events-rules-master-body ui-events-admin-master-body ui-events-raffles-master-body ui-panel__body">
            <?php eventsAdminErrorList($errors); ?>

            <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-wheel-toolbar data-ui-events-admin-component="actionbar">
                <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Çekiliş yönetimi menüsü">
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-raffles.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-raffle-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                        <i class="bi bi-sliders"></i> Çekiliş Yönetimi
                    </a>
                    <button class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" type="button" data-ui-events-raffle-new="new"><i class="bi bi-plus-lg"></i> Yeni Çekiliş</button>
                    <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-raffles.php?tab=draw') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-raffle-draw" data-ui-events-tab="draw" data-ui-events-wheel-ajax-link><i class="bi bi-shuffle"></i> Çekiliş Çekimi</a>
                </div>
            </div>

            <div class="ui-events-rules-master-layout ui-events-admin-master-layout ui-events-raffles-master-layout ui-section" data-ui-events-raffle-master data-ui-events-admin-master data-ui-events-raffle-initial="<?= e($initialRaffleKey) ?>">
                <div class="ui-events-rules-list-panel ui-events-admin-list-panel ui-events-raffles-list-panel ui-panel" aria-label="Çekilişler listesi">
                    <div class="ui-events-rules-table-head ui-panel__head">
                        <span>Çekiliş</span>
                        <span>Tarih</span>
                        <span>Katılım</span>
                        <span>Kazanan</span>
                        <span>Durum</span>
                        <span>Ayar</span>
                    </div>

                    <?php if ($raffles === []): ?>
                        <?php eventsAdminEmptyState('bi-magic', 'Çekiliş Bulunamadı', 'Sistemde henüz bir çekiliş bulunmuyor. Yeni bir çekiliş ekleyerek başlayın.'); ?>
                    <?php else: ?>
                        <?php foreach ($raffles as $index => $raffle): ?>
                            <?php
                            $raffleId = (int)$raffle['id'];
                            $raffleKey = 'raffle-' . $raffleId;
                            $status = (string)$raffle['status'];
                            $statusKey = (int)$raffle['is_active'] === 1 ? 'active' : 'inactive';
                            $statusClass = ((int)$raffle['is_active'] === 1 && $status === 'active') ? 'ui-events-badge-success' : 'ui-events-badge-muted';
                            $isSelected = ($editRaffle && (int)$editRaffle['id'] === $raffleId) || (!$editRaffle && $index === 0);
                            ?>
                            <button
                                class="ui-events-rule-row ui-events-raffle-row<?= $isSelected ? ' is-selected' : '' ?>"
                                type="button"
                                data-ui-events-raffle-target="<?= e($raffleKey) ?>"
                                data-ui-events-raffle-status="<?= e($statusKey) ?>"
                            >
                                <span class="ui-events-rule-row-name">
                                    <strong><?= e((string)$raffle['name']) ?></strong>
                                    <code><?= e(mb_substr((string)($raffle['description'] ?? ''), 0, 90)) ?></code>
                                </span>
                                <span class="ui-events-rule-row-value"><?= e(eventsFormatDateTime((string)$raffle['start_date'])) ?><br><?= e(eventsFormatDateTime((string)$raffle['end_date'])) ?></span>
                                <span class="ui-events-rule-row-value"><?= (int)$raffle['entry_count'] ?> / <?= (int)$raffle['max_entries_per_user'] ?></span>
                                <span class="ui-events-rule-row-value"><?= (int)$raffle['winner_count'] ?></span>
                                <span class="ui-events-badge <?= $statusClass ?>"><?= e(eventsStatusLabel($status)) ?></span>
                                <span class="ui-events-rule-open-action"><i class="bi bi-sliders"></i><span>Ayarlar</span></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ui-events-rule-detail-panel ui-events-admin-detail-panel ui-events-admin-modal-panel ui-events-admin-detail-overlay ui-events-raffle-detail-panel ui-panel" data-ui-events-raffle-modal data-ui-events-admin-modal role="dialog" aria-modal="true" aria-label="Cekilis ayarlari" hidden>
                    <?php $renderRaffleForm($newRaffle, [], 'new', $initialRaffleKey === 'new', $pools, (string)($baseUri ?? ''), $raffleDateInput); ?>
                    <?php foreach ($raffles as $raffle): ?>
                        <?php $raffleId = (int)$raffle['id']; ?>
                        <?php $raffleKey = 'raffle-' . $raffleId; ?>
                        <?php $renderRaffleForm(array_merge($raffleFormDefaults, $raffle), $raffleItemIdsByRaffle[$raffleId] ?? [], $raffleKey, $initialRaffleKey === $raffleKey, $pools, (string)($baseUri ?? ''), $raffleDateInput); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
