<?php

declare(strict_types=1);

namespace App\Core\Bootstrap;

use App\Core\Cache\Cache;
use App\Core\Cache\DatabaseCache;
use App\Core\Cache\FileCache;
use App\Core\Cache\RedisCache;
use App\Core\Cache\TaggableCache;
use App\Core\Config\Config;
use App\Core\Container\Container;
use App\Core\Database;
use App\Core\Events\Dispatcher;
use App\Core\Modules\ModuleLoader;
use App\Core\Queue\Queue;
use App\Core\Queue\SyncQueue;
use App\Core\Security\DatabaseRateLimiter;
use App\Core\Security\FileRateLimiter;
use App\Core\Security\RateLimiter;
use App\Core\Support\Logger as AppLogger;
use App\Core\Security\SecurityHeadersMiddleware;
use App\Engine\Auth\DisabledTwoFactor;
use App\Engine\Auth\TwoFactor;
use App\Engine\Search\DisabledSearchEngine;
use App\Engine\Search\SearchEngine;
use RuntimeException;
use Throwable;

final class Boot
{
    private static ?Container $container = null;
    private static ?string $bootRoot = null;

    public static function container(?string $projectRoot = null): Container
    {
        $projectRoot = self::projectRoot($projectRoot);

        if (self::$container instanceof Container && self::$bootRoot === $projectRoot) {
            return self::$container;
        }

        self::$container = self::make($projectRoot);
        self::$bootRoot = $projectRoot;

        return self::$container;
    }

    public static function make(?string $projectRoot = null): Container
    {
        $projectRoot = self::projectRoot($projectRoot);
        self::requireLegacyDatabase($projectRoot);

        $container = new Container();
        $container->instance(Container::class, $container);
        self::registerBindings($container, $projectRoot);

        return $container;
    }

    public static function reset(): void
    {
        self::$container = null;
        self::$bootRoot = null;
    }

    private static function registerBindings(Container $container, string $projectRoot): void
    {
        $storageRoot = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $cacheRoot = $storageRoot . DIRECTORY_SEPARATOR . 'cache';
        $logRoot = $storageRoot . DIRECTORY_SEPARATOR . 'logs';
        $env = Database::getEnvConfig();
        $cacheBackend = self::normalizeBackend((string) ($env['CORE_CACHE_BACKEND'] ?? 'file'));
        $rateLimitBackend = self::normalizeBackend((string) ($env['CORE_RATE_LIMIT_BACKEND'] ?? 'file'));
        $cacheTable = trim((string) ($env['CORE_CACHE_TABLE'] ?? 'core_cache'));
        $rateLimitTable = trim((string) ($env['CORE_RATE_LIMIT_TABLE'] ?? 'request_rate_limits'));
        $rateLimitScope = trim((string) ($env['CORE_RATE_LIMIT_SCOPE'] ?? 'default'));

        self::ensureDirectory($cacheRoot);
        self::ensureDirectory($logRoot);
        self::requireRateLimiterContract();

        $container->singleton(Database::class, static fn (): Database => new Database());
        $container->singleton(Config::class, static fn (): Config => Config::load([
            'paths' => [
                'root' => $projectRoot,
                'storage' => $storageRoot,
                'cache' => $cacheRoot,
                'logs' => $logRoot,
            ],
        ]));
        $container->singleton(AppLogger::class, static fn (): AppLogger => new AppLogger(
            $logRoot . DIRECTORY_SEPARATOR . 'app.log',
            'app',
        ));
        $container->singleton(Cache::class, static fn (): Cache => self::makeCacheStore(
            $cacheBackend,
            $cacheRoot,
            $cacheTable,
        ));
        $container->bind(TaggableCache::class, Cache::class);
        $container->singleton(Queue::class, static fn (): SyncQueue => new SyncQueue());
        $container->singleton(RateLimiter::class, static fn (): RateLimiter => self::makeRateLimiter(
            $rateLimitBackend,
            $cacheRoot,
            $rateLimitScope,
            $rateLimitTable,
        ));
        $container->singleton(TwoFactor::class, static fn (): DisabledTwoFactor => new DisabledTwoFactor());
        $container->singleton(SearchEngine::class, static fn (): DisabledSearchEngine => new DisabledSearchEngine());
        $container->singleton(Dispatcher::class, static fn (Container $container): Dispatcher => self::makeEventDispatcher(
            $container,
            $projectRoot,
        ));
        $container->singleton(SecurityHeadersMiddleware::class, static fn (): SecurityHeadersMiddleware => new SecurityHeadersMiddleware($env));
    }

