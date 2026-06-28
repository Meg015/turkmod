<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use Closure;
use PDO;
use RuntimeException;

final class RobotsPage implements Handler
{
    /**
     * @param array<string,mixed>|null $settings
     */
    public function __construct(
        private ?array $settings = null,
        private ?string $canonicalBase = null,
        private ?Closure $settingsResolver = null,
        private ?Closure $robotsBuilder = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $settings = $this->settings ?? $this->resolveSettings();
        $body = $this->buildRobotsTxt($settings);
        $expiresAt = time() + 600;

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=600, stale-while-revalidate=86400',
            'Expires' => gmdate('D, d M Y H:i:s', $expiresAt) . ' GMT',
            'ETag' => '"' . hash('sha256', $body) . '"',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveSettings(): array
    {
        if ($this->settingsResolver instanceof Closure) {
            $settings = ($this->settingsResolver)();

            return is_array($settings) ? $settings : [];
        }

        $pdo = $GLOBALS['pdo'] ?? null;
        if (function_exists('getAdminSettings') && $pdo instanceof PDO) {
            return getAdminSettings($pdo);
        }

        return [];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function buildRobotsTxt(array $settings): string
    {
        if ($this->robotsBuilder instanceof Closure) {
            return (string) ($this->robotsBuilder)($settings, $this->canonicalBase);
        }

        if (!function_exists('buildRobotsTxt')) {
            throw new RuntimeException('buildRobotsTxt() is not available.');
        }

        return buildRobotsTxt($settings, $this->canonicalBase);
    }
}
