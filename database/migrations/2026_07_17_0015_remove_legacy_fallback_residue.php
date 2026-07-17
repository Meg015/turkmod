<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const LEGACY_ADMIN_SETTING_KEYS = [
        'default_og_image',
        'bot_translation_fallback_original',
    ];

    private const LEGACY_EVENT_CONFIG_KEYS = [
        'wheel_daily_limit',
        'wheel_hourly_limit',
    ];

    public function name(): string
    {
        return '2026_07_17_0015_remove_legacy_fallback_residue';
    }

    public function up(PDO $pdo): void
    {
        $this->deleteKeys($pdo, 'admin_settings', 'setting_key', self::LEGACY_ADMIN_SETTING_KEYS);
        $this->deleteKeys($pdo, 'events_config', 'config_key', self::LEGACY_EVENT_CONFIG_KEYS);

        if ($this->columnExists($pdo, 'user_restrictions', 'created_by')) {
            $pdo->exec('ALTER TABLE user_restrictions DROP COLUMN created_by');
        }

        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Removed legacy fallback residue is not restored automatically.');
    }

    /**
     * @param list<string> $keys
     */
    private function deleteKeys(PDO $pdo, string $table, string $column, array $keys): void
    {
        if (!$this->tableExists($pdo, $table) || $keys === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
        $stmt->execute($keys);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        if (!$this->tableExists($pdo, $table)) {
            return false;
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$table})");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((string) ($row['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $pdo->quote($column));
        return (bool) $stmt->fetchColumn();
    }
};
