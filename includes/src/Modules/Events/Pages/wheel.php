<?php

declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/includes/init.php';
require_once __DIR__ . '/../init.php';

requireAuth();
global $pdo, $baseUri;

$pageTitle = 'Çark Çevir';
$metaDescription = 'Günlük çark hakkını kullan ve etkinlik ödülleri kazan.';
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$config = eventsGetConfig($pdo);
$eventsBaseUrl = eventsPublicUrl();
$wheelFrontendSettings = eventsWheelFrontendSettings($config);
$featureGate = eventsFeatureGate($config, 'wheel');

// Sayfalama
$historyPerPage = 10;
$historyPage = max(1, (int)($_GET['p'] ?? 1));
$historyOffset = ($historyPage - 1) * $historyPerPage;
if (!$featureGate['enabled']) {
    require dirname(__DIR__, 5) . '/includes/public-header.php';
    echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/events.css', 'css') . '">';
    echo renderPublicBreadcrumb([
        ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
        ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
        ['label' => 'Çark Çevir'],
    ], 'ui-events-breadcrumb');
    echo '<div class="public-container public-content ui-events-page ui-section ui-container"><div class="ui-events-empty ui-events-setup ui-empty"><strong>Çark kapalı.</strong> ' . e((string)$featureGate['message']) . '</div></div>';
    require dirname(__DIR__, 5) . '/includes/public-footer.php';
    exit;
}
$overview = eventsUserOverview($pdo, $userId);
$wheelUsage = eventsWheelUsageState($pdo, $userId, $config);
$dailyLimit = (int)$wheelUsage['daily_limit'];
$hourlyLimit = (int)$wheelUsage['hourly_limit'];
$remainingDaily = $wheelUsage['remaining_daily'];
$remainingHourly = $wheelUsage['remaining_hourly'];
$remainingDailyLabel = eventsLimitLabel($remainingDaily);
$remainingHourlyLabel = eventsLimitLabel($remainingHourly);
$cooldownRemaining = (int)$wheelUsage['cooldown_remaining'];
$cooldownLabel = eventsFormatReadableDurationSeconds($cooldownRemaining, 'Hazır');
$rewards = [];

