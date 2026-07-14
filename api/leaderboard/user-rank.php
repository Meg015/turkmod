<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Support/helpers.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Support/cache-manager.php';
require_once __DIR__ . '/../../includes/src/Modules/Leaderboard/Support/calculator.php';

$pdo = requireDatabaseConnection($pdo ?? null);

// Rate limiting
$clientKey = 'api_user_rank_' . ($_SERVER['REMOTE_ADDR'] ?? 'guest');
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$leaderboardRateLimit = max(1, (int)($settings['api_leaderboard_rate_limit'] ?? 60));
$leaderboardRateWindow = max(1, (int)($settings['api_leaderboard_rate_window'] ?? 1));

if (!checkRateLimit($clientKey, $leaderboardRateLimit, $leaderboardRateWindow)) {
    sendRateLimitError(max(60, $leaderboardRateWindow * 60));
}
incrementRateLimit($clientKey, $leaderboardRateWindow);

// Get parameters
$userId = $_GET['user_id'] ?? null;
$category = $_GET['category'] ?? null;
$period = $_GET['period'] ?? null;

// Validation
if (!$userId) {
    sendError('missing_user_id', 'user_id parameter is required.', 400);
}

if (!is_numeric($userId)) {
    sendError('invalid_user_id', 'user_id must be a valid integer.', 400);
}

$userId = (int)$userId;

// Validate category if provided
if ($category !== null) {
    $validCategories = leaderboardGetValidCategories();
    if (!in_array($category, $validCategories, true)) {
        sendError('invalid_category', 'Category must be one of: ' . implode(', ', $validCategories), 400);
    }
}

// Validate period if provided
if ($period !== null) {
    $validPeriods = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all_time'];
    if (!in_array($period, $validPeriods, true)) {
        sendError('invalid_period', 'Period must be one of: ' . implode(', ', $validPeriods), 400);
    }
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendNotFound('User not found or inactive.');
    }

    // Get user ranks
    $rankData = leaderboardGetUserRank($pdo, $userId, $category, $period);

    sendSuccess('OK', [
        'user_id' => $userId,
        'username' => (string) ($user['username'] ?? ''),
        'ranks' => $rankData['ranks'],
    ]);
} catch (Throwable $e) {
    appLogException($e, ['source' => 'api/leaderboard/user-rank.php']);
    sendServerError('An error occurred while fetching user rank data.', $e);
}
