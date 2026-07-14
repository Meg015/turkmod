<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

$ready = eventsTablesReady($pdo ?? null);
$adminId = (int)($_SESSION['_auth_user_id'] ?? 0);

// Handle AJAX requests
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['_events_action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        $errorMsg = 'Güvenlik doğrulaması başarısız.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        flash('error', $errorMsg);
        header('Location: events-pending.php');
        exit;
    }

    try {
        if ($action === 'apply_reward') {
            $rewardId = (int)($_POST['reward_id'] ?? 0);
            $type = (string)($_POST['reward_type'] ?? '');
            $config = eventsGetConfig($pdo, true);

            // If points system is disabled, treat points rewards as custom rewards
            $pointsValid = eventsValidatePointsTarget($config)['valid'];
            if ($type === 'points' && !$pointsValid) {
                $type = 'custom';
            }

            $result = $type === 'points'
                ? eventsApplyPointsReward($pdo, $rewardId, $config, $adminId)
                : eventsApplyCustomReward($pdo, $rewardId, $adminId, (string)($_POST['note'] ?? ''), $baseUri ?? '');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }

            flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Ödül teslim edildi.' : (string)$result['message']);
            header('Location: events-pending.php');
            exit;
        }

        if ($action === 'cancel_reward') {
            $rewardId = (int)($_POST['reward_id'] ?? 0);
            $success = false;
            if ($rewardId > 0) {
                $stmt = $pdo->prepare("UPDATE events_user_rewards SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$rewardId]);
                eventsAuditLog($pdo, 'reward_cancel', 'user_reward', $rewardId, [], $adminId);
                $success = true;
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => $success, 'message' => $success ? 'Ödül iptal edildi.' : 'Ödül bulunamadı.']);
                exit;
            }

            flash('success', 'Ödül iptal edildi.');
            header('Location: events-pending.php');
            exit;
        }

        if ($action === 'bulk_apply_rewards') {
            $rewardIds = (array)($_POST['reward_ids'] ?? []);
            $rewardIds = array_filter(array_map('intval', $rewardIds));
            $config = eventsGetConfig($pdo, true);
            $pointsValid = eventsValidatePointsTarget($config)['valid'];
            $successCount = 0;
            $errorCount = 0;

            foreach ($rewardIds as $rewardId) {
                try {
                    $stmt = $pdo->prepare("SELECT reward_type FROM events_user_rewards WHERE id = ? AND status = 'pending' LIMIT 1");
                    $stmt->execute([$rewardId]);
                    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$reward) continue;

                    $type = (string)$reward['reward_type'];
                    if ($type === 'points' && !$pointsValid) {
                        $type = 'custom';
                    }

                    $result = $type === 'points'
                        ? eventsApplyPointsReward($pdo, $rewardId, $config, $adminId)
                        : eventsApplyCustomReward($pdo, $rewardId, $adminId, '', $baseUri ?? '');

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (Throwable $e) {
                    $errorCount++;
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => $errorCount === 0,
                    'message' => "{$successCount} ödül teslim edildi" . ($errorCount > 0 ? ", {$errorCount} hata oluştu" : '') . '.'
                ]);
                exit;
            }

            flash('success', "{$successCount} ödül teslim edildi.");
            header('Location: events-pending.php');
            exit;
        }

        if ($action === 'bulk_cancel_rewards') {
            $rewardIds = (array)($_POST['reward_ids'] ?? []);
            $rewardIds = array_filter(array_map('intval', $rewardIds));
            $successCount = 0;

            foreach ($rewardIds as $rewardId) {
                $stmt = $pdo->prepare("UPDATE events_user_rewards SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$rewardId]);
                if ($stmt->rowCount() > 0) {
                    eventsAuditLog($pdo, 'reward_cancel', 'user_reward', $rewardId, [], $adminId);
                    $successCount++;
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => "{$successCount} ödül iptal edildi."]);
                exit;
            }

            flash('success', "{$successCount} ödül iptal edildi.");
            header('Location: events-pending.php');
            exit;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Admin pending action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errorMsg = 'İşlem sırasında hata oluştu.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }
        flash('error', $errorMsg);
        header('Location: events-pending.php');
        exit;
    }
}

eventsAdminStyles($baseUri ?? '');
$pendingRewards = [];
$pendingRaffles = [];
$pendingOverview = ['reward_count' => 0, 'raffle_count' => 0, 'total' => 0];

