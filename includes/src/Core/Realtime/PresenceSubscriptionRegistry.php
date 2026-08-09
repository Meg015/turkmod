<?php

declare(strict_types=1);

namespace App\Core\Realtime;

use InvalidArgumentException;

final class PresenceSubscriptionRegistry
{
    public const MAX_IDS_PER_MESSAGE = 100;

    public const MAX_IDS_PER_CONNECTION = 500;

    /** @var array<string,int> */
    private array $connectionUsers = [];

    /** @var array<int,array<string,true>> */
    private array $userConnections = [];

    /** @var array<string,array<int,true>> */
    private array $connectionSubscriptions = [];

    /** @var array<int,array<string,true>> */
    private array $subscriberConnections = [];

    public function attach(string $connectionId, int $userId): bool
    {
        $connectionId = trim($connectionId);
        if ($connectionId === '' || $userId <= 0) {
            throw new InvalidArgumentException('Geçersiz presence bağlantısı.');
        }

        if (isset($this->connectionUsers[$connectionId])) {
            return false;
        }

        $isFirstConnection = empty($this->userConnections[$userId]);
        $this->connectionUsers[$connectionId] = $userId;
        $this->userConnections[$userId][$connectionId] = true;
        $this->connectionSubscriptions[$connectionId] = [];

        return $isFirstConnection;
    }

    /**
     * @return array{user_id:int,is_last_connection:bool}|null
     */
    public function detach(string $connectionId): ?array
    {
        if (!isset($this->connectionUsers[$connectionId])) {
            return null;
        }

        $userId = $this->connectionUsers[$connectionId];
        foreach (array_keys($this->connectionSubscriptions[$connectionId] ?? []) as $watchedUserId) {
            unset($this->subscriberConnections[$watchedUserId][$connectionId]);
            if (empty($this->subscriberConnections[$watchedUserId])) {
                unset($this->subscriberConnections[$watchedUserId]);
            }
        }

        unset(
            $this->connectionSubscriptions[$connectionId],
            $this->connectionUsers[$connectionId],
            $this->userConnections[$userId][$connectionId]
        );
        if (empty($this->userConnections[$userId])) {
            unset($this->userConnections[$userId]);
        }

        return [
            'user_id' => $userId,
            'is_last_connection' => !isset($this->userConnections[$userId]),
        ];
    }

    /**
     * @param iterable<mixed> $userIds
     * @return array<int,int> Newly subscribed user ids.
     */
    public function subscribe(string $connectionId, iterable $userIds): array
    {
        $ids = $this->normalizeMessageIds($userIds);
        $this->assertConnection($connectionId);

        $current = $this->connectionSubscriptions[$connectionId];
        $prospectiveCount = count($current);
        foreach ($ids as $userId) {
            if (!isset($current[$userId])) {
                $prospectiveCount++;
            }
        }
        if ($prospectiveCount > self::MAX_IDS_PER_CONNECTION) {
            throw new InvalidArgumentException('Presence bağlantı aboneliği sınırı aşıldı.');
        }

        $added = [];
        foreach ($ids as $userId) {
            if (isset($this->connectionSubscriptions[$connectionId][$userId])) {
                continue;
            }
            $this->connectionSubscriptions[$connectionId][$userId] = true;
            $this->subscriberConnections[$userId][$connectionId] = true;
            $added[] = $userId;
        }

        return $added;
    }

    /**
     * @param iterable<mixed> $userIds
     * @return array<int,int> Removed user ids.
     */
    public function unsubscribe(string $connectionId, iterable $userIds): array
    {
        $ids = $this->normalizeMessageIds($userIds);
        $this->assertConnection($connectionId);

        $removed = [];
        foreach ($ids as $userId) {
            if (!isset($this->connectionSubscriptions[$connectionId][$userId])) {
                continue;
            }
            unset(
                $this->connectionSubscriptions[$connectionId][$userId],
                $this->subscriberConnections[$userId][$connectionId]
            );
            if (empty($this->subscriberConnections[$userId])) {
                unset($this->subscriberConnections[$userId]);
            }
            $removed[] = $userId;
        }

        return $removed;
    }

    /** @return array<int,int> */
    public function subscriptions(string $connectionId): array
    {
        $ids = array_map('intval', array_keys($this->connectionSubscriptions[$connectionId] ?? []));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /** @return array<int,string> */
    public function subscriberConnectionIds(int $userId): array
    {
        $ids = array_map('strval', array_keys($this->subscriberConnections[$userId] ?? []));
        sort($ids, SORT_STRING);

        return $ids;
    }

    public function connectionUserId(string $connectionId): int
    {
        return (int) ($this->connectionUsers[$connectionId] ?? 0);
    }

    public function isUserOnline(int $userId): bool
    {
        return $userId > 0 && !empty($this->userConnections[$userId]);
    }

    /**
     * @param iterable<mixed> $userIds
     * @return array<int,bool>
     */
    public function onlineOverrides(iterable $userIds): array
    {
        $overrides = [];
        foreach ($userIds as $userId) {
            $id = (int) $userId;
            if ($id > 0) {
                $overrides[$id] = $this->isUserOnline($id);
            }
        }

        return $overrides;
    }

    /**
     * @param iterable<mixed> $userIds
     * @return array<int,int>
     */
    private function normalizeMessageIds(iterable $userIds): array
    {
        $ids = [];
        $inputCount = 0;
        foreach ($userIds as $userId) {
            $inputCount++;
            if ($inputCount > self::MAX_IDS_PER_MESSAGE) {
                throw new InvalidArgumentException('Presence mesajı kimlik sınırı aşıldı.');
            }
            if (!(is_int($userId) || (is_string($userId) && ctype_digit(trim($userId))))) {
                throw new InvalidArgumentException('Geçersiz presence kullanıcı kimliği.');
            }
            $id = (int) $userId;
            if ($id <= 0) {
                throw new InvalidArgumentException('Geçersiz presence kullanıcı kimliği.');
            }
            $ids[$id] = $id;
        }

        $ids = array_values($ids);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function assertConnection(string $connectionId): void
    {
        if (!isset($this->connectionUsers[$connectionId])) {
            throw new InvalidArgumentException('Presence bağlantısı kayıtlı değil.');
        }
    }
}
