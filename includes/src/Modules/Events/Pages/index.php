<?php

declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/includes/init.php';
require_once __DIR__ . '/../init.php';

requireAuth();
global $pdo, $baseUri;

$pageTitle = 'Etkinlikler';
$metaDescription = 'Çark çevir, çekilişlere katıl, görevlerini tamamla ve kazandığın puanları tek ekrandan takip et.';
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$config = eventsGetConfig($pdo);

$activeEventsTab = $_GET['tab'] ?? 'overview';
if (!in_array($activeEventsTab, ['overview', 'points'])) {
    $activeEventsTab = 'overview';
}

if ($userId > 0 && $pdo instanceof PDO) {
    eventsProcessLoginStreak($pdo, $userId, $config);
}

$userProfile = [];
if ($userId > 0 && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userProfile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}
$eventsAvatarFallback = function_exists('defaultAvatarUrl') ? defaultAvatarUrl($baseUri ?? '') : rtrim((string) ($baseUri ?? ''), '/') . '/assets/images/noavatar-neon-helmet.svg';
$eventsAvatarUrl = function_exists('resolveAvatarUrl')
    ? resolveAvatarUrl((string) ($userProfile['avatar'] ?? ''), $baseUri ?? '', true)
    : (!empty($userProfile['avatar']) ? rtrim((string) ($baseUri ?? ''), '/') . '/' . ltrim((string) $userProfile['avatar'], '/') : $eventsAvatarFallback);

$featureGate = eventsFeatureGate($config, 'events');
if (!$featureGate['enabled']) {
    require dirname(__DIR__, 5) . '/includes/public-header.php';
    echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/events.css', 'css') . '">';
    echo renderPublicBreadcrumb([
        ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
        ['label' => 'Etkinlikler'],
    ], 'ui-events-breadcrumb');
    echo '<div class="public-container public-content ui-events-page ui-section ui-container"><div class="ui-events-empty ui-events-setup ui-empty"><strong>Etkinlikler kapalı.</strong> ' . e((string)$featureGate['message']) . '</div></div>';
    require dirname(__DIR__, 5) . '/includes/public-footer.php';
    exit;
}
$overview = eventsUserOverview($pdo, $userId);
$dailyLimit = (int)($config['wheel_daily_limit'] ?? 3);
$remainingDaily = max(0, $dailyLimit - (int)$overview['today_spins']);

$emptyTaskGroups = ['daily' => [], 'weekly' => [], 'monthly' => [], 'achievement' => []];
$tasksReady = $pdo instanceof PDO && eventsTasksTablesReady($pdo);
$taskData = $tasksReady ? eventsGetUserTasks($pdo, $userId) : ['balance' => 0, 'groups' => $emptyTaskGroups, 'history' => []];
$taskGroups = array_replace($emptyTaskGroups, is_array($taskData['groups'] ?? null) ? $taskData['groups'] : []);
$pointHistory = is_array($taskData['history'] ?? null) ? $taskData['history'] : [];
$taskTypeLabels = eventsAllowedTaskTypes();
$activityLabels = eventsAllowedActivityTypes();
$activityLabel = static function (string $activityType) use ($activityLabels): string {
    if ($activityType === 'task_reward') {
        return 'Görev ödülü';
    }

    return $activityLabels[$activityType] ?? ($activityType !== '' ? $activityType : 'Puan hareketi');
};

$allTasks = [];
$claimableTasks = [];
$activeTaskCount = 0;
$completedTaskCount = 0;
foreach ($taskGroups as $groupKey => $tasks) {
    foreach ($tasks as $task) {
        $task['group_key'] = (string)$groupKey;
        $task['group_label'] = $taskTypeLabels[(string)$groupKey] ?? ucfirst((string)$groupKey);
        $allTasks[] = $task;
        $activeTaskCount++;

        if (!empty($task['is_completed'])) {
            $completedTaskCount++;
        }
        if (!empty($task['is_completed']) && empty($task['is_claimed'])) {
            $claimableTasks[] = $task;
        }
    }
}

