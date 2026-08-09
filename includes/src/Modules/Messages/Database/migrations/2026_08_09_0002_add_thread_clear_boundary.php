<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Messages\Database\migrations\Support\MessageSchemaInstaller;

require_once __DIR__ . '/Support/MessageSchemaInstaller.php';

return new class implements Migration
{
    public function name(): string
    {
        return '2026_08_09_0002_add_thread_clear_boundary';
    }

    public function up(PDO $pdo): void
    {
        (new MessageSchemaInstaller())->ensureParticipantsTable($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $installer = new MessageSchemaInstaller();
        if (!$installer->tableExists($pdo, 'message_thread_participants')
            || !$installer->columnExists($pdo, 'message_thread_participants', 'cleared_through_message_id')) {
            return;
        }

        $pdo->exec('ALTER TABLE message_thread_participants DROP COLUMN cleared_through_message_id');
    }
};
