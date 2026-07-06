<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Services;

use DateTime;
use InvalidArgumentException;
use PDO;
use Throwable;

final class LeaderboardService
{
    /** @var array<string,string>|null */
    private ?array $settingsCache = null;

    private ?LeaderboardCalculator $calculator = null;

    private ?LeaderboardCacheService $cacheService = null;

    public function setCalculator(LeaderboardCalculator $calculator): void
    {
        $this->calculator = $calculator;
    }

    public function setCacheService(LeaderboardCacheService $cacheService): void
    {
        $this->cacheService = $cacheService;
    }

    /**
     * @return array<string,string>
     */
    public function getSettings(PDO $pdo, bool $forceReload = false): array
    {
        if ($this->settingsCache !== null && !$forceReload) {
            return $this->settingsCache;
        }

        $defaults = [
            'leaderboard_enabled' => '1',
            'leaderboard_disabled_message' => 'Liderlik tablosu su anda kapali. Lutfen daha sonra tekrar kontrol edin.',
            'leaderboard_cache_ttl_daily' => '900',
            'leaderboard_cache_ttl_weekly' => '3600',
            'leaderboard_cache_ttl_monthly' => '21600',
            'leaderboard_cache_ttl_quarterly' => '43200',
            'leaderboard_cache_ttl_yearly' => '86400',
            'leaderboard_cache_ttl_all_time' => '86400',
            'leaderboard_exclude_admins' => '1',
            'leaderboard_show_sidebar' => '1',
            'leaderboard_sidebar_limit' => '5',
            'leaderboard_show_profile' => '1',
            'leaderboard_profile_limit' => '10',
            'leaderboard_exclude_banned' => '1',
        ];

        $this->settingsCache = $defaults;

        if (function_exists('getAdminSettings')) {
            foreach (getAdminSettings($pdo) as $key => $value) {
                if (array_key_exists($key, $this->settingsCache)) {
                    $this->settingsCache[$key] = (string) $value;
                }
            }

            return $this->settingsCache;
        }

        try {
            $stmt = $pdo->query("SELECT `key`, value FROM settings WHERE `key` LIKE 'leaderboard_%'");
            while ($row = $stmt->fetch()) {
                $key = (string) ($row['key'] ?? '');
                if (array_key_exists($key, $this->settingsCache)) {
                    $this->settingsCache[$key] = (string) ($row['value'] ?? '');
                }
            }
        } catch (Throwable) {
        }

        return $this->settingsCache;
    }

    /**
     * @return array<string,array{name:string,icon:string,desc:string,metadata_key:string,metadata_label:string}>
     */
    public function getCategories(): array
    {
        return [
            'daily_login' => [
                'name' => 'Günlük Giriş',
                'icon' => 'bi-box-arrow-in-right',
                'desc' => 'Seçili dönemde en çok giriş yapan kullanıcılar',
                'metadata_key' => 'daily_logins',
                'metadata_label' => 'Giriş',
            ],
            'topics' => [
                'name' => 'Konu Sayısı',
                'icon' => 'bi-file-earmark-text',
                'desc' => 'Seçili dönemde en çok konu yayımlayan kullanıcılar',
                'metadata_key' => 'topics',
                'metadata_label' => 'Konu',
            ],
            'comments' => [
                'name' => 'Yorum Sayısı',
                'icon' => 'bi-chat-left-text',
                'desc' => 'Seçili dönemde en çok onaylı yorum yapan kullanıcılar',
                'metadata_key' => 'comments',
                'metadata_label' => 'Yorum',
            ],
        ];
    }

    /**
     * @return array<int,string>
     */
    public function getValidCategories(): array
    {
        return array_keys($this->getCategories());
    }

