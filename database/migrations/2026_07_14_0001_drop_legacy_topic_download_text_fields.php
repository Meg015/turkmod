<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_14_0001_drop_legacy_topic_download_text_fields';
    }

    public function up(PDO $pdo): void
    {
        $this->dropColumnIfExists($pdo, 'topics', 'topic_download_links');
        $this->dropColumnIfExists($pdo, 'topic_revisions', 'topic_download_links');
    }

    public function down(PDO $pdo): void
    {
        $this->addColumnIfMissing($pdo, 'topics', 'topic_download_links', 'LONGTEXT NULL AFTER topic_descriptions');
        $this->addColumnIfMissing($pdo, 'topic_revisions', 'topic_download_links', 'LONGTEXT NULL AFTER topic_descriptions');
    }

    private function dropColumnIfExists(PDO $pdo, string $table, string $column): void
    {
        if (!$this->tableExists($pdo, $table) || !isset($this->columns($pdo, $table)[$column])) {
            return;
        }

        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
    }

    private function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!$this->tableExists($pdo, $table) || isset($this->columns($pdo, $table)[$column])) {
            return;
        }

        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
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
