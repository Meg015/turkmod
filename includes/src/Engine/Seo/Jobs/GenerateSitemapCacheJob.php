<?php

declare(strict_types=1);

namespace App\Engine\Seo\Jobs;

use App\Core\Queue\Job;
use Closure;
use RuntimeException;

final class GenerateSitemapCacheJob implements Job
{
    public function __construct(
        private string $type,
        private string $cacheFile,
        private array $settings = [],
        private ?Closure $generator = null,
        private ?Closure $writer = null,
        private ?string $lockFile = null,
    ) {
    }

    public function handle(): void
    {
        try {
            $output = $this->generate();
            $this->write($output);
        } finally {
            if ($this->lockFile !== null && is_file($this->lockFile)) {
                unlink($this->lockFile);
            }
        }
    }

    private function generate(): string
    {
        if ($this->generator instanceof Closure) {
            return (string) ($this->generator)($this->type, $this->settings);
        }

        if (!function_exists('seoGenerateSitemapOutput')) {
            throw new RuntimeException('Sitemap generator helper is not available.');
        }

        return seoGenerateSitemapOutput($this->type);
    }

    private function write(string $output): void
    {
        if ($this->writer instanceof Closure) {
            ($this->writer)($this->cacheFile, $output, $this->settings);

            return;
        }

        if (function_exists('seoWriteSitemapCache')) {
            seoWriteSitemapCache($this->cacheFile, $output);

            return;
        }

        $cacheDirectory = dirname($this->cacheFile);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0755, true);
        }

        file_put_contents($this->cacheFile, $output);
    }
}
