<?php
/**
 * Leaderboard Sidebar Widget
 * Shows top 5 users in selected category/period
 */

if (!isset($pdo)) {
    return;
}

require_once __DIR__ . '/../src/Modules/Leaderboard/Support/helpers.php';
require_once __DIR__ . '/../src/Modules/Leaderboard/Support/cache-manager.php';

$settings = leaderboardGetSettings($pdo);
$leaderboardUrl = routePublicStaticUrl('leaderboard');

// Check if sidebar widget is enabled
if (($settings['leaderboard_enabled'] ?? '1') !== '1' ||
    ($settings['leaderboard_show_sidebar'] ?? '1') !== '1') {
    return;
}

$widgetLimit = (int)($settings['leaderboard_sidebar_limit'] ?? 5);
$defaultCategory = 'daily_login';
$defaultPeriod = 'weekly';

try {
    $leaderboardData = leaderboardGetData($pdo, $defaultCategory, $defaultPeriod, $widgetLimit, 0);
    $topUsers = $leaderboardData['data'] ?? [];
} catch (Throwable $e) {
    appLogException($e, ['source' => 'leaderboard-widget.php']);
    $topUsers = [];
}

if (empty($topUsers)) {
    return;
}

?>

<div class="widget leaderboard-widget" id="leaderboard-widget">
    <div class="widget-header">
        <h3><i class="bi bi-trophy"></i> Lider Tablosu</h3>
    </div>
    <div class="widget-body">
        <div class="leaderboard-controls">
            <select class="leaderboard-period-select" id="leaderboard-period-widget" aria-label="Dönem seçimi">
                <option value="daily">Günlük</option>
                <option value="weekly" selected>Haftalık</option>
                <option value="monthly">Aylık</option>
                <option value="all_time">Tüm Zamanlar</option>
            </select>
        </div>

        <div class="leaderboard-list" id="leaderboard-widget-list">
            <?php foreach ($topUsers as $index => $user): ?>
                <?php
                $user = function_exists('leaderboardDecorateRow')
                    ? leaderboardDecorateRow($user, $baseUri)
                    : $user;
                $rank = $index + 1;
                $username = htmlspecialchars($user['username'] ?? 'Anonim');
                $profileDisplayName = publicProfileDisplayName($user);
                if ($profileDisplayName === '') {
                    $profileDisplayName = 'kullanici';
                }
                $count = number_format((int)($user['count'] ?? $user['score'] ?? 0));
                $userId = (int)($user['user_id'] ?? 0);
                $avatarFallback = function_exists('defaultAvatarUrl')
                    ? defaultAvatarUrl($baseUri)
                    : $baseUri . '/assets/images/noavatar-neon-helmet.svg';
                $profileUrl = htmlspecialchars((string)($user['profile_url'] ?? publicProfileUrl([
                    'id' => $userId,
                    'username' => $profileDisplayName,
                ])), ENT_QUOTES, 'UTF-8');

                $avatarUrl = htmlspecialchars((string)($user['avatar_url'] ?? $avatarFallback), ENT_QUOTES, 'UTF-8');
                $medals = ['🥇', '🥈', '🥉'];
                ?>
                <div class="leaderboard-item">
                    <div class="leaderboard-rank">
                        <?php if ($rank <= 3): ?>
                            <span class="medal"><?= $medals[$rank - 1] ?></span>
                        <?php else: ?>
                            <span class="rank-number"><?= $rank ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" class="leaderboard-avatar">
                        <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" width="48" height="48" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($avatarFallback, ENT_QUOTES, 'UTF-8') ?>">
                    </a>
                    <div class="leaderboard-info">
                        <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" class="leaderboard-username"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></a>
                        <span class="leaderboard-score"><?= $count ?> giriş</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="<?= htmlspecialchars($leaderboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="leaderboard-view-all">
            Tümünü Gör <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>



