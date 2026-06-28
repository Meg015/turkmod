<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Services;

use App\Core\Cache\TaggableCache;
use App\Core\Events\Event;
use App\Core\Events\Listener;
use Throwable;

final class LeaderboardCacheInvalidator implements Listener
{
    private const CACHE_TAG = 'leaderboard';

    public function __construct(private ?TaggableCache $cache = null)
    {
    }

    public function handle(Event $event): void
    {
        if ($event->name() !== 'topic.published') {
            return;
        }

        $this->invalidate();
    }

    public function invalidate(): bool
    {
        $cache = $this->cache;
        if (!$cache instanceof TaggableCache) {
            return false;
        }

        try {
            return $cache->invalidateTag(self::CACHE_TAG);
        } catch (Throwable $exception) {
            appLogException($exception, ['source' => 'LeaderboardCacheInvalidator']);

            return false;
        }
    }
}
