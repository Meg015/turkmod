<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

eventsAdminStyles($baseUri ?? '');
$ready = eventsTablesReady($pdo ?? null);
$candidates = [];
$recentDraws = [];
if ($ready) {
    try {
        $candidatesRaw = $pdo->query("SELECT r.*,
                (SELECT COUNT(DISTINCT e.user_id) FROM events_raffle_entries e WHERE e.raffle_id = r.id) AS unique_entry_count,
                (SELECT COALESCE(SUM(i.remaining_quantity), 0)
                    FROM events_prize_pool_items i
                    INNER JOIN events_raffle_items ri ON ri.item_id = i.id
                    WHERE ri.raffle_id = r.id AND i.is_active = 1) AS prize_stock
            FROM events_raffles r
            WHERE r.status IN ('active','closed') AND r.is_active = 1
            ORDER BY r.end_date ASC
            LIMIT 50")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
        $readyCandidates = [];
        $ongoingCandidates = [];
        $now = time();
        foreach ($candidatesRaw as $raffle) {
            $end = strtotime((string)$raffle['end_date']);
            if ($end !== false && $end <= $now) {
                $readyCandidates[] = $raffle;
            } else {
                $ongoingCandidates[] = $raffle;
            }
        }
        $recentDraws = $pdo->query("SELECT d.*, r.name AS raffle_name, r.start_date, r.end_date, u.username AS drawn_by_name,
                (SELECT GROUP_CONCAT(wu.username SEPARATOR ', ') FROM events_raffle_winners w LEFT JOIN users wu ON wu.id = w.user_id WHERE w.draw_id = d.id) AS winner_names
            FROM events_raffle_draws d
            LEFT JOIN events_raffles r ON r.id = d.raffle_id
            LEFT JOIN users u ON u.id = d.drawn_by
            ORDER BY d.id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Draw candidate list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$activeTab = $_GET['tab'] ?? 'ready';
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
    
    <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-wheel-toolbar data-ui-events-admin-component="actionbar">
        <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Çekiliş yönetimi menüsü">
            <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-raffles.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-raffle-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                <i class="bi bi-sliders"></i> Çekiliş Yönetimi
            </a>
            <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-raffles.php?tab=draw') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-raffle-draw" data-ui-events-tab="draw" data-ui-events-wheel-ajax-link><i class="bi bi-shuffle"></i> Çekiliş Çekimi</a>
        </div>
    </div>
</div>
</div>
    
    <div class="ui-events-tabs-shell ui-events-admin-tabs-shell ui-events-tabs-shell-offset ui-section" data-ui-events-tabs-root data-ui-events-admin-component="tabs">
        <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" role="tablist">
            <a href="#ui-events-admin-tab-draw-ready" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'ready' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'ready' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-draw-ready" data-ui-events-tab="ready">
                <i class="bi bi-check2-all"></i> Süresi Dolanlar
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-admin-tab-draw-ongoing" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'ongoing' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'ongoing' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-draw-ongoing" data-ui-events-tab="ongoing">
                <i class="bi bi-hourglass-split"></i> Devam Eden Çekilişler
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-admin-tab-draw-recent" class="ui-events-nav-link ui-events-admin-tab <?= $activeTab === 'recent' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'recent' ? 'true' : 'false' ?>" aria-controls="ui-events-admin-tab-draw-recent" data-ui-events-tab="recent">
                <i class="bi bi-clock-history"></i> Son Çekimler
            </a>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-events-admin-tabs-body ui-panel__body ui-panel">
            
            <!-- SÜRESİ DOLANLAR -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'ready' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-draw-ready" role="tabpanel" data-ui-events-tab-panel="ready" <?= $activeTab === 'ready' ? '' : 'hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <?php eventsAdminPanelHeader('bi-check2-all', 'Süresi Dolanlar (Çekime Hazır)', 'Süresi bitmiş ve çekilmeyi bekleyen çekilişler.'); ?>
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($readyCandidates === []): ?>
                            <?php eventsAdminEmptyState('bi-magic', 'Çekiliş Yok', 'Çekime hazır süresi dolmuş çekiliş yok.'); ?>
                        <?php else: ?>
                            <table class="ui-events-table">
                                <thead><tr><th>Çekiliş</th><th>Katılımcı</th><th>Stok</th><th>Kazanan</th><th>Durum</th><th>Çekim</th></tr></thead>
                                <tbody>
                                <?php foreach ($readyCandidates as $raffle): ?>
                                    <?php $canDraw = (int)$raffle['unique_entry_count'] >= (int)$raffle['winner_count'] && (int)$raffle['prize_stock'] >= (int)$raffle['winner_count']; ?>
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
                                                <input type="hidden" name="notes" value="Admin panel çekimi">
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

            <!-- DEVAM EDENLER -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'ongoing' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-draw-ongoing" role="tabpanel" data-ui-events-tab-panel="ongoing" <?= $activeTab === 'ongoing' ? '' : 'hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <?php eventsAdminPanelHeader('bi-hourglass-split', 'Devam Eden Çekilişler', 'Henüz süresi bitmediği için çekilemeyen çekilişler.'); ?>
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($ongoingCandidates === []): ?>
                            <?php eventsAdminEmptyState('bi-ticket-perforated', 'Aktif Çekiliş Yok', 'Devam eden çekiliş yok.'); ?>
                        <?php else: ?>
                            <table class="ui-events-table">
                                <thead><tr><th>Çekiliş</th><th>Katılımcı</th><th>Stok</th><th>Kazanan</th><th>Bitiş</th><th>Durum</th></tr></thead>
                                <tbody>
                                <?php foreach ($ongoingCandidates as $raffle): ?>
                                    <tr>
                                        <td><strong><?= e((string)$raffle['name']) ?></strong></td>
                                        <td><?= (int)$raffle['unique_entry_count'] ?></td>
                                        <td><?= (int)$raffle['prize_stock'] ?></td>
                                        <td><?= (int)$raffle['winner_count'] ?></td>
                                        <td><span class="ui-events-list-meta"><?= e(eventsFormatDateTime((string)$raffle['end_date'])) ?></span></td>
                                        <td><span class="ui-events-badge ui-events-badge-warning"><i class="bi bi-hourglass ui-events-admin-icon-gap"></i>Sürüyor</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SON ÇEKİMLER -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel <?= $activeTab === 'recent' ? 'is-active' : '' ?> ui-panel" id="ui-events-admin-tab-draw-recent" role="tabpanel" data-ui-events-tab-panel="recent" <?= $activeTab === 'recent' ? '' : 'hidden' ?>>
                <div class="admin-card ui-events-admin-panel ui-panel">
                    <?php eventsAdminPanelHeader('bi-clock-history', 'Son çekimler', 'Tamamlanan çekimleri kazanan kişiler ve tarih bilgisiyle izleyin.'); ?>
                    <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface">
                        <?php if ($recentDraws === []): ?>
                            <?php eventsAdminEmptyState('bi-clock-history', 'Çekim Geçmişi Boş', 'Henüz sonuçlanmış bir çekiliş yok.'); ?>
                        <?php else: ?>
                        <table class="ui-events-table"><thead><tr><th>Çekiliş</th><th>Başlangıç</th><th>Bitiş</th><th>Çekim Tarihi</th><th>Kazanan(lar)</th><th>Çeken</th></tr></thead><tbody>
                        <?php foreach ($recentDraws as $draw): ?><tr>
                            <td><strong><?= e((string)($draw['raffle_name'] ?? ('#' . $draw['raffle_id']))) ?></strong></td>
                            <td><span class="ui-events-list-meta"><?= e(eventsFormatDateTime((string)$draw['start_date'])) ?></span></td>
                            <td><span class="ui-events-list-meta"><?= e(eventsFormatDateTime((string)$draw['end_date'])) ?></span></td>
                            <td><?= e(eventsFormatDateTime((string)$draw['created_at'])) ?></td>
                            <td><span class="ui-events-badge ui-events-badge-success"><?= e((string)($draw['winner_names'] ?? '-')) ?></span></td>
                            <td><?= e((string)($draw['drawn_by_name'] ?? '-')) ?></td>
                        </tr><?php endforeach; ?>
                        </tbody></table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