if ($ready) {
    try {
        $pendingOverview = eventsPendingOverview($pdo);
        $pendingRewards = eventsPendingRewardRows($pdo, 150);
        $pendingRaffles = eventsPendingRaffleRows($pdo, 50);
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Admin pending list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$requestedPendingTab = (string)($_GET['tab'] ?? '');
$activePendingTab = in_array($requestedPendingTab, ['raffles', 'rewards'], true) ? $requestedPendingTab : 'raffles';
if ($requestedPendingTab === '') {
    if ($pendingRewards !== []) {
        $activePendingTab = 'rewards';
    } elseif ($pendingRaffles !== []) {
        $activePendingTab = 'raffles';
    }
}
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="pending">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'İşlem Bekleyenler',
        'Etkinlik sisteminde onayınızı veya aksiyonunuzu bekleyen ödüller ve sonuçlanması gereken çekilişler.',
        'bi-bell-fill'
    );
    ?>

    <div class="ui-events-admin-stats ui-events-pending-summary" aria-label="Bekleyen işlem özeti">
        <a class="ui-events-admin-stat ui-events-summary-link" href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php?tab=rewards') ?>">
            <i class="bi bi-gift ui-events-admin-stat-icon" aria-hidden="true"></i>
            <div>
                <strong><?= (int)$pendingOverview['reward_count'] ?></strong>
                <span>Onay bekleyen ödül</span>
            </div>
        </a>
        <a class="ui-events-admin-stat ui-events-summary-link" href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php?tab=raffles') ?>">
            <i class="bi bi-magic ui-events-admin-stat-icon" aria-hidden="true"></i>
            <div>
                <strong><?= (int)$pendingOverview['raffle_count'] ?></strong>
                <span>Çekim bekleyen çekiliş</span>
            </div>
        </a>
        <div class="ui-events-admin-stat">
            <i class="bi bi-bell-fill ui-events-admin-stat-icon" aria-hidden="true"></i>
            <div>
                <strong><?= (int)$pendingOverview['total'] ?></strong>
                <span>Toplam bekleyen işlem</span>
            </div>
        </div>
    </div>

    <div class="ui-events-tabs-shell ui-events-admin-tabs-shell ui-section" data-ui-events-tabs-root data-ui-events-admin-component="tabs">
        <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" role="tablist">
            <a href="#ui-events-admin-tab-pending-raffles" class="ui-events-nav-link ui-events-admin-tab<?= $activePendingTab === 'raffles' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activePendingTab === 'raffles' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-pending-raffles" data-ui-events-tab="raffles">
                <i class="bi bi-magic"></i> Bekleyen Çekilişler
                <?php if ((int)$pendingOverview['raffle_count'] > 0): ?><span class="ui-events-badge ui-events-badge-warning ui-events-nav-count"><?= (int)$pendingOverview['raffle_count'] ?></span><?php endif; ?>
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-admin-tab-pending-rewards" class="ui-events-nav-link ui-events-admin-tab<?= $activePendingTab === 'rewards' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activePendingTab === 'rewards' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-pending-rewards" data-ui-events-tab="rewards">
                <i class="bi bi-gift"></i> Onay Bekleyen Ödüller
                <?php if ((int)$pendingOverview['reward_count'] > 0): ?><span class="ui-events-badge ui-events-badge-warning ui-events-nav-count"><?= (int)$pendingOverview['reward_count'] ?></span><?php endif; ?>
            </a>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-events-admin-tabs-body ui-panel__body ui-panel">

            <!-- RAFFLES TAB -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel<?= $activePendingTab === 'raffles' ? ' is-active' : '' ?> ui-panel" id="ui-events-admin-tab-pending-raffles" role="tabpanel" data-ui-events-tab-panel="raffles"<?= $activePendingTab === 'raffles' ? '' : ' hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($pendingRaffles === []): ?>
                            <?php
                            $rewardTabAction = (int)$pendingOverview['reward_count'] > 0
                                ? '<a class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" href="' . htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php?tab=rewards') . '"><i class="bi bi-gift"></i> Bekleyen Ödülleri Göster</a>'
                                : '';
                            eventsAdminEmptyState('bi-magic', 'Bekleyen Çekiliş Yok', 'Süresi dolmuş ve manuel çekim bekleyen tüm çekilişleri tamamladınız.', $rewardTabAction);
                            ?>
                        <?php else: ?>
                            <table class="ui-events-table">
                                <thead><tr><th>Çekiliş</th><th>Katılımcı</th><th>Stok</th><th>Kazanan</th><th>Durum</th><th>Çekim</th></tr></thead>
                                <tbody>
                                <?php foreach ($pendingRaffles as $raffle): ?>
                                    <?php $canDraw = !empty($raffle['is_draw_ready']); ?>
                                    <tr>
                                        <td><strong><?= e((string)$raffle['name']) ?></strong><br><span class="ui-events-list-meta ui-events-admin-meta-danger">Bitti: <?= e(eventsFormatDateTime((string)$raffle['end_date'])) ?></span></td>
                                        <td><?= (int)$raffle['unique_entry_count'] ?></td>
                                        <td><?= (int)$raffle['prize_stock'] ?></td>
                                        <td><?= (int)$raffle['winner_count'] ?></td>
                                        <td><span class="ui-events-badge <?= $canDraw ? 'ui-events-badge-success' : 'ui-events-badge-warning' ?>"><?= $canDraw ? 'Hazır' : 'Yetersiz' ?></span></td>
                                        <td>
                                            <form class="ui-events-draw-form" data-raffle-id="<?= (int)$raffle['id'] ?>" data-ui-events-confirm="Bu çekiliş için kazananlar belirlenecek ve sonuçlar kaydedilecek." data-ui-events-confirm-title="Çekiliş çekilsin mi?" data-ui-events-confirm-ok="Çekilişi Çek" data-ui-events-confirm-tone="warning">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="raffle_id" value="<?= (int)$raffle['id'] ?>">
                                                <input type="hidden" name="notes" value="İşlem Bekleyenler paneli çekimi">
                                                <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" type="submit" <?= $canDraw ? '' : 'disabled title="Katılımcı veya ödül stoğu yetersiz"' ?>><i class="bi bi-shuffle"></i> Çek</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- REWARDS TAB -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel<?= $activePendingTab === 'rewards' ? ' is-active' : '' ?> ui-panel" id="ui-events-admin-tab-pending-rewards" role="tabpanel" data-ui-events-tab-panel="rewards"<?= $activePendingTab === 'rewards' ? '' : ' hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($pendingRewards === []): ?>
                            <?php
                            $raffleTabAction = (int)$pendingOverview['raffle_count'] > 0
                                ? '<a class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" href="' . htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php?tab=raffles') . '"><i class="bi bi-magic"></i> Bekleyen Çekilişleri Göster</a>'
                                : '';
                            eventsAdminEmptyState('bi-gift', 'Onay Bekleyen Ödül Yok', 'Şu anda sistemde onayınızı bekleyen herhangi bir ödül bulunmuyor.', $raffleTabAction);
                            ?>
                        <?php else: ?>
                            <div class="ui-events-admin-filter-bar ui-events-admin-filter-bar-flush">
                                <input type="text" id="ui-events-rewards-search" class="ui-events-filter-input" placeholder="Kullanıcı adı, email veya ödül ara...">
                                <select id="ui-events-rewards-status-filter" class="ui-events-filter-select">
                                    <option value="all">Tüm Durumlar</option>
                                    <option value="pending">Beklemede</option>
                                </select>
                                <select id="ui-events-rewards-type-filter" class="ui-events-filter-select">
                                    <option value="all">Tüm Kaynaklar</option>
                                    <?php
                                    $sources = array_unique(array_column($pendingRewards, 'source_type'));
                                    foreach($sources as $source): ?>
                                    <option value="<?= e((string)$source) ?>"><?= e(eventsSourceLabel((string)$source)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ui-events-admin-bulkbar">
                                <label class="ui-events-admin-check-label">
                                    <input type="checkbox" id="ui-events-rewards-select-all" class="ui-events-admin-checkbox">
                                    <span>Tümünü Seç</span>
                                </label>
                                <div class="ui-events-admin-spacer"></div>
                                <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" id="ui-events-rewards-apply-all" hidden>
                                    <i class="bi bi-check2"></i> Seçilenleri Teslim Et
                                </button>
                                <button class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm" id="ui-events-rewards-cancel-all" hidden>
                                    <i class="bi bi-x"></i> Seçilenleri İptal Et
                                </button>
                            </div>
                            <table class="ui-events-table">
                                <thead><tr><th class="ui-events-admin-check-cell"><input type="checkbox" class="ui-events-rewards-checkbox-header ui-events-admin-checkbox ui-panel__head"></th><th>Kullanıcı</th><th>Ödül</th><th>Kaynak</th><th>Tarih</th><th>Durum</th><th>İşlem</th></tr></thead>
                                <tbody>
                                <?php foreach ($pendingRewards as $reward): ?>
                                    <tr class="ui-events-reward-row" data-reward-id="<?= (int)$reward['id'] ?>" data-reward-source="<?= e((string)$reward['source_type']) ?>" data-reward-status="<?= e((string)$reward['status']) ?>">
                                        <td class="ui-events-admin-check-cell"><input type="checkbox" class="ui-events-reward-checkbox ui-events-admin-checkbox"></td>
                                        <td><?= e((string)($reward['user_name'] ?? ('#' . $reward['user_id']))) ?><br><span class="ui-events-list-meta"><?= e((string)($reward['user_email'] ?? '')) ?></span></td>
                                        <td>
                                            <strong><?= e((string)$reward['reward_name']) ?></strong>
                                            <span class="ui-events-list-meta ui-events-admin-reward-meta">
                                                <span><?= e((string)$reward['reward_type']) ?></span>
                                                <span class="ui-events-meta-dot" aria-hidden="true">·</span>
                                                <?= eventsAdminPrizeCodeSnippet((string)$reward['reward_value']) ?>
                                            </span>
                                        </td>
                                        <td><span class="ui-events-badge ui-events-badge-muted"><?= e(eventsSourceLabel((string)$reward['source_type'])) ?></span></td>
                                        <td><?= e(eventsFormatDateTime((string)$reward['created_at'])) ?></td>
                                        <td>
                                            <?php if ($reward['status'] === 'pending'): ?>
                                                <span class="ui-events-badge ui-events-badge-warning">Beklemede</span>
                                            <?php elseif ($reward['status'] === 'cancelled'): ?>
                                                <span class="ui-events-badge ui-events-badge-danger">İptal Edildi</span>
                                            <?php else: ?>
                                                <span class="ui-events-badge ui-events-badge-success">Teslim Edildi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="ui-events-action-dropdown">
                                                <button class="ui-events-action-dropdown-btn"><i class="bi bi-three-dots-vertical"></i></button>
                                                <div class="ui-events-action-dropdown-menu">
                                                    <?php if (eventsRewardCanBeAppliedStatus((string)$reward['status'])): ?>
                                                        <?php $applyLabel = $reward['status'] === 'cancelled' ? 'Tekrar Teslim Et' : 'Teslim Et'; ?>
                                                        <form class="ui-events-inline-form" method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php') ?>" data-ui-events-confirm="Bu ödül kullanıcıya teslim edilecek. Devam edilsin mi?" data-ui-events-confirm-title="Ödül teslim edilsin mi?" data-ui-events-confirm-ok="<?= e($applyLabel) ?>" data-ui-events-confirm-tone="success"><?= csrf_field() ?><input type="hidden" name="_events_action" value="apply_reward"><input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>"><input type="hidden" name="reward_type" value="<?= e((string)$reward['reward_type']) ?>"><button class="ui-events-action-dropdown-item" type="submit"><i class="bi bi-check2"></i> <?= e($applyLabel) ?></button></form>
                                                        <?php if ($reward['status'] === 'pending'): ?>
                                                            <form class="ui-events-inline-form" method="post" action="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-pending.php') ?>" data-ui-events-confirm="Bu bekleyen ödül iptal edilecek. Kullanıcıya teslim edilmeyecek." data-ui-events-confirm-title="Ödül iptal edilsin mi?" data-ui-events-confirm-ok="İptal Et" data-ui-events-confirm-tone="danger"><?= csrf_field() ?><input type="hidden" name="_events_action" value="cancel_reward"><input type="hidden" name="reward_id" value="<?= (int)$reward['id'] ?>"><button class="ui-events-action-dropdown-item is-danger" type="submit"><i class="bi bi-x"></i> İptal Et</button></form>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="ui-events-action-dropdown-item is-disabled"><i class="bi bi-dash"></i> İşlem Yok</div>
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
