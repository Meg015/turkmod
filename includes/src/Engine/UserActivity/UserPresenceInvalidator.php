<?php

declare(strict_types=1);

namespace App\Engine\UserActivity;

use App\Core\Bootstrap\Boot;
use App\Core\Cache\TaggableCache;
use App\Core\Realtime\WebSocketBroadcaster;
use PDO;
use Throwable;

final class UserPresenceInvalidator
{
    public static function invalidateForDatabase(PDO $pdo, int $userId): void
    {
        try {
            if (strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
                return;
            }
        } catch (Throwable) {
            // Continue with invalidation when the driver cannot be inspected.
        }

        self::invalidate($userId);
    }

    public static function invalidate(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $cache = Boot::container()->get(TaggableCache::class);
            if ($cache instanceof TaggableCache) {
                $cache->invalidateTag('user-presence:' . $userId);
            }
        } catch (Throwable $error) {
            self::log($error, $userId, 'cache');
        }

        try {
            if (!WebSocketBroadcaster::invalidatePresence($userId)) {
                error_log('Presence invalidation could not reach WebSocket server for user ' . $userId);
            }
        } catch (Throwable $error) {
            self::log($error, $userId, 'websocket');
        }
    }

    private static function log(Throwable $error, int $userId, string $channel): void
    {
        if (function_exists('appLogException')) {
            appLogException($error, [
                'source' => 'User presence invalidation',
                'channel' => $channel,
                'user_id' => $userId,
            ]);
            return;
        }

        error_log('Presence invalidation failed (' . $channel . '): ' . $error->getMessage());
    }
}
