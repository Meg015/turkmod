<?php

declare(strict_types=1);

use App\Modules\Leaderboard\Services\LeaderboardCacheService;
use App\Modules\Leaderboard\Services\LeaderboardCalculator;
use App\Modules\Leaderboard\Services\LeaderboardService;

function leaderboardCacheServiceInstance(): LeaderboardCacheService
{
    static $cacheService = null;

    if ($cacheService instanceof LeaderboardCacheService) {
        return $cacheService;
    }

    $service = function_exists('leaderboardServiceInstance')
        ? leaderboardServiceInstance()
        : new LeaderboardService();
    $calculator = function_exists('leaderboardCalculatorInstance')
        ? leaderboardCalculatorInstance()
        : new LeaderboardCalculator($service);

    $cacheService = new LeaderboardCacheService($service, $calculator);
    $service->setCalculator($calculator);
    $service->setCacheService($cacheService);

    return $cacheService;
}

function leaderboardIsCacheStale(PDO $pdo, string $category, string $period): bool
{
    return leaderboardCacheServiceInstance()->isCacheStale($pdo, $category, $period);
}

function leaderboardReadCache(PDO $pdo, string $category, string $period, int $limit, int $offset, ?string $search = null): array
{
    return leaderboardCacheServiceInstance()->readCache($pdo, $category, $period, $limit, $offset, $search);
}

function getPreviousPeriodRanks(PDO $pdo, string $category, string $period, string $currentPeriodStart): array
{
    return leaderboardCacheServiceInstance()->getPreviousPeriodRanks($pdo, $category, $period, $currentPeriodStart);
}

function leaderboardWriteCache(
    PDO $pdo,
    string $category,
    string $period,
    array $calculatedData,
    string $periodStart,
    string $periodEnd,
): void {
    leaderboardCacheServiceInstance()->writeCache(
        $pdo,
        $category,
        $period,
        $calculatedData,
        $periodStart,
        $periodEnd,
    );
}

function leaderboardClearCache(PDO $pdo, ?string $category = null, ?string $period = null): void
{
    leaderboardCacheServiceInstance()->clearCache($pdo, $category, $period);
}

function leaderboardRecalculate(PDO $pdo, string $category, string $period, bool $force = false): array
{
    return leaderboardCacheServiceInstance()->recalculate($pdo, $category, $period, $force);
}
