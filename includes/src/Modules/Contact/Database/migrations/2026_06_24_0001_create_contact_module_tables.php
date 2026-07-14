<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\Contact\Database\migrations\Support\ContactSchemaInstaller;

require_once __DIR__ . '/Support/ContactSchemaInstaller.php';

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_24_0001_create_contact_module_tables';
    }

    public function up(PDO $pdo): void
    {
        (new ContactSchemaInstaller())->ensureSchema($pdo, false);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `contact_messages`');
        $pdo->exec('DROP TABLE IF EXISTS `contact_categories`');
    }
};
