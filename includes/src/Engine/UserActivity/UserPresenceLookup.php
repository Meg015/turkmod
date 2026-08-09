<?php

declare(strict_types=1);

namespace App\Engine\UserActivity;

use InvalidArgumentException;
use PDO;

final class UserPresenceLookup
{
    public const MAX_IDS_PER_BATCH = 100;

    public function __construct(private readonly ?UserPresence $presence = null)
    {
    }

    /**
     * @param iterable<mixed> $userIds
     * @return array<int,int>
     */
    public function normalizeIds(iterable $userIds, int $limit = self::MAX_IDS_PER_BATCH): array
    {
        $normalized = [];
        foreach ($userIds as $userId) {
            if (is_int($userId) || (is_string($userId) && ctype_digit(trim($userId)))) {
                $id = (int) $userId;
                if ($id > 0) {
                    $normalized[$id] = $id;
                }
            }
        }

        $normalized = array_values($normalized);
        sort($normalized, SORT_NUMERIC);
        if (count($normalized) > max(1, $limit)) {
            throw new InvalidArgumentException('Presence kullanıcı kimliği sınırı aşıldı.');
        }

        return $normalized;
    }

    /**
     * @param iterable<mixed> $userIds
     * @param array<int,bool> $onlineOverrides
     * @return array<int,array{user_id:int,visible:bool,is_online?:bool,status_label?:string,relative_label?:string,state_class?:string}>
     */
    public function lookup(PDO $pdo, iterable $userIds, array $onlineOverrides = []): array
    {
        $ids = $this->normalizeIds($userIds);
        if ($ids === []) {
            return [];
        }

        $results = [];
        foreach ($ids as $id) {
            $results[$id] = $this->hiddenResult($id);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, status, is_banned, deleted_at, last_activity_at
             FROM users
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute($ids);

        $presence = $this->presence ?? new UserPresence();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || !isset($results[$id]) || !$this->isVisibleRow($row)) {
                continue;
            }

            $description = $presence->describePublic(isset($row['last_activity_at']) ? (string) $row['last_activity_at'] : null);
            $isOnline = array_key_exists($id, $onlineOverrides)
                ? (bool) $onlineOverrides[$id]
                : (bool) ($description['is_online'] ?? false);
            $relativeLabel = $isOnline
                ? 'Şimdi çevrimiçi'
                : (trim((string) ($description['relative_label'] ?? '')) ?: 'Bilinmiyor');

            $results[$id] = [
                'user_id' => $id,
                'visible' => true,
                'is_online' => $isOnline,
                'status_label' => $isOnline ? 'Çevrimiçi' : 'Çevrimdışı',
                'relative_label' => $relativeLabel,
                'state_class' => $isOnline ? 'is-online' : 'is-offline',
            ];
        }

        return $results;
    }

    /** @param array<string,mixed> $row */
    public function isVisibleRow(array $row): bool
    {
        return strtolower(trim((string) ($row['status'] ?? ''))) === 'active'
            && (int) ($row['is_banned'] ?? 0) !== 1
            && empty($row['deleted_at']);
    }

    /**
     * @return array{user_id:int,visible:false}
     */
    public function hiddenResult(int $userId): array
    {
        return [
            'user_id' => max(0, $userId),
            'visible' => false,
        ];
    }

}
