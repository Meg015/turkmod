<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Messages\Services\MessageSchemaService;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_29_0001_create_message_tables';
    }

    public function up(PDO $pdo): void
    {
        (new MessageSchemaService())->ensureSchema($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `message_messages`');
        $pdo->exec('DROP TABLE IF EXISTS `message_thread_participants`');
        $pdo->exec('DROP TABLE IF EXISTS `message_threads`');
    }
};

