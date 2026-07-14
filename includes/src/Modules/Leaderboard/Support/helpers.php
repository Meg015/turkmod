<?php

declare(strict_types=1);

use App\Modules\Leaderboard\Services\LeaderboardCacheService;
use App\Modules\Leaderboard\Services\LeaderboardCalculator;
use App\Modules\Leaderboard\Services\LeaderboardService;

function leaderboardServiceInstance(): LeaderboardService
{
    static $service = null;

    if ($service instanceof LeaderboardService) {
        return $service;
    }

    $service = new LeaderboardService();
    $calculator = new LeaderboardCalculator($service);
    $cache = new LeaderboardCacheService($service, $calculator);
    $service->setCalculator($calculator);
    $service->setCacheService($cache);

    return $service;
}

function leaderboardGetSettings(PDO $pdo, bool $forceReload = false): array
{
    return leaderboardServiceInstance()->getSettings($pdo, $forceReload);
}

function leaderboardGetCategories(): array
{
    return leaderboardServiceInstance()->getCategories();
}

function leaderboardGetValidCategories(): array
{
    return leaderboardServiceInstance()->getValidCategories();
}

function leaderboardProfileUrlForRow(array $row): string
{
    return leaderboardServiceInstance()->profileUrlForRow($row);
}

function leaderboardAvatarUrlForRow(array $row, ?string $baseUri = null): string
{
    return leaderboardServiceInstance()->avatarUrlForRow($row, $baseUri);
}

function leaderboardDecorateRow(array $row, ?string $baseUri = null): array
{
    return leaderboardServiceInstance()->decorateRow($row, $baseUri);
}

function leaderboardGetPeriodDates(string $period): array
{
    return leaderboardServiceInstance()->getPeriodDates($period);
}

function leaderboardGetData(PDO $pdo, string $category, string $period, int $limit = 50, int $offset = 0, ?string $search = null): array
{
    return leaderboardServiceInstance()->getData($pdo, $category, $period, $limit, $offset, $search);
}

function leaderboardGetUserRank(PDO $pdo, int $userId, ?string $category = null, ?string $period = null): array
{
    return leaderboardServiceInstance()->getUserRank($pdo, $userId, $category, $period);
}
