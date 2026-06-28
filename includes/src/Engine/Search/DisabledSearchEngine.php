<?php

declare(strict_types=1);

namespace App\Engine\Search;

use RuntimeException;

final class DisabledSearchEngine implements SearchEngine
{
    /**
     * Transitional placeholder until the real search backend lands in Phase 8.
     */
    public function index(string $type, string|int $id, array $document = []): void
    {
        throw new RuntimeException('Search engine is not implemented yet.');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function query(string $term, int $limit = 20, array $filters = []): array
    {
        throw new RuntimeException('Search engine is not implemented yet.');
    }

    public function delete(string $type, string|int $id): void
    {
        throw new RuntimeException('Search engine is not implemented yet.');
    }
}