    /**
     * @param array<string,mixed> $row
     */
    public function profileUrlForRow(array $row): string
    {
        $userId = (int) ($row['user_id'] ?? $row['id'] ?? $row['author_id'] ?? 0);
        if ($userId <= 0) {
            return '#';
        }

        $displayName = (string) ($row['username'] ?? $row['name'] ?? $row['author'] ?? 'üye');
        if (function_exists('publicProfileUrl')) {
            return publicProfileUrl([
                'id' => $userId,
                'name' => $displayName,
            ]);
        }

        return rtrim((string) ($GLOBALS['baseUri'] ?? ''), '/') . '/profil/' . rawurlencode((string) $userId);
    }

    /**
     * @param array<string,mixed> $row
     */
    public function avatarUrlForRow(array $row, ?string $baseUri = null): string
    {
        $avatar = trim((string) ($row['avatar_url'] ?? $row['avatar'] ?? $row['avatar_path'] ?? ''));
        if ($avatar !== '' && function_exists('resolveAvatarUrl')) {
            $resolved = resolveAvatarUrl($avatar, $baseUri, false);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        if ($avatar !== '') {
            $base = rtrim((string) ($baseUri ?? ($GLOBALS['baseUri'] ?? '')), '/');
            if (preg_match('~^(https?:)?//~i', $avatar) === 1) {
                return $avatar;
            }

            if ($base !== '') {
                return $base . '/' . ltrim($avatar, '/');
            }

            return '/' . ltrim($avatar, '/');
        }

        if (function_exists('defaultAvatarUrl')) {
            return defaultAvatarUrl($baseUri);
        }

        return rtrim((string) ($baseUri ?? ($GLOBALS['baseUri'] ?? '')), '/') . '/assets/images/noavatar-neon-helmet.svg';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function decorateRow(array $row, ?string $baseUri = null): array
    {
        if (!isset($row['username']) && isset($row['name'])) {
            $row['username'] = (string) $row['name'];
        }

        if (!isset($row['avatar_path']) && isset($row['avatar'])) {
            $row['avatar_path'] = (string) $row['avatar'];
        }

        if (!isset($row['rank_change']) && isset($row['change'])) {
            $row['rank_change'] = (int) $row['change'];
        }

        if (!isset($row['change']) && isset($row['rank_change'])) {
            $row['change'] = (int) $row['rank_change'];
        }

        $row['profile_url'] = (string) ($row['profile_url'] ?? $this->profileUrlForRow($row));
        $row['avatar_url'] = (string) ($row['avatar_url'] ?? $this->avatarUrlForRow($row, $baseUri));

        return $row;
    }

    /**
     * @return array{start:string,end:string,start_date:string,end_date:string}
     */
    public function getPeriodDates(string $period): array
    {
        $now = new DateTime();
        $start = clone $now;
        $end = clone $now;

        switch ($period) {
            case 'daily':
                $start->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
            case 'weekly':
                $start->modify('monday this week')->setTime(0, 0, 0);
                $end->modify('sunday this week')->setTime(23, 59, 59);
                break;
            case 'monthly':
                $start->modify('first day of this month')->setTime(0, 0, 0);
                $end->modify('last day of this month')->setTime(23, 59, 59);
                break;
            case 'quarterly':
                $month = (int) $now->format('n');
                $quarter = (int) ceil($month / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                $start->setDate((int) $now->format('Y'), $startMonth, 1)->setTime(0, 0, 0);
                $end->setDate((int) $now->format('Y'), $startMonth + 2, 1)->modify('last day of this month')->setTime(23, 59, 59);
                break;
            case 'yearly':
                $start->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0);
                $end->setDate((int) $now->format('Y'), 12, 31)->setTime(23, 59, 59);
                break;
            case 'all_time':
                $start->setDate(2000, 1, 1)->setTime(0, 0, 0);
                $end->setDate(2099, 12, 31)->setTime(23, 59, 59);
                break;
            default:
                throw new InvalidArgumentException('Invalid period: ' . $period);
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ];
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,is_cached:bool}
     */
    public function getData(PDO $pdo, string $category, string $period, int $limit = 50, int $offset = 0, ?string $search = null): array
    {
        $settings = $this->getSettings($pdo);
        if (($settings['leaderboard_enabled'] ?? '1') !== '1') {
            return ['data' => [], 'total' => 0, 'is_cached' => false];
        }

        $calculator = $this->calculator ?? new LeaderboardCalculator($this);
        $cache = $this->cacheService ?? new LeaderboardCacheService($this, $calculator);
        $searchTerm = trim((string) ($search ?? ''));
        $searchValue = $searchTerm !== '' ? $searchTerm : null;

        $cachedData = $cache->readCache($pdo, $category, $period, $limit, $offset, $searchValue);
        if (($cachedData['is_cached'] ?? false) === true) {
            if ($cache->isCacheStale($pdo, $category, $period)) {
                try {
                    $cache->recalculate($pdo, $category, $period, false);
                } catch (Throwable) {
                }

                $cachedData = $cache->readCache($pdo, $category, $period, $limit, $offset, $searchValue);
            }

            return $cachedData;
        }

        try {
            $cache->recalculate($pdo, $category, $period, false);
        } catch (Throwable) {
        }

        $cachedData = $cache->readCache($pdo, $category, $period, $limit, $offset, $searchValue);
        if (($cachedData['total'] ?? 0) > 0 || ($cachedData['is_cached'] ?? false)) {
            return $cachedData;
        }

        $periodDates = $this->getPeriodDates($period);
        if ($searchValue !== null) {
            $data = $calculator->calculatePeriod(
                $pdo,
                $category,
                $period,
                $periodDates['start'],
                $periodDates['end'],
                999999,
                0,
            );

            $filteredRows = array_values(array_filter(
                $data['data'] ?? [],
                static function (array $row) use ($searchValue): bool {
                    $username = (string) ($row['username'] ?? '');
                    return stripos($username, $searchValue) !== false;
                },
            ));

            $filteredTotal = count($filteredRows);

            return [
                'data' => array_slice($filteredRows, $offset, $limit),
                'total' => $filteredTotal,
                'is_cached' => false,
            ];
        }

        $data = $calculator->calculatePeriod(
            $pdo,
            $category,
            $period,
            $periodDates['start'],
            $periodDates['end'],
            $limit,
            $offset,
        );
        $data['is_cached'] = false;

        return $data;
    }

    /**
     * @return array{ranks:array<string,array<string,array<string,int|float>>>}
     */
    public function getUserRank(PDO $pdo, int $userId, ?string $category = null, ?string $period = null): array
    {
        $settings = $this->getSettings($pdo);
        if (($settings['leaderboard_enabled'] ?? '1') !== '1') {
            return ['ranks' => []];
        }

        $categories = $category ? [$category] : $this->getValidCategories();
        $periods = $period ? [$period] : ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all_time'];
        $ranks = [];
        $cache = $this->cacheService ?? new LeaderboardCacheService($this, $this->calculator ?? new LeaderboardCalculator($this));

        foreach ($categories as $categoryKey) {
            foreach ($periods as $periodKey) {
                if ($cache->isCacheStale($pdo, (string) $categoryKey, (string) $periodKey)) {
                    try {
                        $cache->recalculate($pdo, (string) $categoryKey, (string) $periodKey, false);
                    } catch (Throwable) {
                    }
                }

                $userRank = $cache->findUserRank($pdo, (string) $categoryKey, (string) $periodKey, $userId);

                if ($userRank === null) {
                    continue;
                }

                $rank = (int) ($userRank['rank'] ?? 0);
                $count = (int) ($userRank['score'] ?? $userRank['count'] ?? 0);
                $totalUsers = (int) ($userRank['total_users'] ?? 0);
                $ranks[(string) $categoryKey][(string) $periodKey] = [
                    'rank' => $rank,
                    'count' => $count,
                    'total_users' => $totalUsers,
                    'percentile' => $totalUsers > 0
                        ? round((1 - ($rank / $totalUsers)) * 100, 1)
                        : 0.0,
                ];
            }
        }

        return ['ranks' => $ranks];
    }
}
