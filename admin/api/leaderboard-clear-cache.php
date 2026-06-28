<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Engine/Auth/Legacy/helpers.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Legacy/cache-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendMethodNotAllowed(['POST']);
}

$currentUserId = (int)($_SESSION['_auth_user_id'] ?? 0);
$hasAdminAccess = $currentUserId > 0
    && function_exists('userHasPermission')
    && userHasPermission($pdo, $currentUserId, 'leaderboard.manage');
if (!$hasAdminAccess) {
    sendForbidden('Liderlik tablosunu yönetme yetkiniz (leaderboard.manage) yok.');
}

if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
    sendCsrfError();
}

try {
    if (isset($_POST['clear_all']) && $_POST['clear_all'] === 'true') {
        leaderboardClearCache($pdo);

        logActivity($pdo, 'leaderboard_cache_cleared', 'leaderboard', null, [
            'scope' => 'all',
            'cleared_by' => $currentUserId
        ]);

        sendSuccess('All leaderboard cache cleared successfully');
    }

    $category = $_POST['category'] ?? null;
    $period = $_POST['period'] ?? null;

    if (!$category || !$period) {
        sendValidationError('Both category and period parameters are required');
    }

    $validCategories = ['downloads', 'active', 'helpful', 'rising_star', 'quality'];
    if (!in_array($category, $validCategories, true)) {
        sendError('invalid_category', 'Category must be one of: ' . implode(', ', $validCategories), 422);
    }

    $validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
    if (!in_array($period, $validPeriods, true)) {
        sendError('invalid_period', 'Period must be one of: ' . implode(', ', $validPeriods), 422);
    }

    leaderboardClearCache($pdo, $category, $period);

    logActivity($pdo, 'leaderboard_cache_cleared', 'leaderboard', null, [
        'category' => $category,
        'period' => $period,
        'cleared_by' => $currentUserId
    ]);

    sendSuccess('Cache cleared successfully', [
        'category' => $category,
        'period' => $period
    ]);

} catch (Throwable $e) {
    appLogException($e, ['source' => 'admin/api/leaderboard-clear-cache.php']);
    sendServerError('An error occurred while clearing cache.', $e);
}


