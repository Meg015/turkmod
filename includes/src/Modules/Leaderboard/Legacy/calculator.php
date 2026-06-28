<?php

declare(strict_types=1);

use App\Modules\Leaderboard\Services\LeaderboardCalculator;
use App\Modules\Leaderboard\Services\LeaderboardService;

function leaderboardCalculatorInstance(): LeaderboardCalculator
{
    static $calculator = null;

    if ($calculator instanceof LeaderboardCalculator) {
        return $calculator;
    }

    $service = function_exists('leaderboardServiceInstance')
        ? leaderboardServiceInstance()
        : new LeaderboardService();

    $calculator = new LeaderboardCalculator($service);
    $service->setCalculator($calculator);

    return $calculator;
}

function leaderboardCalculateRealtime(PDO $pdo, string $category, int $limit, int $offset): array
{
    return leaderboardCalculatorInstance()->calculateRealtime($pdo, $category, $limit, $offset);
}

function leaderboardCalculatePeriod(
    PDO $pdo,
    string $category,
    string $period,
    string $periodStart,
    string $periodEnd,
    int $limit,
    int $offset,
): array {
    return leaderboardCalculatorInstance()->calculatePeriod(
        $pdo,
        $category,
        $period,
        $periodStart,
        $periodEnd,
        $limit,
        $offset,
    );
}

function leaderboardGetSetting(PDO $pdo, string $key, mixed $default): mixed
{
    return leaderboardCalculatorInstance()->getSetting($pdo, $key, $default);
}

function buildAdminExclusionClause(bool $excludeAdmins): string
{
    return leaderboardCalculatorInstance()->buildAdminExclusionClause($excludeAdmins);
}

function assignRanks(array $data): array
{
    return leaderboardCalculatorInstance()->assignRanks($data);
}

function calculateCountMetricPeriod(
    PDO $pdo,
    string $category,
    string $periodStart,
    string $periodEnd,
    int $limit,
    int $offset,
    bool $excludeAdmins,
): array {
    return leaderboardCalculatorInstance()->calculateCountMetricPeriod(
        $pdo,
        $category,
        $periodStart,
        $periodEnd,
        $limit,
        $offset,
        $excludeAdmins,
    );
}
