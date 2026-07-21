<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Notifications\Services\NotificationTemplateService;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_20_0002_refresh_default_notification_copy';
    }

    public function up(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'notification_templates')) {
            return;
        }

        $columns = $this->columns($pdo, 'notification_templates');
        $templates = (new NotificationTemplateService())->defaults();
        foreach ($templates as $template) {
            $data = [
                'name' => $template['name'],
                'description' => $template['description'],
                'type' => $template['type'],
                'title_template' => $template['title_template'],
                'message_template' => $template['message_template'],
                'link_template' => $template['link_template'],
                'variables_json' => json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
                'sample_payload' => json_encode($template['sample_payload'], JSON_UNESCAPED_UNICODE),
            ];

            foreach (['email_subject_template', 'email_body_template', 'email_link_template', 'email_preview_template'] as $column) {
                if (isset($columns[$column])) {
                    $data[$column] = $template[$column];
                }
            }

            $assignments = implode(', ', array_map(
                static fn (string $column): string => '`' . str_replace('`', '``', $column) . '` = ?',
                array_keys($data)
            ));
            $stmt = $pdo->prepare(
                'UPDATE `notification_templates` SET ' . $assignments . ', `updated_at` = NOW() WHERE `template_key` = ?'
            );
            $values = array_values($data);
            $values[] = $template['template_key'];
            $stmt->execute($values);
        }
    }

    public function down(PDO $pdo): void
    {
        // Content refresh only; no safe rollback for administrator-edited copy.
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
