<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const LEGACY_KEYS = [
        'banned_words',
        'comment_word_filter',
        'comment_auto_ban_words',
        'comment_spam_action',
        'comment_spam_reject_message',
        'comment_spam_pending_message',
        'comment_spam_punctuation_only_enabled',
        'comment_spam_min_meaningful_chars',
        'comment_spam_meaningless_enabled',
        'comment_spam_meaningless_phrases',
        'comment_spam_gibberish_enabled',
        'comment_spam_gibberish_max_length',
        'comment_spam_gibberish_score_threshold',
        'comment_spam_repeated_chars_enabled',
        'comment_spam_repeated_chars_limit',
        'comment_spam_duplicate_window_minutes',
        'comment_spam_max_links',
        'comment_spam_caps_enabled',
        'comment_spam_caps_min_letters',
        'comment_spam_caps_percent',
    ];

    public function name(): string
    {
        return '2026_07_18_0016_remove_legacy_comment_spam_settings';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'admin_settings')) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(self::LEGACY_KEYS), '?'));
        $stmt = $pdo->prepare("DELETE FROM admin_settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute(self::LEGACY_KEYS);

        if (function_exists('invalidateAdminSettingsCache')) {
            invalidateAdminSettingsCache();
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Removed legacy comment spam settings are not restored automatically.');
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
};
