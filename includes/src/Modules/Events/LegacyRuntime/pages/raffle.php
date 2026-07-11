<?php

declare(strict_types=1);

require_once dirname(__DIR__, 6) . '/includes/init.php';
require_once __DIR__ . '/../init.php';

requireAuth();
global $pdo, $baseUri;

$pageTitle = 'Çekilişler';
$metaDescription = 'Aktif etkinlik çekilişlerine katıl ve sonuçları takip et.';
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$config = eventsGetConfig($pdo);
$eventsBaseUrl = eventsPublicUrl();
$featureGate = eventsFeatureGate($config, 'raffle');
if (!$featureGate['enabled']) {
    require dirname(__DIR__, 6) . '/includes/public-header.php';
    echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/events.css', 'css') . '">';
    echo renderPublicBreadcrumb([
        ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
        ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
        ['label' => 'Çekilişler'],
    ], 'ui-events-breadcrumb');
    echo '<div class="public-container public-content ui-events-page ui-section ui-container"><div class="ui-events-empty ui-events-setup ui-empty"><strong>Çekilişler kapalı.</strong> ' . e((string)$featureGate['message']) . '</div></div>';
    require dirname(__DIR__, 6) . '/includes/public-footer.php';
    exit;
}
$ready = eventsTablesReady($pdo);
$raffles = array_filter(eventsActiveRaffles($pdo, $userId, 30), fn($r) => (string)$r['status'] !== 'drawn');
$drawnRaffles = eventsDrawnRafflesWithWinners($pdo, 30);

require dirname(__DIR__, 6) . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?= eventsGetAssetUrl('/events/assets/css/events.css', 'css') ?>">

<?= renderPublicBreadcrumb([
    ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
    ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
    ['label' => 'Çekilişler'],
], 'ui-events-breadcrumb') ?>

