<?php

declare(strict_types=1);

require_once dirname(__DIR__, 6) . '/includes/init.php';
require_once __DIR__ . '/../init.php';

requireAuth();
global $pdo, $baseUri;

$pageTitle = 'Ödüllerim';
$metaDescription = 'Etkinliklerden kazandığın ödülleri ve teslim durumlarını görüntüle.';
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$config = eventsGetConfig($pdo);
$eventsBaseUrl = function_exists('eventsPublicUrl')
    ? eventsPublicUrl()
    : (function_exists('routePublicStaticUrl')
        ? routePublicStaticUrl('events')
        : rtrim((string) ($baseUri ?? ''), '/') . '/events');
$featureGate = eventsFeatureGate($config, 'rewards');
if (!$featureGate['enabled']) {
    require dirname(__DIR__, 6) . '/includes/public-header.php';
    echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/events.css', 'css') . '">';
    echo renderPublicBreadcrumb([
        ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
        ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
        ['label' => 'Ödüllerim'],
    ], 'ui-events-breadcrumb');
    echo '<div class="public-container public-content ui-events-page ui-section ui-container"><div class="ui-events-empty ui-events-setup ui-empty"><strong>Ödüller kapalı.</strong> ' . e((string)$featureGate['message']) . '</div></div>';
    require dirname(__DIR__, 6) . '/includes/public-footer.php';
    exit;
}
$ready = eventsTablesReady($pdo);
$rewards = eventsUserRewards($pdo, $userId, 500);

require dirname(__DIR__, 6) . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?= eventsGetAssetUrl('/events/assets/css/events.css', 'css') ?>">

<?= renderPublicBreadcrumb([
    ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
    ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
    ['label' => 'Ödüllerim'],
], 'ui-events-breadcrumb') ?>