if ($pdo && eventsTablesReady($pdo)) {
    try {
        $stmt = $pdo->query("SELECT name, type, value, probability, remaining_quantity FROM events_wheel_rewards WHERE is_active = 1 ORDER BY display_order ASC, id ASC LIMIT 12");
        $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Wheel rewards could not be listed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$recentWinners = [];
if ($pdo && eventsTablesReady($pdo)) {
    $winnerNameExpr = (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username'))
        ? "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))"
        : "CONCAT('user-', u.id)";
    try {
        $recentWinners = $pdo->query("SELECT ur.reward_name, {$winnerNameExpr} AS username FROM events_user_rewards ur JOIN users u ON u.id = ur.user_id WHERE ur.source_type = 'wheel' ORDER BY ur.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

// Çark Geçmişi verilerini önceden yükle
$wheelHistorySpins = [];
$wheelHistoryTotalSpins = 0;
$wheelHistoryTotalPages = 1;
if ($pdo && eventsTablesReady($pdo)) {
    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM events_wheel_spins WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $wheelHistoryTotalSpins = (int)$countStmt->fetchColumn();

        $historyWindow = eventsResolvePaginationWindow($historyPage, $wheelHistoryTotalSpins, $historyPerPage);
        $historyPage = (int)$historyWindow['page'];
        $historyOffset = (int)$historyWindow['offset'];
        $wheelHistoryTotalPages = (int)$historyWindow['total_pages'];

        $stmt = $pdo->prepare("
            SELECT
                ws.id,
                ws.created_at,
                wr.name AS reward_name,
                wr.type AS reward_type,
                wr.value AS reward_value,
                COALESCE(ur.status, 'pending') AS reward_status
            FROM events_wheel_spins ws
            JOIN events_wheel_rewards wr ON wr.id = ws.reward_id
            LEFT JOIN events_user_rewards ur ON ur.id = ws.user_reward_id
            WHERE ws.user_id = ?
            ORDER BY ws.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $historyPerPage, $historyOffset]);
        $wheelHistorySpins = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Wheel history preload failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

$totalActiveWeight = 0;
foreach ($rewards as $r) {
    $totalActiveWeight += (float)$r['probability'];
}

$wheelColors = ['#0f766e', '#2563eb', '#dc2626', '#ca8a04', '#7c3aed', '#0891b2', '#be123c', '#16a34a', '#ea580c', '#4f46e5', '#0d9488', '#c026d3'];
$wheelSliceCount = count($rewards);
$wheelDegPerSlice = $wheelSliceCount > 0 ? 360 / $wheelSliceCount : 0.0;
$wheelOffsetDeg = $wheelSliceCount > 0 ? -($wheelDegPerSlice / 2) : 0.0;
$wheelGradientStyle = '';
if ($wheelSliceCount > 0) {
    $gradientParts = [];
    for ($index = 0; $index < $wheelSliceCount; $index++) {
        $start = number_format($index * $wheelDegPerSlice, 4, '.', '');
        $end = number_format(($index + 1) * $wheelDegPerSlice, 4, '.', '');
        $gradientParts[] = $wheelColors[$index % count($wheelColors)] . ' ' . $start . 'deg ' . $end . 'deg';
    }

    $wheelGradientStyle = 'background: conic-gradient(from ' . number_format($wheelOffsetDeg, 4, '.', '') . 'deg, ' . implode(', ', $gradientParts) . ');';
}

require dirname(__DIR__, 5) . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?= eventsGetAssetUrl('/events/assets/css/events.css', 'css') ?>">
<?php if ($wheelSliceCount > 0 && $wheelGradientStyle !== ''): ?>
<style<?= function_exists('appCspNonceAttr') ? appCspNonceAttr() : '' ?>>
    [data-ui-events-wheel-instance="public-wheel"] {
        <?= $wheelGradientStyle ?>
    }
    <?php foreach ($rewards as $index => $reward): ?>
        <?php
        $separatorRotation = (($index * $wheelDegPerSlice) + $wheelOffsetDeg);
        $textRotation = (($index * $wheelDegPerSlice) - 90);
        ?>
        [data-ui-events-wheel-instance="public-wheel"] [data-ui-events-wheel-separator][data-ui-events-wheel-index="<?= $index ?>"] {
            transform: rotate(<?= e(number_format($separatorRotation, 4, '.', '')) ?>deg);
        }
        [data-ui-events-wheel-instance="public-wheel"] [data-ui-events-wheel-text][data-ui-events-wheel-index="<?= $index ?>"] {
            transform: rotate(<?= e(number_format($textRotation, 4, '.', '')) ?>deg);
        }
    <?php endforeach; ?>
</style>
<?php endif; ?>

<?= renderPublicBreadcrumb([
    ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
    ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
    ['label' => 'Çark Çevir'],
], 'ui-events-breadcrumb') ?>

<div class="public-container public-content ui-events-page ui-section ui-container">
    <?php
    $activeEventsTab = 'wheel';
    require __DIR__ . '/_tabbar.php';
    ?>
    <?= eventsRenderBanner($config) ?>
    <section class="ui-events-hero" aria-labelledby="wheel-title">
        <div class="ui-events-hero-main">
            <h1 class="ui-events-title" id="wheel-title">Çarkı çevir, sonucu net gör.</h1>
            <p class="ui-events-subtitle">Günlük limitin <?= $dailyLimit ?>, saatlik limitin <?= $hourlyLimit ?>. Kazandığın ödüller otomatik olarak ödül kasana işlenir.</p>
        </div>
        <div class="ui-events-hero-side">
            <div class="ui-events-stat-card ui-events-stat-info ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-calendar2-check"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= e($remainingDailyLabel) ?></div>
                    <div class="ui-events-stat-label">Bugünkü kalan hak</div>
                </div>
            </div>
            <div class="ui-events-stat-card ui-events-stat-success ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= e($remainingHourlyLabel) ?></div>
                    <div class="ui-events-stat-label">Saatlik kalan hak</div>
                </div>
            </div>
            <div class="ui-events-stat-card <?= $cooldownRemaining > 0 ? 'ui-events-stat-danger' : 'ui-events-stat-success' ?> ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-stopwatch"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value" data-ui-events-wheel-usage-cooldown-hero><?= e($cooldownLabel) ?></div>
                    <div class="ui-events-stat-label">Bekleme durumu</div>
                </div>
            </div>
            <div class="ui-events-stat-card ui-events-stat-warning ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-box-seam"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?= count($rewards) ?></div>
                    <div class="ui-events-stat-label">Aktif ödül dilimi</div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$overview['ready']): ?>
        <div class="ui-events-empty ui-events-setup ui-empty">Cark ekrani hazir; veritabani semasi tamamlandiktan sonra odul havuzu kullanilabilir.</div>
    <?php endif; ?>

    <div class="ui-events-tabs-shell ui-events-tabs-shell-tight ui-section" data-ui-events-tabs-root>
        <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-app-nav-flush" role="tablist">
            <a href="#ui-events-tab-wheel-spin" class="ui-events-nav-link is-active" role="tab" aria-selected="true" aria-controls="ui-events-tab-wheel-spin" data-ui-events-tab="spin">
                <i class="bi bi-disc"></i> Çark Çevir
            </a>
            <span class="ui-events-nav-separator" aria-hidden="true"></span>
            <a href="#ui-events-tab-wheel-history" class="ui-events-nav-link" role="tab" aria-selected="false" aria-controls="ui-events-tab-wheel-history" data-ui-events-tab="history">
                <i class="bi bi-clock-history"></i> Çark Geçmişi
            </a>
        </div>

        <div class="ui-events-panel-body ui-events-tabs-body ui-events-tabs-body-flush ui-panel__body ui-panel">

            <!-- SPIN TAB -->
            <div class="ui-events-tab-panel is-active ui-panel" id="ui-events-tab-wheel-spin" role="tabpanel" data-ui-events-tab-panel="spin">
                <section class="ui-events-grid ui-grid">
                    <div class="ui-events-panel ui-events-col-7 ui-panel">
                        <div class="ui-events-panel-head ui-panel ui-panel__head">
                            <h2><i class="bi bi-disc"></i> Çark</h2>
                            <?php
                            $wheelReadyBadgeClass = !empty($wheelUsage['can_spin_now']) ? 'ui-events-badge-available ui-events-badge-success' : 'ui-events-badge-warning';
                            $wheelReadyBadgeText = $cooldownRemaining > 0 ? 'Bekleme' : (!empty($wheelUsage['can_spin_now']) ? 'Alınabilir' : 'Limit doldu');
                            ?>
                            <span class="ui-events-badge <?= $wheelReadyBadgeClass ?>" data-ui-events-wheel-ready-badge><?= e($wheelReadyBadgeText) ?></span>
                        </div>
                        <div class="ui-events-panel-body ui-panel__body ui-panel">
                            <div class="ui-events-wheel-stage">
                                <div class="ui-events-wheel-wrap">
                                    <div class="ui-events-wheel-border" aria-hidden="true"></div>
                                    <div class="ui-events-wheel-pointer" aria-hidden="true"></div>
                                    <?php if (count($rewards) > 0): ?>
                                        <div class="ui-events-wheel" data-ui-events-wheel aria-hidden="true">
                                            <div class="ui-events-wheel-inner" data-ui-events-wheel-inner data-ui-events-css-rendered="1" data-ui-events-wheel-instance="public-wheel" data-ui-events-slice-count="<?= $wheelSliceCount ?>" data-ui-events-deg-per-slice="<?= e((string)$wheelDegPerSlice) ?>" data-ui-events-offset-deg="<?= e((string)$wheelOffsetDeg) ?>">
                                                <div class="ui-events-wheel-overlay" aria-hidden="true"></div>

                                                <!-- Slices and Separators -->
                                                <?php foreach ($rewards as $index => $reward): ?>
                                                    <?php
                                                    $separatorRotation = (($index * $wheelDegPerSlice) + $wheelOffsetDeg);
                                                    $textRotation = (($index * $wheelDegPerSlice) - 90);
                                                    $rewardName = (string)$reward['name'];
                                                    $rewardNameNeedsTooltip = mb_strlen($rewardName, 'UTF-8') > 14;
                                                    ?>
                                                    <div class="ui-events-wheel-separator" data-ui-events-wheel-separator data-ui-events-wheel-index="<?= $index ?>"></div>

                                                    <div class="ui-events-wheel-text" data-ui-events-wheel-text data-ui-events-wheel-index="<?= $index ?>" data-ui-events-wheel-tooltip="<?= e($rewardName) ?>" aria-label="<?= e($rewardName) ?>" title="<?= e($rewardName) ?>"<?= $rewardNameNeedsTooltip ? ' tabindex="0"' : '' ?>>
                                                        <span><?= e($rewardName) ?></span>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="ui-events-wheel-center" aria-hidden="true"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ui-events-wheel" data-ui-events-wheel aria-hidden="true"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ui-events-result" data-ui-events-result data-ui-events-result-card><?= $rewards === [] ? 'Admin panelinden aktif ödül eklenince çark açılır.' : 'Hazırsan çevirebilirsin.' ?></div>
                                <div
                                    class="ui-events-wheel-usage"
                                    data-ui-events-wheel-usage
                                    data-ui-events-limit-blocked="<?= !empty($wheelUsage['limit_blocked']) ? '1' : '0' ?>"
                                    data-ui-events-cooldown-until="<?= !empty($wheelUsage['next_spin_at_epoch']) ? ((int)$wheelUsage['next_spin_at_epoch'] * 1000) : '' ?>"
                                    data-ui-events-can-spin-now="<?= !empty($wheelUsage['can_spin_now']) ? '1' : '0' ?>"
                                >
                                    <span><i class="bi bi-calendar2-check"></i> Günlük <strong data-ui-events-wheel-usage-daily><?= e($remainingDailyLabel) ?></strong></span>
                                    <span><i class="bi bi-hourglass-split"></i> Saatlik <strong data-ui-events-wheel-usage-hourly><?= e($remainingHourlyLabel) ?></strong></span>
                                    <span><i class="bi bi-stopwatch"></i> Bekleme <strong data-ui-events-wheel-usage-cooldown><?= e($cooldownLabel) ?></strong></span>
                                </div>
                                <div class="ui-events-wheel-action">
                                    <?php
                                    $extraSpinCost = (int)($config['wheel_extra_spin_cost'] ?? 0);
                                    $canSpinForFree = !empty($wheelUsage['can_spin_free']);
                                    $canSpinForPoints = !empty($wheelUsage['can_spin_with_extra']);
                                    $disableSpin = (!$overview['ready'] || $rewards === [] || empty($wheelUsage['can_spin_now']));
                                    $buttonLabel = $canSpinForPoints ? "{$extraSpinCost} Puanla Çevir" : "Çevir";
                                    $buttonLabel = $cooldownRemaining > 0 ? 'Bekle' : ($canSpinForPoints ? "{$extraSpinCost} Puanla Çevir" : "Çevir");
                                    $buttonLabel = $cooldownRemaining > 0 ? 'Bekle' : (!empty($wheelUsage['can_spin_with_bonus']) ? 'Bonus Hakla Çevir' : ($canSpinForPoints ? "{$extraSpinCost} Puanla Çevir" : "Çevir"));
                                    ?>
                                    <button
                                        class="ui-events-wheel-spin-btn"
                                        type="button"
                                        data-ui-events-spin
                                        <?= $disableSpin ? 'disabled' : '' ?>
                                        aria-label="<?= e($buttonLabel) ?>"
                                    >
                                        <span class="ui-events-wheel-spin-btn-icon">
                                            <i class="bi bi-play-fill"></i>
                                        </span>
                                        <span class="ui-events-wheel-spin-btn-text"><?= e($buttonLabel) ?></span>
                                        <span class="ui-events-wheel-spin-btn-glow" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ui-events-panel ui-events-col-5 ui-panel">
                        <div class="ui-events-panel-head ui-panel ui-panel__head">
                            <h2><i class="bi bi-list-stars"></i> Ödül dilimleri</h2>
                        </div>
                        <div class="ui-events-panel-body ui-panel__body ui-panel">
                            <?php if ($rewards === []): ?>
                                <?= eventsRenderPublicEmptyState('bi-gift', 'Aktif çark ödülü yok', 'Admin panelinden çark ödülü eklendiğinde dilimler burada görünecek.') ?>
                            <?php else: ?>
                                <div class="ui-events-list">
                                    <?php foreach ($rewards as $reward): ?>
                                        <div class="ui-events-list-item ui-card">
                                            <div class="ui-events-list-main">
                                                <span class="ui-events-list-title"><?= e((string)$reward['name']) ?></span>
                                                <span class="ui-events-list-meta"><?= e((string)$reward['type']) ?> · Değer: <?= e((string)$reward['value']) ?></span>
                                            </div>
                                            <?php
                                            $weight = (float)$reward['probability'];
                                            $percentage = $totalActiveWeight > 0 ? round(($weight / $totalActiveWeight) * 100, 2) : 0;
                                            ?>
                                            <span class="ui-events-badge" title="Çıkma İhtimali">%<?= $percentage ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($recentWinners)): ?>
                        <div class="ui-events-panel-head ui-events-panel-head-separated ui-panel ui-panel__head">
                            <h2><i class="bi bi-clock-history"></i> Son Kazananlar</h2>
                        </div>
                        <div class="ui-events-panel-body ui-panel__body ui-panel">
                            <div class="ui-events-ticker-container ui-events-wheel-winners" data-ui-events-wheel-winners>
                                <div class="ui-events-ticker-list" role="list">
                                    <?php foreach (array_slice($recentWinners, 0, 5) as $winner): ?>
                                        <div class="ui-events-list-item ui-events-winner-list-item ui-card" role="listitem">
                                            <div class="ui-events-list-main">
                                                <span class="ui-events-list-title ui-events-winner-title"><?= e((string)$winner['username']) ?></span>
                                                <span class="ui-events-list-meta"><?= e((string)$winner['reward_name']) ?> kazand&#305;</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- HISTORY TAB -->
            <div class="ui-events-tab-panel ui-panel" id="ui-events-tab-wheel-history" role="tabpanel" data-ui-events-tab-panel="history" hidden>
                <div class="ui-events-panel ui-panel">
                    <div class="ui-events-panel-head ui-panel ui-panel__head">
                        <h2><i class="bi bi-clock-history"></i> Çark Geçmişi</h2>
                    </div>
                    <div class="ui-events-panel-body ui-panel__body ui-panel">
                        <div id="ui-events-wheel-history-container">
                            <?php
                            if (empty($wheelHistorySpins)):
                            ?>
                                <?= eventsRenderPublicEmptyState('bi-inbox', 'Çark geçmişi boş', 'Henüz çark çevirmemişsiniz.') ?>
                            <?php else: ?>
                                <div class="ui-events-list">
                                    <?php foreach ($wheelHistorySpins as $spin): ?>
                                        <div class="ui-events-list-item ui-card">
                                            <div class="ui-events-list-main">
                                                <span class="ui-events-list-title"><?= e((string)$spin['reward_name']) ?></span>
                                                <span class="ui-events-list-meta">
                                                    <?= e((string)$spin['reward_type']) ?> · Değer: <?= e((string)$spin['reward_value']) ?> ·
                                                    <?= e(eventsFormatDateTime((string)$spin['created_at'])) ?>
                                                </span>
                                            </div>
                                            <?php
                                            if ($spin['reward_status'] === 'claimed') {
                                                $statusBadge = '<span class="ui-events-badge ui-events-badge-success">Teslim Edildi</span>';
                                            } elseif ($spin['reward_status'] === 'cancelled') {
                                                $statusBadge = '<span class="ui-events-badge ui-events-badge-danger">İptal Edildi</span>';
                                            } else {
                                                $statusBadge = '<span class="ui-events-badge ui-events-badge-warning">Beklemede</span>';
                                            }
                                            echo $statusBadge;
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($wheelHistoryTotalPages > 1): ?>
                                    <?= eventsRenderPagination($historyPage, $wheelHistoryTotalPages, '', ['tab' => 'history'], 'p', 'Cark gecmisi sayfalama') ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$eventsRuntimeSettings = array_merge([
    'pollingInterval' => max(5, (int)($config['frontend_toast_polling_interval'] ?? 15)) * 1000,
    'animationsEnabled' => !isset($config['frontend_animations_enabled']) || $config['frontend_animations_enabled'] === 'true',
    'soundsEnabled' => !isset($config['frontend_sounds_enabled']) || $config['frontend_sounds_enabled'] === 'true',
], $wheelFrontendSettings);
?>
<script type="application/json" id="eventsRuntimeSettings"><?= json_encode($eventsRuntimeSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= eventsGetAssetUrl('/events/assets/js/events.js', 'js') ?>" defer></script>
<?php require dirname(__DIR__, 5) . '/includes/public-footer.php'; ?>
