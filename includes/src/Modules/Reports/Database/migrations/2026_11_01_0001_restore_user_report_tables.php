<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Reports\Database\migrations\Support\ReportsSchemaInstaller;

require_once __DIR__ . '/Support/ReportsSchemaInstaller.php';

return new class implements Migration
{
    public function name(): string
    {
        return '2026_11_01_0001_restore_user_report_tables';
    }

    public function up(PDO $pdo): void
    {
        (new ReportsSchemaInstaller())->ensureUserReports($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Kullanıcı şikayetleri ve işlem geçmişi otomatik geri dönüşte silinmez.');
    }
};
