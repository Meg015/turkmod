<?php

declare(strict_types=1);

namespace App\Core;

/** Canonical reader and request-local cache for the admin_settings table. */
final class AppSettings
{
    private static ?self $instance = null;

    private array $settings = [];

    private bool $loaded = false;

    private const APCU_KEY = 'admin_settings_v2';

    private const CACHE_TTL = 300;

    private function __construct(private ?\PDO $pdo = null)
    {
    }

    /**
     * Get or create the singleton instance.
     */
    public static function instance(?\PDO $pdo = null): self
    {
        $resolvedPdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
        if (self::$instance === null
            || ($resolvedPdo instanceof \PDO && self::$instance->pdo !== $resolvedPdo)) {
            self::$instance = new self($resolvedPdo instanceof \PDO ? $resolvedPdo : null);
        }
        return self::$instance;
    }

    /**
     * Reset the singleton (useful for testing or after settings change).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get a single setting value.
     */
    public function get(string $key, string $default = ''): string
    {
        $this->ensureLoaded();
        return (string) ($this->settings[$key] ?? $default);
    }

    /**
     * Get a boolean setting value.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get an integer setting value.
     */
    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, (string) $default);
    }

    /**
     * Get all settings as an array for procedural callers.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $this->ensureLoaded();
        return $this->settings;
    }

    /**
     * Get configured timezone.
     */
    public function timezone(): string
    {
        return $this->get('timezone', 'Europe/Istanbul');
    }

    /**
     * Get items per page setting.
     */
    public function itemsPerPage(int $default = 20): int
    {
        return max(1, $this->int('items_per_page', $default));
    }

    /**
     * Check if maintenance mode is enabled.
     */
    public function isMaintenanceMode(): bool
    {
        return $this->bool('maintenance_mode');
    }

    /**
     * Get maintenance mode message.
     */
    public function maintenanceMessage(): string
    {
        return $this->get('maintenance_message', 'Site bakım modundadır, lütfen daha sonra tekrar deneyin.');
    }

    /**
     * Check if dark mode is enabled.
     */
    public function darkMode(): string
    {
        return $this->get('dark_mode', 'auto');
    }

    /**
     * Get site name.
     */
    public function siteName(): string
    {
        return $this->get('site_name', 'İçerik Topic');
    }

    /**
     * Invalidate the loaded settings cache.
     */
    public function invalidate(): void
    {
        $this->loaded = false;
        $this->settings = [];
        if (function_exists('apcu_delete')) {
            apcu_delete(self::APCU_KEY);
        }
        $cacheFile = $this->cacheFile();
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $definitions = function_exists('adminSettingDefinitions') ? adminSettingDefinitions() : [];
        $settings = [];
        foreach ($definitions as $key => $definition) {
            $default = (string) ($definition['default'] ?? '');
            $settings[(string) $key] = function_exists('adminNormalizeSettingValue')
                ? adminNormalizeSettingValue((string) $key, $default, $definition)
                : $default;
        }

        if (!$this->pdo instanceof \PDO) {
            $this->settings = $settings;
            $this->loaded = true;
            return;
        }

        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch(self::APCU_KEY, $success);
            if ($success && is_array($cached)) {
                $this->settings = $this->completeSettings($settings, $cached, $definitions);
                $this->loaded = true;
                return;
            }
        }

        $cacheFile = $this->cacheFile();
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < self::CACHE_TTL) {
            try {
                $cached = require $cacheFile;
            } catch (\Throwable) {
                @unlink($cacheFile);
                if (function_exists('apcu_delete')) {
                    apcu_delete(self::APCU_KEY);
                }
                $cached = null;
            }
            if (is_array($cached) && $cached !== []) {
                $this->settings = $this->completeSettings($settings, $cached, $definitions);
                $this->loaded = true;
                if (function_exists('apcu_store')) {
                    apcu_store(self::APCU_KEY, $this->settings, self::CACHE_TTL);
                }
                return;
            }
        }

        $persistedKeys = [];
        try {
            $statement = $this->pdo->query('SELECT setting_key, setting_value FROM admin_settings');
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $key = (string) ($row['setting_key'] ?? '');
                $value = (string) ($row['setting_value'] ?? '');
                if ($key === '') {
                    continue;
                }
                if (array_key_exists($key, $definitions)) {
                    $persistedKeys[$key] = true;
                    $settings[$key] = function_exists('adminNormalizeSettingValue')
                        ? adminNormalizeSettingValue($key, $value, $definitions[$key])
                        : $value;
                }
            }
        } catch (\Throwable $exception) {
            error_log('admin_settings read failed: ' . $exception->getMessage());
        }

        $this->settings = $this->applyLegacyFallbacks($settings, $definitions, $persistedKeys);
        $this->loaded = true;

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cachePayload = "<?php\nreturn " . var_export($this->settings, true) . ";\n";
        $tmpFile = $cacheFile . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmpFile, $cachePayload, LOCK_EX) !== false) {
            if (!@rename($tmpFile, $cacheFile)) {
                @unlink($cacheFile);
                if (!@rename($tmpFile, $cacheFile)) {
                    @unlink($tmpFile);
                }
            }
        }
        if (function_exists('apcu_store')) {
            apcu_store(self::APCU_KEY, $this->settings, self::CACHE_TTL);
        }
    }

    /**
     * @param array<string,string> $defaults
     * @param array<string,mixed> $cached
     * @param array<string,array<string,mixed>> $definitions
     * @return array<string,string>
     */
    private function completeSettings(array $defaults, array $cached, array $definitions): array
    {
        $knownCached = [];
        foreach ($cached as $key => $value) {
            if (is_string($key) && array_key_exists($key, $definitions)) {
                $knownCached[$key] = (string) $value;
            }
        }

        return $this->applyLegacyFallbacks(
            array_replace($defaults, $knownCached),
            $definitions,
            array_fill_keys(array_keys($knownCached), true)
        );
    }

    /**
     * @param array<string,string> $settings
     * @param array<string,array<string,mixed>> $definitions
     * @param array<string,bool> $persistedKeys
     * @return array<string,string>
     */
    private function applyLegacyFallbacks(array $settings, array $definitions, array $persistedKeys): array
    {
        foreach ($definitions as $key => $definition) {
            $legacyKey = trim((string) ($definition['legacy_key'] ?? ''));
            if ($legacyKey === '' || isset($persistedKeys[$key]) || !array_key_exists($legacyKey, $settings)) {
                continue;
            }

            $legacyValue = (string) $settings[$legacyKey];
            $settings[$key] = function_exists('adminNormalizeSettingValue')
                ? adminNormalizeSettingValue($key, $legacyValue, $definition)
                : $legacyValue;
        }

        return $settings;
    }

    private function cacheFile(): string
    {
        return dirname(__DIR__, 3) . '/storage/cache/admin_settings_compiled_v2.php';
    }
}
