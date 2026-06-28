<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_21_0001_create_ban_appeal_tables';
    }

    public function up(PDO $pdo): void
    {
        $schema = new App\Modules\BanAppeals\Services\BanAppealSchemaService();
        $schema->ensureSchema($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `ban_appeal_messages`');
        $pdo->exec('DROP TABLE IF EXISTS `ban_appeals`');
    }
};
