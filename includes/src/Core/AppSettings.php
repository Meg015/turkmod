<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized application settings service.
 *
 * Wraps the legacy getAdminSettings() function behind an OOP interface and
 * integrates with the Container for dependency injection. This is the first
 * step toward migrating global state ($pdo, $baseUri, $GLOBALS) into the
 * container-based architecture.
 *
 * Usage:
 *   $settings = AppSettings::instance();
 *   $value = $settings->get('items_per_page', '20');
 *   $timezone = $settings->timezone();
 */
final class AppSettings
{
    private static ?self $instance = null;

    private array $settings = [];

    private bool $loaded = false;

    private function __construct(private ?\PDO $pdo = null)
    {
    }

    /**
     * Get or create the singleton instance.
     */
    public static function instance(?\PDO $pdo = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($pdo ?? ($GLOBALS['pdo'] ?? null));
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
     * Get all settings as an array (compatibility with legacy getAdminSettings).
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
        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    /**
     * Load settings from the legacy getAdminSettings function.
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if (function_exists('getAdminSettings')) {
            $this->settings = getAdminSettings($this->pdo);
        }

        $this->loaded = true;
    }
}
