<?php
$activeEventsTab = $activeEventsTab ?? 'overview';
$baseEventsUrl = htmlspecialchars(
    function_exists('routePublicStaticUrl')
        ? routePublicStaticUrl('events')
        : rtrim((string) ($baseUri ?? ''), '/') . '/events'
);

$tabbarUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
$tabbarClaimableTasksCount = 0;
$tabbarPendingRewardsCount = 0;

if ($tabbarUserId > 0 && isset($pdo) && $pdo instanceof PDO) {
    if (function_exists('eventsGetUserTasks')) {
        try {
            $taskData = eventsGetUserTasks($pdo, $tabbarUserId);
            foreach (($taskData['groups'] ?? []) as $tasks) {
                foreach ($tasks as $task) {
                    if (!empty($task['is_completed']) && empty($task['is_claimed'])) {
                        $tabbarClaimableTasksCount++;
                    }
                }
            }
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }
    
    if (function_exists('eventsUserOverview')) {
        try {
            $overview = eventsUserOverview($pdo, $tabbarUserId);
            $tabbarPendingRewardsCount = (int)($overview['pending_rewards'] ?? 0);
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
    }
}
?>
<div class="ui-events-nav-container">
    <nav class="ui-events-tabbar ui-events-primary-nav" aria-label="Etkinlik bolumleri">
        <a class="ui-events-tab-button <?= $activeEventsTab === 'overview' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/" <?= $activeEventsTab === 'overview' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-grid-1x2"></i><span>Genel Bakış</span>
        </a>
        <a class="ui-events-tab-button <?= $activeEventsTab === 'wheel' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/wheel" <?= $activeEventsTab === 'wheel' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-arrow-clockwise"></i><span>Şans Çarkı</span>
        </a>
        <a class="ui-events-tab-button <?= $activeEventsTab === 'raffle' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/raffle" <?= $activeEventsTab === 'raffle' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-ticket-perforated"></i><span>Çekiliş</span>
        </a>
        <a class="ui-events-tab-button <?= $activeEventsTab === 'tasks' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/tasks" <?= $activeEventsTab === 'tasks' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-check2-square"></i><span>Görevler</span>
            <?php if ($tabbarClaimableTasksCount > 0): ?>
                <span class="ui-events-badge-dot bg-success" title="<?= $tabbarClaimableTasksCount ?> Görev Ödülü Alınabilir"><?= $tabbarClaimableTasksCount ?></span>
            <?php endif; ?>
        </a>
        <a class="ui-events-tab-button <?= $activeEventsTab === 'points' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/?tab=points" <?= $activeEventsTab === 'points' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-stars"></i><span>Puan</span>
        </a>
        <a class="ui-events-tab-button <?= $activeEventsTab === 'rewards' ? 'is-active' : '' ?>" href="<?= $baseEventsUrl ?>/rewards" <?= $activeEventsTab === 'rewards' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-gift"></i><span>Ödüller</span>
            <?php if ($tabbarPendingRewardsCount > 0): ?>
                <span class="ui-events-badge-dot bg-danger" title="<?= $tabbarPendingRewardsCount ?> Bekleyen Ödül"><?= $tabbarPendingRewardsCount ?></span>
            <?php endif; ?>
        </a>
    </nav>
</div>
