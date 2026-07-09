<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Services;

use PDO;
use Throwable;

final class LeaderboardCacheService
{
    public function __construct(
        private ?LeaderboardService $service = null,
        private ?LeaderboardCalculator $calculator = null,
    ) {
    }

    public function setService(LeaderboardService $service): void
    {
        $this->service = $service;
    }

    public function setCalculator(LeaderboardCalculator $calculator): void
    {
        $this->calculator = $calculator;
    }

    public function isCacheStale(PDO $pdo, string $category, string $period): bool
    {
        $ttlKey = 'leaderboard_cache_ttl_' . $period;
        $settings = $this->service instanceof LeaderboardService
            ? $this->service->getSettings($pdo)
            : [];
        $ttl = (int) ($settings[$ttlKey] ?? 0);

        if ($ttl === 0) {
            try {
                $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
                $stmt->execute([$ttlKey]);
                $ttl = (int) $stmt->fetchColumn();
            } catch (Throwable) {
                $ttl = 0;
            }
        }

        if ($ttl === 0) {
            $defaultTtls = [
                'daily' => 900,
                'weekly' => 3600,
                'monthly' => 21600,
                'quarterly' => 43200,
                'yearly' => 86400,
                'all_time' => 86400,
            ];
            $ttl = $defaultTtls[$period] ?? 3600;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT calculated_at
                FROM leaderboard_cache
                WHERE category = ? AND period = ?
                ORDER BY calculated_at DESC
                LIMIT 1
            ");
            $stmt->execute([$category, $period]);
            $calculatedAt = $stmt->fetchColumn();
        } catch (Throwable) {
            return true;
        }

        if (!$calculatedAt) {
            return true;
        }

        $cacheAge = time() - strtotime((string) $calculatedAt);
        return $cacheAge > $ttl;
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,is_cached:bool,calculated_at?:string,period_range?:array{start:string,end:string}}
     */
    public function readCache(PDO $pdo, string $category, string $period, int $limit, int $offset, ?string $search = null): array
    {
        $searchTerm = trim((string) ($search ?? ''));
        $searchValue = $searchTerm !== '' ? $searchTerm : null;
        $userNameExpr = $this->userNameExpression($pdo, 'u');

        try {
            $stmt = $pdo->prepare("
                SELECT period_start
                FROM leaderboard_cache
                WHERE category = ? AND period = ?
                ORDER BY period_start DESC
                LIMIT 1
            ");
            $stmt->execute([$category, $period]);
            $latestPeriodStart = $stmt->fetchColumn();
        } catch (Throwable) {
            return ['data' => [], 'total' => 0, 'is_cached' => false];
        }

        if (!$latestPeriodStart) {
            return ['data' => [], 'total' => 0, 'is_cached' => false];
        }

        try {
            if ($searchValue !== null) {
                $searchPattern = '%' . strtr($searchValue, [
                    '\\' => '\\\\',
                    '%' => '\%',
                    '_' => '\_',
                ]) . '%';

                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM leaderboard_cache lc
                    INNER JOIN users u ON u.id = lc.user_id
                    WHERE lc.category = ? AND lc.period = ? AND lc.period_start = ?
                      AND {$userNameExpr} LIKE ? ESCAPE '\\'
                ");
                $countStmt->execute([$category, $period, $latestPeriodStart, $searchPattern]);
            } else {
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM leaderboard_cache
                    WHERE category = ? AND period = ? AND period_start = ?
                ");
                $countStmt->execute([$category, $period, $latestPeriodStart]);
            }
            $total = (int) $countStmt->fetchColumn();
        } catch (Throwable) {
            return ['data' => [], 'total' => 0, 'is_cached' => false];
        }

        try {
            if ($searchValue !== null) {
                $searchPattern = '%' . strtr($searchValue, [
                    '\\' => '\\\\',
                    '%' => '\%',
                    '_' => '\_',
                ]) . '%';

                $stmt = $pdo->prepare("
                    SELECT
                        lc.rank,
                        lc.user_id,
                        {$userNameExpr} as username,
                        u.avatar,
                        lc.score,
                        lc.metadata,
                        lc.calculated_at,
                        lc.period_start,
                        lc.period_end
                    FROM leaderboard_cache lc
                    JOIN users u ON u.id = lc.user_id
                    WHERE lc.category = ? AND lc.period = ? AND lc.period_start = ?
                      AND {$userNameExpr} LIKE ? ESCAPE '\\'
                    ORDER BY lc.rank ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$category, $period, $latestPeriodStart, $searchPattern, $limit, $offset]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT
                        lc.rank,
                        lc.user_id,
                        {$userNameExpr} as username,
                        u.avatar,
                        lc.score,
                        lc.metadata,
                        lc.calculated_at,
                        lc.period_start,
                        lc.period_end
                    FROM leaderboard_cache lc
                    JOIN users u ON u.id = lc.user_id
                    WHERE lc.category = ? AND lc.period = ? AND lc.period_start = ?
                    ORDER BY lc.rank ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->execute([$category, $period, $latestPeriodStart, $limit, $offset]);
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return ['data' => [], 'total' => 0, 'is_cached' => false];
        }

        if ($data === []) {
            return ['data' => [], 'total' => $total, 'is_cached' => true];
        }

        $calculatedAt = (string) ($data[0]['calculated_at'] ?? '');
        $periodStart = (string) ($data[0]['period_start'] ?? '');
        $periodEnd = (string) ($data[0]['period_end'] ?? '');
        $previousRanks = $this->getPreviousPeriodRanks($pdo, $category, $period, $periodStart);

        foreach ($data as &$row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $currentRank = (int) ($row['rank'] ?? 0);
            $row['metadata'] = json_decode((string) ($row['metadata'] ?? '{}'), true);
            if (!is_array($row['metadata'])) {
                $row['metadata'] = [];
            }

            $row['score'] = (float) ($row['score'] ?? 0);
            $row['count'] = (int) ($row['score'] ?? 0);
            $row['user_id'] = $userId;
            $row['rank'] = $currentRank;

            if (isset($previousRanks[$userId])) {
                $previousRank = (int) $previousRanks[$userId];
                $row['previous_rank'] = $previousRank;
                $row['change'] = $previousRank - $currentRank;
            } else {
                $row['previous_rank'] = null;
                $row['change'] = 0;
            }

            if ($this->service instanceof LeaderboardService) {
                $row = $this->service->decorateRow($row);
            }

            unset($row['calculated_at'], $row['period_start'], $row['period_end']);
        }
        unset($row);

        return [
            'data' => $data,
            'total' => $total,
            'is_cached' => true,
            'calculated_at' => $calculatedAt,
            'period_range' => [
                'start' => $periodStart,
                'end' => $periodEnd,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findUserRank(PDO $pdo, string $category, string $period, int $userId): ?array
    {
        $userNameExpr = $this->userNameExpression($pdo, 'u');
        try {
            $snapshotStmt = $pdo->prepare("
                SELECT period_start
                FROM leaderboard_cache
                WHERE category = ? AND period = ?
                ORDER BY period_start DESC
                LIMIT 1
            ");
            $snapshotStmt->execute([$category, $period]);
            $latestPeriodStart = $snapshotStmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }

        if (!$latestPeriodStart) {
            return null;
        }

        try {
            $countStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM leaderboard_cache
                WHERE category = ? AND period = ? AND period_start = ?
            ");
            $countStmt->execute([$category, $period, $latestPeriodStart]);
            $totalUsers = (int) $countStmt->fetchColumn();
        } catch (Throwable) {
            $totalUsers = 0;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    lc.rank,
                    lc.user_id,
                    {$userNameExpr} as username,
                    u.avatar,
                    lc.score,
                    lc.metadata,
                    lc.calculated_at,
                    lc.period_start,
                    lc.period_end
                FROM leaderboard_cache lc
                JOIN users u ON u.id = lc.user_id
                WHERE lc.category = ? AND lc.period = ? AND lc.period_start = ? AND lc.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$category, $period, $latestPeriodStart, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $row['metadata'] = json_decode((string) ($row['metadata'] ?? '{}'), true);
        if (!is_array($row['metadata'])) {
            $row['metadata'] = [];
        }

        $row['score'] = (float) ($row['score'] ?? 0);
        $row['count'] = (int) ($row['score'] ?? 0);
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $row['rank'] = (int) ($row['rank'] ?? 0);
        $row['total_users'] = $totalUsers;

        $previousRanks = $this->getPreviousPeriodRanks($pdo, $category, $period, (string) $latestPeriodStart);
        $previousRank = isset($previousRanks[$userId]) ? (int) $previousRanks[$userId] : null;
        $row['previous_rank'] = $previousRank;
        $row['change'] = $previousRank !== null ? $previousRank - (int) $row['rank'] : 0;

        if ($this->service instanceof LeaderboardService) {
            $row = $this->service->decorateRow($row);
        }

        unset($row['calculated_at'], $row['period_start'], $row['period_end']);

        return $row;
    }

    private function userHasUsernameColumn(PDO $pdo): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
            $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private function userNameExpression(PDO $pdo, string $alias = 'u'): string
    {
        return $this->userHasUsernameColumn($pdo)
            ? "COALESCE(NULLIF({$alias}.username, ''), CONCAT('user-', {$alias}.id))"
            : "CONCAT('user-', {$alias}.id)";
    }

    /**
     * @return array<int,int>
     */
    public function getPreviousPeriodRanks(PDO $pdo, string $category, string $period, string $currentPeriodStart): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT user_id, rank
                FROM leaderboard_cache
                WHERE category = ? AND period = ? AND period_start < ?
                ORDER BY period_start DESC
                LIMIT 100
            ");
            $stmt->execute([$category, $period, $currentPeriodStart]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $rows = [];
        }

        if ($rows === []) {
            try {
                $stmt = $pdo->prepare("
                    SELECT user_id, rank
                    FROM leaderboard_history
                    WHERE category = ? AND period = ? AND snapshot_date < ?
                    ORDER BY snapshot_date DESC
                    LIMIT 100
                ");
                $stmt->execute([$category, $period, $currentPeriodStart]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $rows = [];
            }
        }

        $ranks = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0 && !isset($ranks[$userId])) {
                $ranks[$userId] = (int) ($row['rank'] ?? 0);
            }
        }

        return $ranks;
    }

    /**
     * @param array{data:array<int,array<string,mixed>>} $calculatedData
     */
    public function writeCache(
        PDO $pdo,
        string $category,
        string $period,
        array $calculatedData,
        string $periodStart,
        string $periodEnd,
    ): void {
        $pdo->beginTransaction();

        try {
            $deleteStmt = $pdo->prepare("
                DELETE FROM leaderboard_cache
                WHERE category = ? AND period = ? AND period_start = ?
            ");
            $deleteStmt->execute([$category, $period, $periodStart]);

            $insertStmt = $pdo->prepare("
                INSERT INTO leaderboard_cache
                (user_id, category, period, rank, score, metadata, calculated_at, period_start, period_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $historyStmt = $pdo->prepare("
                INSERT INTO leaderboard_history
                (user_id, category, period, rank, score, snapshot_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $now = date('Y-m-d H:i:s');
            $snapshotDate = date('Y-m-d');

            foreach ($calculatedData['data'] as $row) {
                $metadata = json_encode($row['metadata'] ?? []);
                $insertStmt->execute([
                    $row['user_id'],
                    $category,
                    $period,
                    $row['rank'],
                    $row['score'],
                    $metadata,
                    $now,
                    $periodStart,
                    $periodEnd,
                ]);

                $historyStmt->execute([
                    $row['user_id'],
                    $category,
                    $period,
                    $row['rank'],
                    $row['score'],
                    $snapshotDate,
                    $now,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function clearCache(PDO $pdo, ?string $category = null, ?string $period = null): void
    {
        $sql = "DELETE FROM leaderboard_cache WHERE 1=1";
        $params = [];

        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if ($period !== null) {
            $sql .= " AND period = ?";
            $params[] = $period;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @return array{affected_users:int,message:string}
     */
    public function recalculate(PDO $pdo, string $category, string $period, bool $force = false): array
    {
        if (!$force && !$this->isCacheStale($pdo, $category, $period)) {
            return ['affected_users' => 0, 'message' => 'Cache is still fresh'];
        }

        $service = $this->service ?? new LeaderboardService();
        $calculator = $this->calculator ?? new LeaderboardCalculator($service);
        $periodDates = $service->getPeriodDates($period);

        $data = $calculator->calculatePeriod(
            $pdo,
            $category,
            $period,
            $periodDates['start'],
            $periodDates['end'],
            999999,
            0,
        );

        $this->clearCache($pdo, $category, $period);
        if (!empty($data['data'])) {
            $this->writeCache($pdo, $category, $period, $data, $periodDates['start'], $periodDates['end']);
        }

        return [
            'affected_users' => count($data['data']),
            'message' => 'Leaderboard recalculated successfully',
        ];
    }
}