<div class="public-container public-content ui-events-page ui-section ui-container">
    <?php
    $activeEventsTab = 'rewards';
    require __DIR__ . '/_tabbar.php';
    ?>
    <?= eventsRenderBanner($config) ?>
    <section class="ui-events-hero" aria-labelledby="rewards-title">
        <div class="ui-events-hero-main">
            <h1 class="ui-events-title" id="rewards-title">Kazandığın ödüller burada düzenli durur.</h1>
            <p class="ui-events-subtitle">Puan, kupon, indirim ve özel ödüllerin durumunu buradan takip edebilirsin. Teslim ve iptal işlemleri kayıt altında tutulur.</p>
        </div>
        <div class="ui-events-hero-side">
            <div class="ui-events-stat-card ui-events-stat-success ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-box2-heart"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= count($rewards) ?></div>
                    <div class="ui-events-stat-label">Toplam son kayıt</div>
                </div>
            </div>
            <div class="ui-events-stat-card ui-events-stat-danger ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= count(array_filter($rewards, static fn(array $reward): bool => (string)$reward['status'] === 'pending')) ?></div>
                    <div class="ui-events-stat-label">Bekleyen ödül</div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$ready): ?>
        <div class="ui-events-empty ui-events-setup ui-empty">Odul ekrani hazir; kayitlar icin veritabani semasi tamamlanmali.</div>
    <?php endif; ?>

    <section class="ui-events-panel ui-events-tabs-shell ui-events-tabs-surface-flat ui-section ui-panel" data-ui-events-tabs-root>
        <div class="ui-events-tabbar ui-events-tabbar-spaced" role="tablist" aria-label="Ödül bölümleri">
            <button class="ui-events-tab-button is-active" id="ui-events-tab-reward-types-button" type="button" role="tab" aria-selected="true" aria-controls="ui-events-tab-reward-types" data-ui-events-tab="reward-types">
                <i class="bi bi-info-circle"></i><span>Sistemdeki Ödül Türleri</span>
            </button>
            <button class="ui-events-tab-button" id="ui-events-tab-reward-history-button" type="button" role="tab" aria-selected="false" aria-controls="ui-events-tab-reward-history" data-ui-events-tab="reward-history">
                <i class="bi bi-clock-history"></i><span>Ödül Hareketleri</span>
            </button>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-events-tabs-body-flush ui-panel__body ui-panel">
            <!-- Sistemdeki Ödül Türleri -->
            <div class="ui-events-tab-panel is-active ui-panel" id="ui-events-tab-reward-types" role="tabpanel" aria-labelledby="ui-events-tab-reward-types-button" data-ui-events-tab-panel="reward-types">
                <div class="ui-events-grid ui-events-grid-compact ui-grid">
                    <div class="ui-events-panel ui-events-col-12 ui-panel">
                        <div class="ui-events-panel-head ui-panel ui-panel__head">
                            <h2><i class="bi bi-info-circle"></i> Sistemdeki Ödül Türleri</h2>
                        </div>
                        <div class="ui-events-panel-body ui-panel__body ui-panel">
                            <div class="ui-events-list">
                                <div class="ui-events-list-item ui-card">
                                    <div class="ui-events-list-main">
                                        <span class="ui-events-list-title"><i class="bi bi-stars"></i> Etkinlik Puanları</span>
                                        <span class="ui-events-list-meta">Çark çevirerek veya görev yaparak kazandığınız puanlar anında hesabınıza yansır.</span>
                                    </div>
                                </div>
                                <div class="ui-events-list-item ui-card">
                                    <div class="ui-events-list-main">
                                        <span class="ui-events-list-title"><i class="bi bi-ticket-perforated"></i> Kupon Kodları</span>
                                        <span class="ui-events-list-meta">Alışverişlerde kullanabileceğiniz indirim kodlarıdır. Süresi dolmadan kullanmanız gerekir.</span>
                                    </div>
                                </div>
                                <div class="ui-events-list-item ui-card">
                                    <div class="ui-events-list-main">
                                        <span class="ui-events-list-title"><i class="bi bi-box-seam"></i> Fiziksel Ödüller</span>
                                        <span class="ui-events-list-meta">Çekiliş veya özel etkinliklerden kazanılan eşya ödülleridir. Yöneticiler tarafından kargolanır.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ödül Hareketleri -->
            <div class="ui-events-tab-panel ui-panel" id="ui-events-tab-reward-history" role="tabpanel" aria-labelledby="ui-events-tab-reward-history-button" data-ui-events-tab-panel="reward-history" hidden>
                <div class="ui-events-grid ui-events-grid-compact ui-grid">
                    <div class="ui-events-panel ui-events-col-12 ui-panel">
                        <div class="ui-events-panel-head ui-panel ui-panel__head">
                            <h2><i class="bi bi-gift"></i> Ödül geçmişi</h2>
                        </div>
                        <div class="ui-events-panel-body ui-panel__body ui-panel">
                            <?php if ($rewards === []): ?>
                                <?= eventsRenderPublicEmptyState('bi-gift', 'Henüz ödül kaydın yok', 'Çark, görev veya çekilişlerden ödül kazandığında teslim durumu burada görünür.') ?>
                            <?php else: ?>
                                <?php
                                $rewardPage = max(1, (int)($_GET['p'] ?? 1));
                                $rewardPerPage = 10;
                                $totalRewards = count($rewards);
                                $totalRewardPages = max(1, (int)ceil($totalRewards / $rewardPerPage));
                                if ($rewardPage > $totalRewardPages && $totalRewardPages > 0) { $rewardPage = $totalRewardPages; }
                                $paginatedRewards = array_slice($rewards, ($rewardPage - 1) * $rewardPerPage, $rewardPerPage);
                                ?>
                                <div class="ui-events-list">
                                    <?php foreach ($paginatedRewards as $reward): ?>
                                        <div class="ui-events-list-item ui-card ui-events-reward-item <?= (string)$reward['status'] === 'pending' ? 'is-pending-gift' : '' ?>">
                                            <div class="ui-events-list-main">
                                                <span class="ui-events-list-title">
                                                    <?php if ((string)$reward['status'] === 'pending' && (string)$reward['reward_type'] !== 'points'): ?>
                                                        <i class="bi bi-gift ui-events-gift-icon-animated text-danger"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-box-seam text-muted"></i>
                                                    <?php endif; ?>
                                                    <?= e((string)$reward['reward_name']) ?>
                                                </span>
                                                <span class="ui-events-list-meta">
                                                    <?= e((string)$reward['reward_type']) ?> ·
                                                    Değer: <?= e((string)$reward['reward_value']) ?> ·
                                                    Kazanım: <?= e(eventsFormatDateTime((string)$reward['created_at'])) ?>
                                                    <?php if (!empty($reward['expires_at'])): ?>
                                                        · Son gün: <?= e(eventsFormatDateTime((string)$reward['expires_at'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php if ((string)$reward['status'] === 'pending' && (string)$reward['reward_type'] !== 'points'): ?>
                                                <div class="ui-events-action-stack">
                                                    <button class="ui-events-btn ui-events-btn-primary" type="button" data-ui-events-claim-reward="<?= (int)$reward['id'] ?>" data-ui-events-confirm="Bu ödülü teslim alacaksın. Devam edilsin mi?" data-ui-events-confirm-title="Ödül teslim alınsın mı?" data-ui-events-confirm-ok="Teslim Al" data-ui-events-confirm-tone="success"><i class="bi bi-check2-circle"></i> Teslim Al</button>
                                                    <span class="ui-events-inline-status ui-events-inline-status-info" data-ui-events-inline-status>Alınabilir</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="ui-events-badge <?= (string)$reward['status'] === 'claimed' ? 'ui-events-badge-success' : ((string)$reward['status'] === 'pending' ? 'ui-events-badge-warning' : 'ui-events-badge-muted') ?>"><?= e(eventsStatusLabel((string)$reward['status'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($totalRewardPages > 1): ?>
                                    <?= eventsRenderPagination($rewardPage, $totalRewardPages, '', ['tab' => 'reward-history'], 'p', 'Odul gecmisi sayfalama') ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
