<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Core\Database\SchemaInspector;
use PDO;

final class ReportsSchemaService
{
    public function __construct(private ?SchemaInspector $inspector = null) { $this->inspector ??= new SchemaInspector(); }
    public function isSqlite(PDO $pdo): bool { return $this->inspector->isSqlite($pdo); }
    public function now(): string { return date('Y-m-d H:i:s'); }
    public function ensureUserReports(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['user_reports', 'user_report_events']); }
    public function ensureUserReportEvents(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['user_report_events']); }
    public function ensureTopicReports(PDO $pdo, bool $unused = true): void
    {
        $this->inspector->requireTables($pdo, ['topic_reports', 'topic_report_events']);
        $this->inspector->requireColumns($pdo, 'topic_reports', ['reporter_name', 'reporter_email', 'reporter_type']);
    }
    public function ensureTopicReportEvents(PDO $pdo, bool $unused = true): void { $this->inspector->requireTables($pdo, ['topic_report_events']); }
}
