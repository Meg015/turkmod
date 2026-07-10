<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Engine/Auth/Legacy/helpers.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Legacy/helpers.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Legacy/cache-manager.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Legacy/calculator.php';

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

$category = $_POST['category'] ?? null;
$period = $_POST['period'] ?? null;
$force = isset($_POST['force']) && $_POST['force'] === 'true';

if (!$category || !$period) {
    sendValidationError('Both category and period parameters are required');
}

$validCategories = function_exists('leaderboardGetValidCategories')
    ? leaderboardGetValidCategories()
    : ['daily_login', 'topics', 'comments'];
if (!in_array($category, $validCategories, true)) {
    sendError('invalid_category', 'Category must be one of: ' . implode(', ', $validCategories), 422);
}

    $validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all_time'];
if (!in_array($period, $validPeriods, true)) {
    sendError('invalid_period', 'Period must be one of: ' . implode(', ', $validPeriods), 422);
}

try {
    $startTime = microtime(true);
    $result = leaderboardRecalculate($pdo, $category, $period, $force);
    $executionTime = (int)((microtime(true) - $startTime) * 1000);

    logActivity($pdo, 'leaderboard_recalculated', 'leaderboard', null, [
        'category' => $category,
        'period' => $period,
        'force' => $force,
        'affected_users' => $result['affected_users'] ?? 0,
        'calculation_time_ms' => $executionTime,
        'actor_id' => $currentUserId,
    ]);
    adminAuditLogger()->logAction($pdo, 'leaderboard_recalculated', 'leaderboard', 0, 'Liderlik yeniden hesaplandı', [], [
        'category' => $category,
        'period' => $period,
        'force' => $force,
        'affected_users' => $result['affected_users'] ?? 0,
        'calculation_time_ms' => $executionTime,
    ], false);

    sendSuccess('Leaderboard recalculated successfully', [
        'category' => $category,
        'period' => $period,
        'affected_users' => $result['affected_users'] ?? 0,
        'calculation_time_ms' => $executionTime
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => 'admin/api/leaderboard-recalculate.php']);
    sendServerError('An error occurred while recalculating leaderboard.', $e);
}