$highlightTasks = $allTasks;
usort($highlightTasks, static function (array $left, array $right): int {
    $leftClaimable = !empty($left['is_completed']) && empty($left['is_claimed']) ? 1 : 0;
    $rightClaimable = !empty($right['is_completed']) && empty($right['is_claimed']) ? 1 : 0;
    if ($leftClaimable !== $rightClaimable) {
        return $rightClaimable <=> $leftClaimable;
    }

    return (int)($right['progress_percent'] ?? 0) <=> (int)($left['progress_percent'] ?? 0);
});
$highlightTasks = array_slice($highlightTasks, 0, 4);

$activityRules = [];
if ($tasksReady) {
    try {
        $activityRules = $pdo->query("SELECT * FROM events_activity_rules
            WHERE is_active = 1
            ORDER BY FIELD(activity_type, 'comment_created', 'topic_created', 'comment_reaction_added', 'topic_favorite_added', 'wheel_spin', 'daily_login'), activity_type ASC")
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $activityRules = [];
    }
}

require dirname(__DIR__, 5) . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?= eventsGetAssetUrl('/events/assets/css/events.css', 'css') ?>">

<?= renderPublicBreadcrumb([
    ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
    ['label' => 'Etkinlikler'],
], 'ui-events-breadcrumb') ?>

<div class="public-container public-content ui-events-page ui-section ui-container">
    <?php require __DIR__ . '/_tabbar.php'; ?>

    <?php if ($activeEventsTab === 'overview'): ?>
    <div class="ui-events-summary-cards" aria-label="Hızlı etkinlik durumu">
        <div class="ui-events-stat-card ui-events-stat-info ui-card">
            <div class="ui-events-stat-icon"><i class="bi bi-stars"></i></div>
            <div class="ui-events-stat-content">
                <div class="ui-events-stat-value" data-ui-events-countup><?= (int)$taskData['balance'] ?></div>
                <div class="ui-events-stat-label">Toplam Puan</div>
            </div>
        </div>
        <div class="ui-events-stat-card ui-events-stat-success ui-card">
            <div class="ui-events-stat-icon"><i class="bi bi-check2-circle"></i></div>
            <div class="ui-events-stat-content">
                <div class="ui-events-stat-value"><?= $completedTaskCount ?> / <?= $activeTaskCount ?></div>
                <div class="ui-events-stat-label">Tamamlanan Görev</div>
            </div>
        </div>
        <div class="ui-events-stat-card ui-events-stat-warning ui-card">
            <div class="ui-events-stat-icon"><i class="bi bi-ticket-perforated"></i></div>
            <div class="ui-events-stat-content">
                <div class="ui-events-stat-value"><?= count($overview['active_raffles']) ?></div>
                <div class="ui-events-stat-label">Aktif Çekiliş</div>
            </div>
        </div>
        <div class="ui-events-stat-card ui-events-stat-danger ui-card">
            <div class="ui-events-stat-icon"><i class="bi bi-gift"></i></div>
            <div class="ui-events-stat-content">
                <div class="ui-events-stat-value"><?= (int)$overview['pending_rewards'] ?></div>
                <div class="ui-events-stat-label">Bekleyen Ödül</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?= eventsRenderBanner($config) ?>
    <section class="ui-events-panel ui-events-tabs-shell ui-section ui-panel">
        <div class="ui-events-panel-body ui-events-tabs-body ui-panel__body ui-panel">
            <?php if ($activeEventsTab === 'overview'): ?>
                <div class="ui-events-tab-panel is-active ui-panel">
                <section class="ui-events-hero ui-events-section-spaced ui-section" aria-labelledby="ui-events-title">
                    <div class="ui-events-hero-main">
                        <h1 class="ui-events-title" id="ui-events-title">Ödüller, çekilişler, görevler ve puanların tek yerde.</h1>
                        <p class="ui-events-subtitle">Etkinlik merkezi hesabına bağlı çalışır. Çark haklarını, aktif çekilişleri, görev ilerlemeni ve kazandığın puanları sade bir akışta takip edebilirsin.</p>
                        <div class="ui-events-hero-actions">
                        </div>
                    </div>
                    <div class="ui-events-hero-side ui-events-hero-side--overview" aria-label="Etkinlik özeti">
                        <article class="ui-events-profile-card ui-events-profile-card--minimal" aria-label="Hesap özeti">
                            <header class="ui-events-profile-minimal-head">
                                <div class="ui-events-profile-minimal-avatar">
                                    <img src="<?= htmlspecialchars($eventsAvatarUrl) ?>" alt="Avatar" class="ui-events-profile-avatar" width="52" height="52" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($eventsAvatarFallback) ?>">
                                </div>
                                <div class="ui-events-profile-minimal-meta">
                                    <h3 class="ui-events-profile-name"><?= e((string)($userProfile['username'] ?? 'Kullanıcı')) ?></h3>
                                    <p class="ui-events-profile-minimal-subtitle">Etkinlik hesabın aktif. Güncel durumun aşağıda.</p>
                                </div>
                            </header>

                            <dl class="ui-events-profile-minimal-stats" aria-label="Etkinlik kısa durum">
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Toplam Puan</dt>
                                    <dd data-ui-events-countup><?= (int)$taskData['balance'] ?></dd>
                                </div>
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Çark Hakkı</dt>
                                    <dd><?= $remainingDaily ?></dd>
                                </div>
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Hazır Görev</dt>
                                    <dd><?= count($claimableTasks) ?></dd>
                                </div>
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Aktif Çekiliş</dt>
                                    <dd><?= count($overview['active_raffles']) ?></dd>
                                </div>
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Bekleyen Ödül</dt>
                                    <dd><?= (int)$overview['pending_rewards'] ?></dd>
                                </div>
                                <div class="ui-events-profile-minimal-stat">
                                    <dt>Tamamlanan Görev</dt>
                                    <dd><?= $completedTaskCount ?> / <?= $activeTaskCount ?></dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </section>

                <?php if (!$overview['ready']): ?>
                    <div class="ui-events-empty ui-events-setup ui-events-section-spaced ui-empty ui-section">
                        <strong>Etkinlik modulu kod tarafinda hazir.</strong> Veritabani semasi icin <code>database/schema.sql</code> kurulumunu tamamlamaniz gerekiyor.
                    </div>
                <?php endif; ?>
                <?php if ($overview['ready'] && !$tasksReady): ?>
                    <div class="ui-events-empty ui-events-setup ui-events-section-spaced ui-empty ui-section">
                        <strong>Gorev sistemi kod tarafinda hazir.</strong> Puan ve gorev tablolari icin <code>database/schema.sql</code> kurulumunu tamamlamaniz gerekiyor.
                    </div>
                <?php endif; ?>

                <div class="ui-events-info-cards ui-card">
                    <div class="ui-events-info-card ui-card">
                        <div class="ui-events-info-card-icon ui-card"><i class="bi bi-question-circle" aria-hidden="true"></i></div>
                        <div class="ui-events-info-card-content ui-section ui-card">
                            <h4>Nasıl puan kazanırım?</h4>
                            <p>Yorum yap, konu oluştur, emoji ekle, favori işaretle ve günlük giriş yap. Her aktivite sana puan kazandırır.</p>
                        </div>
                    </div>
                    <div class="ui-events-info-card ui-card">
                        <div class="ui-events-info-card-icon ui-card"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></div>
                        <div class="ui-events-info-card-content ui-section ui-card">
                            <h4>Çark hakkı nedir?</h4>
                            <p>Her gün belirli sayıda çarkı çevirme hakkın var. Çarkı çevirerek ödüller kazanabilirsin.</p>
                        </div>
                    </div>
                    <div class="ui-events-info-card ui-card">
                        <div class="ui-events-info-card-icon ui-card"><i class="bi bi-gift" aria-hidden="true"></i></div>
                        <div class="ui-events-info-card-content ui-section ui-card">
                            <h4>Ödüller nasıl çalışır?</h4>
                            <p>Görevleri tamamla, çekilişleri kazanarak ödüller topla. Ödüllerini talep etmek için "Al" butonuna tıkla.</p>
                        </div>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <?php if ($activeEventsTab === 'points'): ?>
            <div class="ui-events-tab-panel is-active ui-panel">
                <section class="ui-events-hero ui-events-section-spaced ui-section" aria-labelledby="ui-events-points-title">
                    <div class="ui-events-hero-main">
                        <h1 class="ui-events-title" id="ui-events-points-title">Toplam Puanınız: <?= (int)$taskData['balance'] ?></h1>
                        <p class="ui-events-subtitle">Yorum, konu, emoji, favori, günlük giriş ve çark hareketlerinden kazandığın puanlar burada birikir. Günlük limitler dolduğunda aktivite sayılır ama puan vermeyebilir.</p>
                    </div>
                    <div class="ui-events-hero-side" aria-label="Puan özeti">
                        <div class="ui-events-stat-card ui-events-stat-warning ui-card">
                            <div class="ui-events-stat-icon"><i class="bi bi-stars"></i></div>
                            <div class="ui-events-stat-content ui-section">
                                <div class="ui-events-stat-value" data-ui-events-countup><?= (int)$taskData['balance'] ?></div>
                                <div class="ui-events-stat-label">Mevcut Puan</div>
                            </div>
                        </div>
                        <div class="ui-events-stat-card ui-events-stat-info ui-card">
                            <div class="ui-events-stat-icon"><i class="bi bi-list-check"></i></div>
                            <div class="ui-events-stat-content ui-section">
                                <div class="ui-events-stat-value"><?= count($activityRules) ?></div>
                                <div class="ui-events-stat-label">Puan Yöntemi</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ui-events-panel ui-events-tabs-shell ui-events-tabs-surface-flat ui-section ui-panel" data-ui-events-tabs-root>
                    <div class="ui-events-tabbar ui-events-tabbar-spaced" role="tablist" aria-label="Puan bölümleri">
                        <button class="ui-events-tab-button is-active" id="ui-events-tab-ways-button" type="button" role="tab" aria-selected="true" aria-controls="ui-events-tab-ways" data-ui-events-tab="ways">
                            <i class="bi bi-list-check"></i><span>Puan Kazanma Yolları</span>
                        </button>
                        <button class="ui-events-tab-button" id="ui-events-tab-points-history-button" type="button" role="tab" aria-selected="false" aria-controls="ui-events-tab-points-history" data-ui-events-tab="points-history">
                            <i class="bi bi-clock-history"></i><span>Puan Hareketleri</span>
                        </button>
                    </div>

                    <div class="ui-events-panel-body ui-events-tabs-body ui-events-tabs-body-flush ui-panel__body ui-panel">
                        <!-- Puan Kazanma Yolları -->
                        <div class="ui-events-tab-panel is-active ui-panel" id="ui-events-tab-ways" role="tabpanel" aria-labelledby="ui-events-tab-ways-button" data-ui-events-tab-panel="ways">
                            <div class="ui-events-grid ui-events-grid-compact ui-grid">
                                <div class="ui-events-tab-section ui-events-col-12 ui-section">
                                    <div class="ui-events-section-head ui-panel__head">
                                        <h2><i class="bi bi-list-check"></i> Puan kazanma yolları</h2>
                                    </div>
                                    <?php if ($activityRules === []): ?>
                                        <?= eventsRenderPublicEmptyState('bi-info-circle', 'Puan kuralı bulunamadı', 'Henüz aktif puan kuralı tanımlanmamış. Admin panelinden kurallar eklendiğinde burada görünür.') ?>
                                    <?php else: ?>
                                        <div class="ui-events-list">
                                            <?php foreach ($activityRules as $rule): ?>
                                                <div class="ui-events-list-item ui-card">
                                                    <div class="ui-events-list-main">
                                                        <span class="ui-events-list-title"><?= e((string)$rule['label']) ?></span>
                                                        <span class="ui-events-list-meta">Günlük limit: <?= (int)$rule['daily_limit'] > 0 ? (int)$rule['daily_limit'] : 'Limitsiz' ?></span>
                                                    </div>
                                                    <span class="ui-events-badge ui-events-badge-success">+<?= (int)$rule['points'] ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Puan Hareketleri -->
                        <div class="ui-events-tab-panel ui-panel" id="ui-events-tab-points-history" role="tabpanel" aria-labelledby="ui-events-tab-points-history-button" data-ui-events-tab-panel="points-history" hidden>
                            <div class="ui-events-grid ui-events-grid-compact ui-grid">
                                <div class="ui-events-tab-section ui-events-col-12 ui-section">
                                    <div class="ui-events-section-head ui-panel__head">
                                        <h2><i class="bi bi-clock-history" aria-label="Puan geçmişi"></i> Puan geçmişi</h2>
                                        <div class="ui-events-history-filters" role="group" aria-label="Puan geçmişi filtreleri">
                                            <button class="ui-events-filter-btn is-active" data-filter="all" aria-label="Tüm puan hareketlerini göster">Tümü</button>
                                            <button class="ui-events-filter-btn" data-filter="today" aria-label="Bugünün puan hareketlerini göster">Bugün</button>
                                            <button class="ui-events-filter-btn" data-filter="week" aria-label="Bu haftanın puan hareketlerini göster">Bu Hafta</button>
                                            <button class="ui-events-filter-btn" data-filter="month" aria-label="Bu ayın puan hareketlerini göster">Bu Ay</button>
                                        </div>
                                    </div>
                                    <?php if ($pointHistory === []): ?>
                                        <?= eventsRenderPublicEmptyState('bi-clock-history', 'Henüz puan hareketi yok', 'Etkinliklere katıldığında ve görevleri tamamladığında puan hareketleri burada görünecek.') ?>
                                    <?php else: ?>
                                        <?php
                                        $historyPage = max(1, (int)($_GET['p'] ?? 1));
                                        $historyPerPage = 10;
                                        $totalHistory = count($pointHistory);
                                        $totalHistoryPages = max(1, (int)ceil($totalHistory / $historyPerPage));
                                        if ($historyPage > $totalHistoryPages && $totalHistoryPages > 0) { $historyPage = $totalHistoryPages; }
                                        $paginatedHistory = array_slice($pointHistory, ($historyPage - 1) * $historyPerPage, $historyPerPage);
                                        ?>
                                        <div class="ui-events-list" id="ui-events-history-list">
                                            <?php foreach ($paginatedHistory as $row): ?>
                                                <?php
                                                $delta = (int)($row['points_delta'] ?? 0);
                                                // Fallback: if points_delta is 0 or null, try reward_value
                                                if ($delta === 0 && isset($row['reward_value'])) {
                                                    $delta = (int)$row['reward_value'];
                                                }
                                                ?>
                                                <div class="ui-events-list-item ui-card" data-history-date="<?= e((string)$row['created_at']) ?>">
                                                    <div class="ui-events-list-main">
                                                        <span class="ui-events-list-title"><?= e($activityLabel((string)($row['activity_type'] ?? ''))) ?></span>
                                                        <span class="ui-events-list-meta"><?= e(eventsFormatDateTime((string)$row['created_at'])) ?></span>
                                                    </div>
                                                    <span class="ui-events-badge <?= $delta >= 0 ? 'ui-events-badge-success' : 'ui-events-badge-warning' ?>" aria-label="<?= $delta > 0 ? 'Kazanılan' : 'Kaybedilen' ?> <?= abs($delta) ?> puan"><?= $delta > 0 ? '+' : '' ?><?= $delta ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if ($totalHistoryPages > 1): ?>
                                            <?= eventsRenderPagination($historyPage, $totalHistoryPages, '', ['tab' => 'points'], 'p', 'Puan gecmisi sayfalama') ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <?php endif; ?>

        </div>
    </section>
</div>

<script type="application/json" id="eventsRuntimeSettings"><?= json_encode([
    'pollingInterval' => max(5, (int)($config['frontend_toast_polling_interval'] ?? 15)) * 1000,
    'animationsEnabled' => !isset($config['frontend_animations_enabled']) || $config['frontend_animations_enabled'] === 'true',
    'soundsEnabled' => !isset($config['frontend_sounds_enabled']) || $config['frontend_sounds_enabled'] === 'true',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= eventsGetAssetUrl('/events/assets/js/events.js', 'js') ?>" defer></script>
<?php require dirname(__DIR__, 5) . '/includes/public-footer.php'; ?>