<div class="public-container public-content ui-events-page ui-section ui-container">
    <?php
    $activeEventsTab = 'raffle';
    require __DIR__ . '/_tabbar.php';
    ?>
    <?= eventsRenderBanner($config) ?>
    <section class="ui-events-hero" aria-labelledby="raffle-title">
        <div class="ui-events-hero-main">
            <h1 class="ui-events-title" id="raffle-title">Aktif çekilişleri takip et, uygun olanlara katıl.</h1>
            <p class="ui-events-subtitle">Her çekiliş kendi katılım limitiyle çalışır. Katıldığın çekilişler işaretlenir, sonuçlar ödül kasana düşer.</p>
        </div>
        <div class="ui-events-hero-side">
            <div class="ui-events-stat-card ui-events-stat-info ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-ticket-detailed"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= count($raffles) ?></div>
                    <div class="ui-events-stat-label">Listelenen çekiliş</div>
                </div>
            </div>
            <div class="ui-events-stat-card ui-events-stat-success ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-shield-check"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= $ready ? 'Aktif' : 'Kurulum' ?></div>
                    <div class="ui-events-stat-label">Modül durumu</div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$ready): ?>
        <div class="ui-events-empty ui-events-setup ui-empty">Cekilis ekrani hazir; cekilis kayitlari icin veritabani semasi tamamlanmali.</div>
    <?php endif; ?>

    <section class="ui-events-panel ui-events-tabs-shell ui-section ui-panel" data-ui-events-tabs-root>
        <div class="ui-events-tabbar ui-events-raffle-tabbar" role="tablist" aria-label="Çekiliş bölümleri">
            <button class="ui-events-tab-button is-active" id="ui-events-tab-list-button" type="button" role="tab" aria-selected="true" aria-controls="ui-events-tab-list" data-ui-events-tab="list">
                <i class="bi bi-ticket"></i><span>Mevcut Çekilişler</span>
            </button>
            <button class="ui-events-tab-button" id="ui-events-tab-results-button" type="button" role="tab" aria-selected="false" aria-controls="ui-events-tab-results" data-ui-events-tab="results">
                <i class="bi bi-trophy"></i><span>Çekiliş Sonuçları</span>
            </button>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-panel__body ui-panel">
            <!-- AKTİF ÇEKİLİŞLER SEKME İÇERİĞİ -->
            <div class="ui-events-tab-panel is-active ui-panel" id="ui-events-tab-list" role="tabpanel" aria-labelledby="ui-events-tab-list-button" data-ui-events-tab-panel="list">
                <?php if ($raffles === []): ?>
                    <?= eventsRenderPublicEmptyState('bi-ticket-detailed', 'Şu an için aktif bir çekiliş bulunmuyor', 'Yeni ödüller ve etkinlik çekilişleri burada listelenecek. Daha sonra tekrar kontrol edebilirsiniz.') ?>
                <?php else: ?>
                    <div class="ui-events-list">
                        <?php foreach ($raffles as $raffle): ?>
                            <?php
                            $joined = (int)($raffle['user_entries'] ?? 0) > 0;
                            $isDrawn = (string)$raffle['status'] === 'drawn';
                            $isExpired = strtotime((string)$raffle['end_date']) <= time();
                            $canJoin = eventsRaffleIsOpen($raffle) && !$joined && (int)$raffle['user_entries'] < (int)$raffle['max_entries_per_user'];
                            $endTs = strtotime((string)$raffle['end_date']);
                            $hoursLeft = max(0, ($endTs - time()) / 3600);
                            $participationText = $joined ? 'Katıldın' : ($canJoin ? 'Alınabilir' : eventsRaffleAvailabilityLabel($raffle));
                            ?>
                            <div class="ui-events-raffle-card ui-card" data-raffle-id="<?= (int)$raffle['id'] ?>">
                                <div class="ui-events-raffle-card-icon ui-card">
                                    <i class="bi bi-ticket-perforated"></i>
                                </div>
                                <div class="ui-events-raffle-card-body ui-card ui-panel__body">
                                    <h3 class="ui-events-raffle-card-title ui-card"><?= e((string)$raffle['name']) ?></h3>
                                    <div class="ui-events-raffle-card-meta ui-card">
                                        <span><?= e(eventsStatusLabel((string)$raffle['status'])) ?></span>
                                        <span class="ui-events-raffle-card-meta-sep ui-card"></span>
                                        <span>Bitiş: <?= e(eventsFormatDateTime((string)$raffle['end_date'])) ?></span>
                                        <span class="ui-events-raffle-card-meta-sep ui-card"></span>
                                        <span>Katılım: <?= (int)$raffle['entry_count'] ?></span>
                                    </div>
                                    <div class="ui-events-raffle-card-stats ui-card" aria-label="Çekiliş detayları">
                                        <span><i class="bi bi-people"></i><strong data-ui-events-raffle-entry-count><?= (int)$raffle['entry_count'] ?></strong> katılım</span>
                                        <span><i class="bi bi-person-check"></i><strong class="ui-events-raffle-participation-state" data-ui-events-inline-status><?= e($participationText) ?></strong></span>
                                        <span><i class="bi bi-calendar-event"></i><strong><?= e(eventsFormatDateTime((string)$raffle['end_date'])) ?></strong></span>
                                        <?php if ((int)$raffle['max_entries_per_user'] > 1): ?>
                                            <span><i class="bi bi-ticket"></i><strong data-ui-events-raffle-limit>Kalan hakkın: <?= max(0, (int)$raffle['max_entries_per_user'] - (int)$raffle['user_entries']) ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$isDrawn && !$isExpired && $endTs > time()): ?>
                                        <span class="ui-events-raffle-countdown<?= $hoursLeft < 6 ? ' is-urgent' : '' ?>" data-ui-events-countdown="<?= htmlspecialchars((string)$raffle['end_date']) ?>">
                                            <i class="bi bi-clock"></i>
                                            <span class="ui-events-countdown-text">Yükleniyor...</span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ui-events-raffle-card-action ui-card">
                                    <?php if ($isDrawn): ?>
                                        <span class="ui-events-raffle-status ui-events-raffle-status-drawn"><i class="bi bi-check-circle"></i> Sonuçlandı</span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="ui-events-raffle-status ui-events-raffle-status-expired"><i class="bi bi-clock-history"></i> Süresi doldu</span>
                                    <?php elseif ($joined): ?>
                                        <span class="ui-events-raffle-status ui-events-raffle-status-joined"><i class="bi bi-check2-circle"></i> Katıldın</span>
                                    <?php elseif ($canJoin): ?>
                                        <button class="ui-events-btn ui-events-btn-primary" type="button" data-ui-events-raffle-join="<?= (int)$raffle['id'] ?>"><i class="bi bi-plus-circle"></i> Katıl</button>
                                    <?php else: ?>
                                        <span class="ui-events-raffle-status ui-events-raffle-status-closed"><?= e(eventsRaffleAvailabilityLabel($raffle)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- ÇEKİLİŞ SONUÇLARI SEKME İÇERİĞİ -->
            <div class="ui-events-tab-panel ui-panel" id="ui-events-tab-results" role="tabpanel" aria-labelledby="ui-events-tab-results-button" data-ui-events-tab-panel="results" hidden>
                <?php if ($drawnRaffles === []): ?>
                    <?= eventsRenderPublicEmptyState('bi-trophy', 'Henüz sonuçlanmış çekiliş bulunmuyor', 'Çekilişler tamamlandığında kazananlar bu bölümde görüntülenecek.') ?>
                <?php else: ?>
                    <?php
                    $resultsPage = max(1, (int)($_GET['p'] ?? 1));
                    $resultsPerPage = 10;
                    $totalResults = count($drawnRaffles);
                    $totalResultsPages = max(1, (int)ceil($totalResults / $resultsPerPage));
                    if ($resultsPage > $totalResultsPages && $totalResultsPages > 0) { $resultsPage = $totalResultsPages; }
                    $paginatedResults = array_slice($drawnRaffles, ($resultsPage - 1) * $resultsPerPage, $resultsPerPage);
                    ?>
                    <div class="ui-events-list">
                        <?php foreach ($paginatedResults as $raffle): ?>
                            <div class="ui-events-raffle-result-card ui-card">
                                <div class="ui-events-raffle-result-body ui-panel__body">
                                    <h3 class="ui-events-raffle-result-title"><?= e((string)$raffle['name']) ?></h3>
                                    <div class="ui-events-raffle-result-meta">
                                        <span>Çekim: <?= e(eventsFormatDateTime((string)$raffle['draw_date_actual'])) ?></span>
                                        <span class="ui-events-raffle-card-meta-sep ui-card"></span>
                                        <span>Katılım: <?= (int)$raffle['entry_count'] ?></span>
                                    </div>
                                </div>
                                <div class="ui-events-raffle-winner-group">
                                    <?php if (!empty($raffle['winner_names'])): ?>
                                        <div class="ui-events-raffle-winner-info">
                                            <span class="ui-events-raffle-winner-label">Kazanan(lar)</span>
                                            <span class="ui-events-raffle-winner-name"><?= e((string)$raffle['winner_names']) ?></span>
                                        </div>
                                        <div class="ui-events-raffle-winner-trophy">
                                            <i class="bi bi-trophy-fill"></i>
                                        </div>
                                    <?php else: ?>
                                        <span class="ui-events-raffle-no-winner"><i class="bi bi-dash-circle"></i> Kazanan Yok</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalResultsPages > 1): ?>
                        <?= eventsRenderPagination($resultsPage, $totalResultsPages, '', ['tab' => 'results'], 'p', 'Cekilis sonuclari sayfalama') ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="eventsRuntimeSettings"><?= json_encode([
    'pollingInterval' => max(5, (int)($config['frontend_toast_polling_interval'] ?? 15)) * 1000,
    'animationsEnabled' => !isset($config['frontend_animations_enabled']) || $config['frontend_animations_enabled'] === 'true',
    'soundsEnabled' => !isset($config['frontend_sounds_enabled']) || $config['frontend_sounds_enabled'] === 'true',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= eventsGetAssetUrl('/events/assets/js/events.js', 'js') ?>" defer></script>
<?php require dirname(__DIR__, 6) . '/includes/public-footer.php'; ?>