    private static function normalizeBackend(string $backend): string
    {
        $backend = strtolower(trim($backend));
        if (in_array($backend, ['db', 'database'], true)) {
            return 'database';
        }
        if ($backend === 'redis') {
            return 'redis';
        }

        return 'file';
    }

    private static function makeCacheStore(string $backend, string $cacheRoot, string $cacheTable): Cache
    {
        if ($backend === 'database') {
            try {
                $pdo = Database::connection();
                if ($pdo instanceof \PDO) {
                    return new DatabaseCache($pdo, $cacheTable, 'core-cache');
                }
            } catch (Throwable $exception) {
                error_log('Database cache backend failed, falling back to file cache: ' . $exception->getMessage());
            }
        }

        if ($backend === 'redis') {
            try {
                $env = Database::getEnvConfig();
                return new RedisCache(
                    host: (string) ($env['REDIS_HOST'] ?? '127.0.0.1'),
                    port: (int) ($env['REDIS_PORT'] ?? 6379),
                    password: (string) ($env['REDIS_PASSWORD'] ?? ''),
                    prefix: (string) ($env['REDIS_PREFIX'] ?? 'core-cache'),
                );
            } catch (Throwable $exception) {
                error_log('Redis cache backend failed, falling back to file cache: ' . $exception->getMessage());
            }
        }

        return new FileCache(
            $cacheRoot . DIRECTORY_SEPARATOR . 'core',
            'core-cache',
        );
    }

    private static function makeRateLimiter(
        string $backend,
        string $cacheRoot,
        string $scope,
        string $table,
    ): RateLimiter {
        if ($backend === 'database') {
            try {
                $pdo = Database::connection();
                if ($pdo instanceof \PDO) {
                    return new DatabaseRateLimiter($pdo, $scope, $table);
                }
            } catch (Throwable $exception) {
                error_log('Database rate-limit backend failed, falling back to file limiter: ' . $exception->getMessage());
            }
        }

        return new FileRateLimiter(
            $cacheRoot . DIRECTORY_SEPARATOR . 'rate-limits',
        );
    }

    private static function projectRoot(?string $projectRoot): string
    {
        $projectRoot = trim((string) $projectRoot);
        if ($projectRoot === '') {
            $projectRoot = dirname(__DIR__, 4);
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $projectRoot), DIRECTORY_SEPARATOR);
    }

    private static function requireLegacyDatabase(string $projectRoot): void
    {
        $candidates = [
            $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Database.php',
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Database.php',
        ];

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                require_once $candidate;

                return;
            }
        }

        throw new RuntimeException('Legacy App\\Core\\Database bootstrap file could not be found.');
    }

    private static function requireRateLimiterContract(): void
    {
        $rateLimiterFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Security' . DIRECTORY_SEPARATOR . 'RateLimiter.php';
        if (is_file($rateLimiterFile)) {
            require_once $rateLimiterFile;
        }
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Directory could not be created: ' . $directory);
        }
    }

    private static function makeEventDispatcher(Container $container, string $projectRoot): Dispatcher
    {
        $dispatcher = new Dispatcher($container);
        self::registerModuleEventListeners($dispatcher, $container, $projectRoot);

        return $dispatcher;
    }

    private static function registerModuleEventListeners(Dispatcher $dispatcher, Container $container, string $projectRoot): void
    {
        $modulesRoot = $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Modules';
        if (!is_dir($modulesRoot)) {
            return;
        }

        $loader = new ModuleLoader($container);
        foreach ($loader->discover($modulesRoot) as $metadata) {
            foreach ($loader->eventListeners($metadata) as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $dispatcher->listen($eventName, $listener);
                }
            }
        }
    }
}
