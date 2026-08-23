<?php

declare(strict_types=1);

namespace App\Core\Realtime;

use App\Core\DatabaseConnection;

final class WebSocketConfig
{
    public static function publicEndpoint(): string
    {
        $endpoint = trim((string) (self::env()['PUBLIC_WEBSOCKET_URL'] ?? ''));
        if ($endpoint === '') {
            $environment = strtolower(trim((string) (self::env()['APP_ENV'] ?? 'development')));
            $endpoint = $environment === 'production' ? '/ws' : 'wss://localhost:' . self::websocketPort() . '/';
        }
        if (preg_match('~^(?:wss?://[^\s]+|/(?!/)[^\s]*)$~i', $endpoint) !== 1) {
            return '';
        }

        return $endpoint;
    }

    public static function websocketBindHost(): string
    {
        return self::loopbackHost((string) (self::env()['WEBSOCKET_BIND_HOST'] ?? '127.0.0.1'));
    }

    public static function websocketPort(): int
    {
        return self::port(self::env()['WEBSOCKET_PORT'] ?? null, 8080);
    }

    public static function broadcastBindHost(): string
    {
        return self::loopbackHost((string) (self::env()['WEBSOCKET_BROADCAST_BIND_HOST'] ?? '127.0.0.1'));
    }

    public static function broadcastPort(): int
    {
        return self::port(self::env()['WEBSOCKET_BROADCAST_PORT'] ?? null, 8081);
    }

    public static function broadcastUrl(): string
    {
        return 'http://' . self::broadcastBindHost() . ':' . self::broadcastPort() . '/broadcast';
    }

    public static function broadcastMaxBytes(): int
    {
        $value = (int) (self::env()['WEBSOCKET_BROADCAST_MAX_BYTES'] ?? 65536);

        return max(1024, min(1048576, $value));
    }

    public static function broadcastTimeoutSeconds(): float
    {
        $value = (float) (self::env()['WEBSOCKET_BROADCAST_TIMEOUT_SECONDS'] ?? 3);

        return max(1, min(30, $value));
    }

    /** @return array<string,string> */
    private static function env(): array
    {
        return DatabaseConnection::getEnvConfig();
    }

    private static function loopbackHost(string $value): string
    {
        return trim($value) === '::1' ? '::1' : '127.0.0.1';
    }

    private static function port(mixed $value, int $default): int
    {
        $port = (int) $value;

        return $port >= 1 && $port <= 65535 ? $port : $default;
    }
}
