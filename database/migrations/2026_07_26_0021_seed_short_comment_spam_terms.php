<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const SETTING_KEY = 'comment_spam_exact_terms';
    private const SEED_TERMS = ['vv'];
    private const REMOVED_TERMS = ['sa', 'as'];

    public function name(): string
    {
        return '2026_07_26_0021_seed_short_comment_spam_terms';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'admin_settings')) {
            return;
        }

        $select = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1');
        $select->execute([self::SETTING_KEY]);
        $storedValue = $select->fetchColumn();
        $settingExists = $storedValue !== false;

        $terms = [];
        $seenSeedTerms = [];
        foreach ($this->parseTerms($settingExists ? (string) $storedValue : '') as $term) {
            $normalized = $this->normalizeTerm($term);
            if (in_array($normalized, self::REMOVED_TERMS, true)) {
                continue;
            }
            if (in_array($normalized, self::SEED_TERMS, true)) {
                if (isset($seenSeedTerms[$normalized])) {
                    continue;
                }
                $seenSeedTerms[$normalized] = true;
            }
            $terms[] = $term;
        }
        $knownTerms = [];
        foreach ($terms as $term) {
            $normalized = $this->normalizeTerm($term);
            if ($normalized !== '') {
                $knownTerms[$normalized] = true;
            }
        }

        foreach (self::SEED_TERMS as $seedTerm) {
            $normalized = $this->normalizeTerm($seedTerm);
            if (!isset($knownTerms[$normalized])) {
                $terms[] = $seedTerm;
                $knownTerms[$normalized] = true;
            }
        }

        $newValue = implode("\n", $terms);
        if ($settingExists) {
            $update = $pdo->prepare(
                'UPDATE admin_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?'
            );
            $update->execute([$newValue, self::SETTING_KEY]);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
                 VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $insert->execute([self::SETTING_KEY, $newValue]);
        }

        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Seeded short comment spam terms are not removed automatically.');
    }

    /**
     * @return list<string>
     */
    private function parseTerms(string $value): array
    {
        $parts = preg_split('/[\r\n,;]+/u', $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $term): bool => $term !== ''));
    }

    private function normalizeTerm(string $term): string
    {
        $term = html_entity_decode(trim($term), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $term = mb_strtolower($term, 'UTF-8');
        $term = preg_replace('/^[\p{Z}\p{P}\p{S}]+|[\p{Z}\p{P}\p{S}]+$/u', '', $term) ?? $term;

        return preg_replace('/\s+/u', ' ', trim($term)) ?? trim($term);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return (bool) ($stmt && $stmt->fetchColumn());
    }
};
