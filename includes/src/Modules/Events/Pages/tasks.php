<?php

declare(strict_types=1);

require_once dirname(__DIR__, 5) . '/includes/init.php';
require_once __DIR__ . '/../init.php';

requireAuth();
global $pdo, $baseUri;

$pageTitle = 'Görevler';
$metaDescription = 'Etkinlik görevlerini tamamla, puan kazan ve ödüllerini al.';
$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$config = eventsGetConfig($pdo);
$eventsBaseUrl = eventsPublicUrl();
$featureGate = eventsFeatureGate($config, 'tasks');
if (!$featureGate['enabled']) {
    require dirname(__DIR__, 5) . '/includes/public-header.php';
    echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/events.css', 'css') . '">';
    echo renderPublicBreadcrumb([
        ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
        ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
        ['label' => 'Görevler'],
    ], 'ui-events-breadcrumb');
    echo '<div class="public-container public-content ui-events-page ui-section ui-container"><div class="ui-events-empty ui-events-setup ui-empty"><strong>Görevler kapalı.</strong> ' . e((string)$featureGate['message']) . '</div></div>';
    require dirname(__DIR__, 5) . '/includes/public-footer.php';
    exit;
}
$tasksReady = $pdo instanceof PDO && eventsTasksTablesReady($pdo);
$taskData = $tasksReady ? eventsGetUserTasks($pdo, $userId) : ['balance' => 0, 'groups' => ['daily' => [], 'weekly' => [], 'monthly' => [], 'achievement' => []], 'history' => []];
$groups = $taskData['groups'];
$history = $taskData['history'];
$groupLabels = [
    'daily' => ['Bugünkü görevler', 'bi-calendar2-check'],
    'weekly' => ['Haftalık görevler', 'bi-calendar-week'],
    'monthly' => ['Aylık görevler', 'bi-calendar3'],
    'achievement' => ['Başarı görevleri', 'bi-trophy'],
];
$allTasks = [];
foreach ($groups as $groupKey => $groupTasks) {
    foreach ($groupTasks as $task) {
        $task['group_label'] = $groupLabels[$groupKey][0] ?? 'Görev';
        $allTasks[] = $task;
    }
}

require dirname(__DIR__, 5) . '/includes/public-header.php';
?>
<link rel="stylesheet" href="<?= eventsGetAssetUrl('/events/assets/css/events.css', 'css') ?>">

<?= renderPublicBreadcrumb([
    ['label' => 'Anasayfa', 'url' => rtrim($baseUri, '/') . '/index.php'],
    ['label' => 'Etkinlikler', 'url' => $eventsBaseUrl],
    ['label' => 'Görevler'],
], 'ui-events-breadcrumb') ?>

