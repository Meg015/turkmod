<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0002_add_topic_report_snapshot_columns';
    }

    public function up(PDO $pdo): void
    {
        $schema = new App\Modules\Reports\Services\ReportsSchemaService();
        $schema->ensureTopicReports($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $this->dropColumnIfExists($pdo, 'topic_reports', 'reporter_type');
        $this->dropColumnIfExists($pdo, 'topic_reports', 'reporter_email');
        $this->dropColumnIfExists($pdo, 'topic_reports', 'reporter_name');
    }

    private function dropColumnIfExists(PDO $pdo, string $table, string $column): void
    {
        if (!$this->tableExists($pdo, $table) || !isset($this->columns($pdo, $table)[$column])) {
            return;
        }

        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
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
