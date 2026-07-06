<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Services;

use PDO;
use PDOException;
use Throwable;

final class LeaderboardCalculator
{
    public function __construct(private ?LeaderboardService $service = null)
    {
    }

    public function setService(LeaderboardService $service): void
    {
        $this->service = $service;
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,is_cached:bool}
     */
    public function calculateRealtime(PDO $pdo, string $category, int $limit, int $offset): array
    {
        $excludeAdmins = (bool) $this->getSetting($pdo, 'leaderboard_exclude_admins', false);
        $result = $this->calculateCountMetricPeriod(
            $pdo,
            $category,
            '2000-01-01 00:00:00',
            '2099-12-31 23:59:59',
            $limit,
            $offset,
            $excludeAdmins,
        );
        $result['is_cached'] = false;

        return $result;
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int}
     */
    public function calculatePeriod(
        PDO $pdo,
        string $category,
        string $period,
        string $periodStart,
        string $periodEnd,
        int $limit,
        int $offset,
    ): array {
        $excludeAdmins = (bool) $this->getSetting($pdo, 'leaderboard_exclude_admins', false);

        return $this->calculateCountMetricPeriod(
            $pdo,
            $category,
            $periodStart,
            $periodEnd,
            $limit,
            $offset,
            $excludeAdmins,
        );
    }

    public function getSetting(PDO $pdo, string $key, mixed $default): mixed
    {
        $settings = $this->service instanceof LeaderboardService
            ? $this->service->getSettings($pdo)
            : [];

        if (array_key_exists($key, $settings)) {
            $value = $settings[$key];
            if (is_string($default) || $default === null) {
                return $value;
            }

            if (is_bool($default)) {
                return (string) $value === '1';
            }

            if (is_int($default)) {
                return is_numeric($value) ? (int) $value : $default;
            }

            return $value;
        }

        try {
            $stmt = $pdo->prepare("SELECT value, type FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $result = false;
        }

        if (!$result) {
            return $default;
        }

        $value = $result['value'] ?? null;
        $type = (string) ($result['type'] ?? 'string');

        return match ($type) {
            'boolean', 'bool' => (string) $value === '1',
            'number', 'int' => is_numeric($value) ? (int) $value : $default,
            default => $value,
        };
    }

    public function buildAdminExclusionClause(bool $excludeAdmins): string
    {
        if (!$excludeAdmins) {
            return '';
        }

        return " AND NOT EXISTS (
            SELECT 1
            FROM user_group_members ugm
            INNER JOIN user_groups ug ON ug.id = ugm.group_id
            LEFT JOIN user_group_permissions ugp ON ugp.group_id = ug.id
                AND ugp.permission_value = 1
                AND ugp.permission_key IN ('*', 'admin.access')
            WHERE ugm.user_id = u.id
                AND ug.is_active = 1
                AND (ug.slug = 'admin' OR ugp.permission_key IS NOT NULL)
        )";
    }

    public function buildBannedExclusionClause(bool $excludeBanned): string
    {
        if (!$excludeBanned) {
            return '';
        }

        return ' AND (u.is_banned = 0 OR u.is_banned IS NULL)';
    }

    /**
     * @param array<int,array<string,mixed>> $data
     * @return array<int,array<string,mixed>>
     */
    public function assignRanks(array $data): array
    {
        $rank = 1;
        $previousScore = null;
        $sameRankCount = 0;

        foreach ($data as &$row) {
            $score = (float) ($row['score'] ?? 0);
            if ($previousScore !== null && $score < $previousScore) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $row['rank'] = $rank;
            $previousScore = $score;
        }

        return $data;
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int}
     */
    public function calculateCountMetricPeriod(
        PDO $pdo,
        string $category,
        string $periodStart,
        string $periodEnd,
        int $limit,
        int $offset,
        bool $excludeAdmins,
    ): array {
        $adminClause = $this->buildAdminExclusionClause($excludeAdmins);
        $bannedClause = $this->buildBannedExclusionClause((bool) $this->getSetting($pdo, 'leaderboard_exclude_banned', true));
        $metricSql = match ($category) {
            'daily_login' => "(SELECT COUNT(*) FROM activity_logs a
                WHERE a.actor_id = u.id
                AND a.action = 'user_login'
                AND a.created_at >= ? AND a.created_at <= ?)",
            'topics' => "(SELECT COUNT(*) FROM topics t
                WHERE t.author_id = u.id
                AND t.status = 'published'
                AND COALESCE(t.published_at, t.created_at) >= ? AND COALESCE(t.published_at, t.created_at) <= ?)",
            'comments' => "(SELECT COUNT(*) FROM comments c
                WHERE c.user_id = u.id
                AND c.status = 'approved'
                AND c.created_at >= ? AND c.created_at <= ?)",
            default => null,
        };

        if ($metricSql === null) {
            return ['data' => [], 'total' => 0];
        }

        $countSql = "
            SELECT COUNT(*) FROM (
                SELECT u.id, {$metricSql} AS metric_count
                FROM users u
                WHERE u.status = 'active'
                {$adminClause}
                {$bannedClause}
            ) counted_users
            WHERE metric_count > 0
        ";

        try {
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$periodStart, $periodEnd]);
            $total = (int) $countStmt->fetchColumn();
        } catch (PDOException) {
            return ['data' => [], 'total' => 0];
        }

        $sql = "
            SELECT * FROM (
                SELECT
                    u.id AS user_id,
                    u.name AS username,
                    u.avatar,
                    {$metricSql} AS score
                FROM users u
                WHERE u.status = 'active'
                {$adminClause}
                {$bannedClause}
            ) counted_users
            WHERE score > 0
            ORDER BY score DESC, user_id ASC
            LIMIT ? OFFSET ?
        ";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$periodStart, $periodEnd, $limit, $offset]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return ['data' => [], 'total' => 0];
        }

        $data = $this->assignRanks($data);
        $metadataKey = match ($category) {
            'daily_login' => 'daily_logins',
            'topics' => 'topics',
            'comments' => 'comments',
            default => 'count',
        };

        foreach ($data as &$row) {
            $count = (int) ($row['score'] ?? 0);
            $row['score'] = (float) $count;
            $row['count'] = $count;
            $row['metadata'] = [$metadataKey => $count];

            if ($this->service instanceof LeaderboardService) {
                $row = $this->service->decorateRow($row);
            }
        }

        return [
            'data' => $data,
            'total' => $total,
        ];
    }
}
