<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\BanAppeals\Database\migrations\Support\BanAppealSchemaInstaller;

require_once __DIR__ . '/Support/BanAppealSchemaInstaller.php';

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_21_0001_create_ban_appeal_tables';
    }

    public function up(PDO $pdo): void
    {
        $schema = new BanAppealSchemaInstaller();
        $schema->ensureSchema($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `ban_appeal_messages`');
        $pdo->exec('DROP TABLE IF EXISTS `ban_appeals`');
    }
};
