<?php

declare(strict_types=1);

namespace App\Core\Cache;

interface TaggableCache extends Cache
{
    public function invalidateTag(string $tag): bool;

    /**
     * @param array<int,string> $tags
     */
    public function invalidateTags(array $tags): bool;
}