<div class="public-container public-content ui-events-page ui-section ui-container">
    <?php
    $activeEventsTab = 'tasks';
    require __DIR__ . '/_tabbar.php';
    ?>
    <?= eventsRenderBanner($config) ?>
    <section class="ui-events-hero" aria-labelledby="ui-events-tasks-title">
        <div class="ui-events-hero-main">
            <h1 class="ui-events-title" id="ui-events-tasks-title">Aktif ol, ilerlemeni gör, ödülünü al.</h1>
            <p class="ui-events-subtitle">Yorum, konu, emoji, favori ve çark hareketlerin görev ilerlemesine dönüşür. Aktivite puanları otomatik gelir; görev ödüllerini tamamlandığında buradan alırsın.</p>

        </div>
        <div class="ui-events-hero-side">
            <div class="ui-events-stat-card ui-events-stat-warning ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-stars"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value" data-ui-events-countup><?= (int)$taskData['balance'] ?></div>
                    <div class="ui-events-stat-label">Etkinlik puanı</div>
                </div>
            </div>
            <div class="ui-events-stat-card ui-events-stat-success ui-card">
                <div class="ui-events-stat-icon"><i class="bi bi-check-circle"></i></div>
                <div class="ui-events-stat-content ui-section">
                    <div class="ui-events-stat-value"><?php
                        $claimable = 0;
                        foreach ($groups as $items) {
                            foreach ($items as $task) {
                                if (!empty($task['is_completed']) && empty($task['is_claimed'])) {
                                    $claimable++;
                                }
                            }
                        }
                        echo $claimable;
                    ?></div>
                    <div class="ui-events-stat-label">Alınabilir ödül</div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!$tasksReady): ?>
        <div class="ui-events-empty ui-events-setup ui-empty">Gorev sistemi hazir; veritabani semasi tamamlandiktan sonra gorevler kullanilabilir.</div>
    <?php endif; ?>

    <div class="ui-events-task-filters" role="tablist">
        <button class="ui-events-task-filter-btn is-active" type="button" data-ui-events-task-filter="all" role="tab" aria-selected="true">
            <i class="bi bi-grid"></i><span>Hepsi</span>
        </button>
        <button class="ui-events-task-filter-btn" type="button" data-ui-events-task-filter="daily" role="tab" aria-selected="false">
            <i class="bi bi-calendar2-check"></i><span>Günlük</span>
        </button>
        <button class="ui-events-task-filter-btn" type="button" data-ui-events-task-filter="weekly" role="tab" aria-selected="false">
            <i class="bi bi-calendar-week"></i><span>Haftalık</span>
        </button>
        <button class="ui-events-task-filter-btn" type="button" data-ui-events-task-filter="monthly" role="tab" aria-selected="false">
            <i class="bi bi-calendar3"></i><span>Aylık</span>
        </button>
        <button class="ui-events-task-filter-btn" type="button" data-ui-events-task-filter="achievement" role="tab" aria-selected="false">
            <i class="bi bi-trophy"></i><span>Başarılar</span>
        </button>
    </div>

    <section class="ui-events-grid ui-grid" data-ui-events-task-grid>
        <?php foreach ($groupLabels as $groupKey => [$label, $icon]): ?>
            <div class="ui-events-panel ui-events-col-6 ui-panel" data-ui-events-task-group="<?= e($groupKey) ?>">
                <div class="ui-events-panel-head ui-panel ui-panel__head">
                    <h2><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></h2>
                </div>
                <div class="ui-events-panel-body ui-panel__body ui-panel">
                    <?php if (($groups[$groupKey] ?? []) === []): ?>
                        <?= eventsRenderPublicEmptyState('bi-check2-square', 'Bu bölümde görev yok', 'Yeni görevler aktif edildiğinde burada görünür; diğer sekmelerde tamamlanabilir görevleri kontrol edebilirsin.') ?>
                    <?php else: ?>
                        <div class="ui-events-task-list">
                            <?php foreach ($groups[$groupKey] as $task): ?>
                                <?php
                                $completed = !empty($task['is_completed']);
                                $claimed = !empty($task['is_claimed']);
                                $badgeClass = $claimed ? 'ui-events-badge-muted' : ($completed ? 'ui-events-badge-available ui-events-badge-success' : 'ui-events-badge-warning');
                                $badgeText = $claimed ? 'Teslim edildi' : ($completed ? 'Alınabilir' : 'Bekliyor');
                                ?>
                                <article class="ui-events-task-card ui-card" data-ui-events-task-accordion>
                                    <div class="ui-events-task-top" data-ui-events-task-accordion-trigger>
                                        <div class="ui-events-task-header-main">
                                            <h3><?= e((string)$task['title']) ?></h3>
                                            <div class="ui-events-task-collapse">
                                                <p><?= e((string)$task['description']) ?></p>
                                            </div>
                                        </div>
                                        <div class="ui-events-task-header-right">
                                            <span class="ui-events-badge <?= $badgeClass ?>"><?= e($badgeText) ?></span>
                                            <button class="ui-events-task-toggle-btn" type="button" aria-expanded="false" aria-label="Detayları göster/gizle">
                                                <i class="bi bi-chevron-down"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="ui-events-task-details-body">
                                        <div class="ui-events-task-progress" aria-label="Görev ilerlemesi">
                                            <span class="ui-events-progress-fill" data-ui-events-progress="<?= (int)$task['progress_percent'] ?>"></span>
                                        </div>
                                        <div class="ui-events-task-meta">
                                            <span><?= (int)$task['progress_count'] ?> / <?= (int)$task['target_count'] ?> · <?= e((string)$task['activity_label']) ?></span>
                                            <strong><?= e((string)$task['reward_label']) ?></strong>
                                        </div>
                                        <?php if ($completed && !$claimed): ?>
                                            <button class="ui-events-btn ui-events-btn-primary ui-events-task-claim" type="button" data-ui-events-task-claim="<?= (int)$task['id'] ?>" data-ui-events-period="<?= e((string)$task['period_key']) ?>">
                                                <i class="bi bi-gift"></i> Ödülü al
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<script type="application/json" id="eventsRuntimeSettings"><?= json_encode([
    'pollingInterval' => max(5, (int)($config['frontend_toast_polling_interval'] ?? 15)) * 1000,
    'animationsEnabled' => !isset($config['frontend_animations_enabled']) || $config['frontend_animations_enabled'] === 'true',
    'soundsEnabled' => !isset($config['frontend_sounds_enabled']) || $config['frontend_sounds_enabled'] === 'true',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= eventsGetAssetUrl('/events/assets/js/events.js', 'js') ?>" defer></script>
<?php require dirname(__DIR__, 5) . '/includes/public-footer.php'; ?>
