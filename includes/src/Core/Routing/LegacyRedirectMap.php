<?php

declare(strict_types=1);

namespace App\Core\Routing;

final class LegacyRedirectMap
{
    /**
     * @var array<string,string>
     */
    private array $map = [
        'index.php' => '',
        'login.php' => 'giris',
        'register.php' => 'kayit',
        'logout.php' => 'cikis',
        'forgot-password.php' => 'sifremi-unuttum',
        'reset-password.php' => 'sifre-sifirla',
        'profile.php' => 'profil',
        'public-profile.php' => 'profil',
        'notifications.php' => 'bildirimler',
        'messages.php' => 'mesajlar',
        'leaderboard.php' => 'liderlik',
        'ban-appeals.php' => 'ban-itiraz',
        'upload-topic.php' => 'konu-yukle',
        'edit-topic.php' => 'konu-duzenle',
        'download.php' => 'indir',
        'sitemap.php' => 'sitemap.xml',
        'topic-sitemap.php' => 'topic-sitemap.xml',
        'profile-sitemap.php' => 'profile-sitemap.xml',
        'image-sitemap.php' => 'image-sitemap.xml',
        'robots.php' => 'robots.txt',
        // Eski snake_case API dosya adları kebab-case'e taşındı.
        'api/track_view.php' => 'api/track-view.php',
        'api/scraper_image.php' => 'api/scraper-image.php',
        'cron/cleanup_deleted.php' => 'cron/cleanup-deleted.php',
    ];

    /**
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->map;
    }

    public function targetFor(string $legacyPath): ?string
    {
        $normalized = $this->normalizeLegacyPath($legacyPath);
        if ($normalized === '') {
            return null;
        }

        return $this->map[$normalized] ?? null;
    }

    private function normalizeLegacyPath(string $legacyPath): string
    {
        $path = (string) parse_url(trim($legacyPath), PHP_URL_PATH);
        $path = trim(str_replace('\\', '/', $path), '/');

        $baseUri = trim((string) ($GLOBALS['baseUri'] ?? ''), '/');
        if ($baseUri !== '' && str_starts_with($path, $baseUri . '/')) {
            $path = substr($path, strlen($baseUri) + 1);
        }

        return trim($path, '/');
    }
}
