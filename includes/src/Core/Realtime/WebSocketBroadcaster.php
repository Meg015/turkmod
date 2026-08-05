<?php

declare(strict_types=1);

namespace App\Core\Realtime;

use Throwable;

final class WebSocketBroadcaster
{
    /**
     * @param array<int,int>|int $userIds
     * @param array<string,mixed> $payload
     */
    public static function publish(array|int $userIds, array $payload): bool
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($userIds) ? $userIds : [$userIds]
        ))));
        if ($userIds === []) {
            return false;
        }

        $body = json_encode([
            'user_id' => $userIds,
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false || strlen($body) > WebSocketConfig::broadcastMaxBytes()) {
            return false;
        }

        try {
            if (function_exists('curl_init')) {
                $handle = curl_init(WebSocketConfig::broadcastUrl());
                if ($handle === false) {
                    return false;
                }

                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
                curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                if (defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, 250);
                }
                if (defined('CURLOPT_TIMEOUT_MS')) {
                    curl_setopt($handle, CURLOPT_TIMEOUT_MS, 1000);
                } else {
                    curl_setopt($handle, CURLOPT_TIMEOUT, 1);
                }
                curl_exec($handle);
                $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);

                return $statusCode >= 200 && $statusCode < 300;
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 1,
                    'ignore_errors' => true,
                ],
            ]);
            $response = file_get_contents(WebSocketConfig::broadcastUrl(), false, $context);

            return $response !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
