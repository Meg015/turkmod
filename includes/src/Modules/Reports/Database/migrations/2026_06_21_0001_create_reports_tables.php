<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_21_0001_create_reports_tables';
    }

    public function up(PDO $pdo): void
    {
        $schema = new App\Modules\Reports\Services\ReportsSchemaService();
        $schema->ensureUserReports($pdo, false);
        $schema->ensureTopicReports($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `topic_report_events`');
        $pdo->exec('DROP TABLE IF EXISTS `topic_reports`');
        $pdo->exec('DROP TABLE IF EXISTS `user_report_events`');
        $pdo->exec('DROP TABLE IF EXISTS `user_reports`');
    }
};
