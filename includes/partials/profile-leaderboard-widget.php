<?php
/**
 * Profile Leaderboard Widget
 * Shows user's ranks in all categories
 */

if (!isset($pdo) || !isset($userId)) {
    return;
}

require_once __DIR__ . '/../src/Modules/Leaderboard/Legacy/helpers.php';
require_once __DIR__ . '/../src/Modules/Leaderboard/Legacy/calculator.php';

$settings = leaderboardGetSettings($pdo);
$leaderboardUrl = routePublicStaticUrl('leaderboard');

if (($settings['leaderboard_enabled'] ?? '1') !== '1') {
    return;
}

$categories = leaderboardGetCategories();
$userRanks = [];
$period = 'weekly';
$profileLimit = max(1, (int) ($settings['leaderboard_profile_limit'] ?? 10));

try {
    foreach ($categories as $categoryKey => $categoryInfo) {
        $rankBundle = leaderboardGetUserRank($pdo, (int) $userId, $categoryKey, $period);
        $rankData = $rankBundle['ranks'][$categoryKey][$period] ?? null;

        if (!is_array($rankData)) {
            continue;
        }

        $userRanks[$categoryKey] = [
            'category' => $categoryInfo,
            'rank' => (int) ($rankData['rank'] ?? 0),
            'score' => (int) ($rankData['count'] ?? 0),
            'total' => (int) ($rankData['total_users'] ?? 0),
            'percentile' => round((float) ($rankData['percentile'] ?? 0), 1),
        ];
    }

    uasort($userRanks, static function (array $left, array $right): int {
        $rankCompare = $left['rank'] <=> $right['rank'];
        if ($rankCompare !== 0) {
            return $rankCompare;
        }

        return $right['score'] <=> $left['score'];
    });

    if (count($userRanks) > $profileLimit) {
        $userRanks = array_slice($userRanks, 0, $profileLimit, true);
    }
} catch (Throwable $e) {
    appLogException($e, ['source' => 'profile-leaderboard-widget.php']);
}

if (empty($userRanks)) {
    return;
}

$bestRank = min(array_column($userRanks, 'rank'));
$rankCount = count($userRanks);
?>

<div class="widget profile-leaderboard-widget ui-card">
    <div class="profile-leaderboard-head ui-panel__head">
        <span class="profile-leaderboard-mark"><i class="bi bi-trophy-fill"></i></span>
        <div>
            <span class="profile-leaderboard-kicker">Haftalık performans</span>
            <h3>Lider Tablosu Sıralamalarım</h3>
        </div>
        <div class="profile-leaderboard-best">
            <strong>#<?= number_format($bestRank) ?></strong>
            <span>en iyi sıra</span>
        </div>
    </div>

    <div class="profile-leaderboard-body ui-panel__body">
        <div class="profile-leaderboard-summary">
            <span><?= number_format($rankCount) ?> kategoride görünüyorsun</span>
            <span>Bu hafta</span>
        </div>

        <div class="profile-ranks-list">
            <?php foreach ($categories as $categoryKey => $categoryInfo): ?>
                <?php if (isset($userRanks[$categoryKey])): ?>
                    <?php
                    $rankData = $userRanks[$categoryKey];
                    $rank = (int)$rankData['rank'];
                    $score = number_format((int)$rankData['score']);
                    $percentile = round((float)$rankData['percentile'], 1);
                    $total = (int)$rankData['total'];
                    $rankClass = $rank <= 3 ? ' is-podium' : ($percentile >= 75 ? ' is-strong' : '');
                    ?>
                    <div class="profile-rank-item<?= $rankClass ?>">
                        <div class="profile-rank-header ui-panel__head">
                            <span class="profile-rank-icon">
                                <i class="bi <?= htmlspecialchars((string)$categoryInfo['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            </span>
                            <div class="profile-rank-info">
                                <strong><?= htmlspecialchars((string)$categoryInfo['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="profile-rank-position"><?= number_format($total) ?> kullanıcı içinde</span>
                            </div>
                            <div class="profile-rank-metric">
                                <span class="profile-rank-number">#<?= number_format($rank) ?></span>
                                <span class="profile-rank-score"><?= $score ?> sayı</span>
                            </div>
                        </div>
                        <div class="profile-rank-progress">
                            <div class="progress-bar">
                                <meter class="progress-meter" min="0" max="100" value="<?= htmlspecialchars((string)$percentile, ENT_QUOTES, 'UTF-8') ?>"></meter>
                            </div>
                            <span class="progress-label">Top <?= $percentile ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <a href="<?= htmlspecialchars($leaderboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="profile-leaderboard-link">
            <span><i class="bi bi-bar-chart-line"></i> Detaylı istatistikler</span>
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>


