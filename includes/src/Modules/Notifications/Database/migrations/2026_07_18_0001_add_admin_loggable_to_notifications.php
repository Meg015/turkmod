<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_18_0001_add_admin_loggable_to_notifications';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notifications')) {
            return;
        }

        $columns = $this->columns($pdo, 'notifications');
        if (!isset($columns['is_admin_loggable'])) {
            $afterColumn = isset($columns['delivery_channels']) ? 'delivery_channels' : (isset($columns['link']) ? 'link' : 'type');
            $pdo->exec("ALTER TABLE `notifications` ADD COLUMN `is_admin_loggable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `{$afterColumn}`");
        }

        if (!$this->indexExists($pdo, 'notifications', 'notifications_admin_loggable_index')) {
            $pdo->exec('ALTER TABLE `notifications` ADD INDEX `notifications_admin_loggable_index` (`is_admin_loggable`, `created_at`)');
        }

        $conditions = ["`type` = 'system'"];
        if (isset($columns['event_key'])) {
            $conditions[] = "`event_key` IN (" . implode(', ', array_map([$pdo, 'quote'], $this->adminLoggableEventKeys())) . ')';
        }
        if (isset($columns['entity_type'])) {
            $conditions[] = "`entity_type` = 'ban_appeal'";
        }

        $pdo->exec('UPDATE `notifications` SET `is_admin_loggable` = 1 WHERE ' . implode(' OR ', $conditions));
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notifications')) {
            return;
        }

        if ($this->indexExists($pdo, 'notifications', 'notifications_admin_loggable_index')) {
            $pdo->exec('ALTER TABLE `notifications` DROP INDEX `notifications_admin_loggable_index`');
        }

        if (isset($this->columns($pdo, 'notifications')['is_admin_loggable'])) {
            $pdo->exec('ALTER TABLE `notifications` DROP COLUMN `is_admin_loggable`');
        }
    }

    /** @return list<string> */
    private function adminLoggableEventKeys(): array
    {
        return [
            'topic_approved',
            'topic_rejected',
            'topic_revision_requested',
            'comment_approved',
            'comment_edited_by_staff',
            'user_banned',
            'user_unbanned',
            'user_restricted',
            'user_restriction_removed',
            'user_group_changed',
            'ban_appeal_created',
            'ban_appeal_message_added',
            'ban_appeal_updated',
            'topic_report_status_updated',
            'user_report_status_updated',
        ];
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ');
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<string,bool> */
    private function columns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $stmt->execute();

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $columns[(string) $row['Field']] = true;
        }

        return $columns;
    }

    private function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ');
        $stmt->execute([$table, $index]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
