<?php

declare(strict_types=1);

namespace App\Engine\Themes;

final class ThemeMetadata
{
    /**
     * @return array<int, string>
     */
    public static function authFocusPageKeys(\ThemeManager $themeManager, ?string $themeId = null): array
    {
        $themeId = $themeId !== null ? trim($themeId) : $themeManager->activeThemeId();
        if ($themeId === '') {
            return self::defaultAuthFocusPageKeys();
        }

        try {
            $manifest = $themeManager->loadManifest($themeId);
        } catch (\Throwable) {
            return self::defaultAuthFocusPageKeys();
        }

        $focusPages = $manifest['shell']['auth_focus_pages'] ?? [];
        if (!is_array($focusPages)) {
            return self::defaultAuthFocusPageKeys();
        }

        $keys = [];
        foreach ($focusPages as $pageKey) {
            $pageKey = trim((string) $pageKey);
            if ($pageKey !== '') {
                $keys[] = $pageKey;
            }
        }

        return $keys !== [] ? array_values(array_unique($keys)) : self::defaultAuthFocusPageKeys();
    }

    /**
     * @return array<int, string>
     */
    private static function defaultAuthFocusPageKeys(): array
    {
        return ['login', 'register', 'forgot_password', 'reset_password'];
    }
}
