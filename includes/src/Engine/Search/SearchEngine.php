<?php

declare(strict_types=1);

namespace App\Engine\Search;

interface SearchEngine
{
    /**
     * @param array<string,mixed> $document
     */
    public function index(string $type, string|int $id, array $document = []): void;

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function query(string $term, int $limit = 20, array $filters = []): array;

    public function delete(string $type, string|int $id): void;
}
