<?php

declare(strict_types=1);

use App\Core\Database\Migration;
use App\Modules\StaticPages\Database\migrations\Support\StaticPageSchemaInstaller;

require_once __DIR__ . '/Support/StaticPageSchemaInstaller.php';

return new class implements Migration
{
    public function name(): string
    {
        return '2026_08_08_0001_create_static_pages_tables';
    }

    public function up(PDO $pdo): void
    {
        (new StaticPageSchemaInstaller())->ensureSchema($pdo);
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `static_page_redirects`');
        $pdo->exec('DROP TABLE IF EXISTS `static_pages`');
    }
};
