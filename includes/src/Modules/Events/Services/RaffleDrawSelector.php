<?php

declare(strict_types=1);

namespace App\Modules\Events\Services;

final class RaffleDrawSelector
{
    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    public function selectWinners(array $entries, int $winnerCount, ?callable $randomInt = null): array
    {
        $unique = [];
        foreach ($entries as $entry) {
            $userId = (int) ($entry['user_id'] ?? 0);
            if ($userId > 0 && !isset($unique[$userId])) {
                $entry['user_id'] = $userId;
                $unique[$userId] = $entry;
            }
        }

        $pool = array_values($unique);
        $winnerCount = min(max(0, $winnerCount), count($pool));
        $randomInt = $randomInt ?: static fn (int $min, int $max): int => random_int($min, $max);
        $winners = [];

        while (count($winners) < $winnerCount && $pool !== []) {
            $index = (int) $randomInt(0, count($pool) - 1);
            $winners[] = $pool[$index];
            array_splice($pool, $index, 1);
        }

        return $winners;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>|null
     */
    public function pickPrizePoolItem(array $items, ?callable $randomInt = null): ?array
    {
        $eligible = array_values(array_filter($items, static function (array $item): bool {
            return (int) ($item['is_active'] ?? 1) === 1
                && (float) ($item['weight'] ?? 0) > 0
                && (int) ($item['remaining_quantity'] ?? 0) > 0;
        }));

        if ($eligible === []) {
            return null;
        }

        $scale = 1;
        foreach ($eligible as $item) {
            $raw = (string) ($item['weight'] ?? '0');
            if (str_contains($raw, '.') && (float) $raw !== floor((float) $raw)) {
                $scale = 10000;
                break;
            }
        }

        $weights = [];
        $total = 0;
        foreach ($eligible as $index => $item) {
            $weight = max(0, (int) round(((float) ($item['weight'] ?? 0)) * $scale));
            if ($weight <= 0) {
                continue;
            }

            $weights[$index] = $weight;
            $total += $weight;
        }

        if ($total <= 0) {
            return null;
        }

        $randomInt = $randomInt ?: static fn (int $min, int $max): int => random_int($min, $max);
        $cursor = (int) $randomInt(1, $total);

        foreach ($weights as $index => $weight) {
            $cursor -= $weight;
            if ($cursor <= 0) {
                return $eligible[$index];
            }
        }

        return end($eligible) ?: null;
    }
}
