<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_20_0001_add_email_template_copy';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notification_templates')) {
            return;
        }

        $columns = $this->columns($pdo, 'notification_templates');
        $columnSql = [
            'email_subject_template' => "ALTER TABLE `notification_templates` ADD COLUMN `email_subject_template` VARCHAR(255) NULL AFTER `email_enabled`",
            'email_body_template' => "ALTER TABLE `notification_templates` ADD COLUMN `email_body_template` TEXT NULL AFTER `email_subject_template`",
            'email_link_template' => "ALTER TABLE `notification_templates` ADD COLUMN `email_link_template` VARCHAR(1024) NULL AFTER `email_body_template`",
            'email_preview_template' => "ALTER TABLE `notification_templates` ADD COLUMN `email_preview_template` VARCHAR(255) NULL AFTER `email_link_template`",
        ];

        foreach ($columnSql as $column => $sql) {
            if (!isset($columns[$column])) {
                $pdo->exec($sql);
            }
        }

        $pdo->exec("
            UPDATE `notification_templates`
            SET
                `email_subject_template` = CASE
                    WHEN `email_subject_template` IS NULL OR TRIM(`email_subject_template`) = '' THEN `title_template`
                    ELSE `email_subject_template`
                END,
                `email_body_template` = CASE
                    WHEN `email_body_template` IS NULL OR TRIM(`email_body_template`) = '' THEN `message_template`
                    ELSE `email_body_template`
                END,
                `email_link_template` = CASE
                    WHEN `email_link_template` IS NULL OR TRIM(`email_link_template`) = '' THEN `link_template`
                    ELSE `email_link_template`
                END,
                `email_preview_template` = CASE
                    WHEN `email_preview_template` IS NULL OR TRIM(`email_preview_template`) = '' THEN LEFT(`message_template`, 255)
                    ELSE `email_preview_template`
                END
        ");
    }

    public function down(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notification_templates')) {
            return;
        }

        foreach (['email_preview_template', 'email_link_template', 'email_body_template', 'email_subject_template'] as $column) {
            if (isset($this->columns($pdo, 'notification_templates')[$column])) {
                $pdo->exec('ALTER TABLE `notification_templates` DROP COLUMN `' . $column . '`');
            }
        }
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
};
