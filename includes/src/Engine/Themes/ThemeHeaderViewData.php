<?php

declare(strict_types=1);

namespace App\Engine\Themes;

final class ThemeHeaderViewData
{
    /**
     * @return array<string, mixed>
     */
    public static function notificationMenu(string $baseUri, bool $isLoggedIn): array
    {
        $baseUrl = rtrim($baseUri, '/');
        $notificationsUrl = \function_exists('routePublicStaticUrl')
            ? (string) \routePublicStaticUrl('notifications')
            : ($baseUrl . '/notifications.php');

        return [
            'notifications_enabled' => $isLoggedIn,
            'notifications_url' => $notificationsUrl,
            'notifications_api_url' => $baseUrl . '/api/notifications.php',
            'notifications_read_api_url' => $baseUrl . '/api/notifications-read.php',
            'notifications_fallback_url' => $notificationsUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function messageMenu(string $baseUri, bool $isLoggedIn): array
    {
        $baseUrl = rtrim($baseUri, '/');
        $messagesUrl = \function_exists('routePublicStaticUrl')
            ? (string) \routePublicStaticUrl('messages')
            : ($baseUrl . '/mesajlar');

        return [
            'messages_enabled' => $isLoggedIn,
            'messages_url' => $messagesUrl,
            'messages_api_url' => $baseUrl . '/api/messages.php',
            'messages_fallback_url' => $messagesUrl,
        ];
    }
}
