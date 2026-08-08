<?php

declare(strict_types=1);

namespace App\Modules\StaticPages\Database\migrations\Support;

use App\Core\Database\SchemaInspector;
use PDO;

final class StaticPageSchemaInstaller
{
    public function __construct(private ?SchemaInspector $inspector = null)
    {
        $this->inspector ??= new SchemaInspector();
    }

    public function ensureSchema(PDO $pdo): void
    {
        if ($this->inspector->isSqlite($pdo)) {
            $this->createSqliteSchema($pdo);
        } else {
            $this->createMysqlSchema($pdo);
        }

        $this->seedSystemPages($pdo);
    }

    private function createMysqlSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `static_pages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `system_key` VARCHAR(50) DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(191) NOT NULL,
            `body_html` LONGTEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `seo_title` VARCHAR(255) NOT NULL DEFAULT '',
            `meta_description` TEXT NOT NULL,
            `og_image` VARCHAR(2048) NOT NULL DEFAULT '',
            `robots_noindex` TINYINT(1) NOT NULL DEFAULT 0,
            `robots_nofollow` TINYINT(1) NOT NULL DEFAULT 0,
            `sitemap_include` TINYINT(1) NOT NULL DEFAULT 1,
            `show_in_footer` TINYINT(1) NOT NULL DEFAULT 0,
            `footer_label` VARCHAR(255) NOT NULL DEFAULT '',
            `footer_order` INT NOT NULL DEFAULT 0,
            `created_by_admin_id` BIGINT UNSIGNED DEFAULT NULL,
            `updated_by_admin_id` BIGINT UNSIGNED DEFAULT NULL,
            `published_at` DATETIME DEFAULT NULL,
            `archived_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `static_pages_system_key_unique` (`system_key`),
            UNIQUE KEY `static_pages_slug_unique` (`slug`),
            KEY `static_pages_status_updated_index` (`status`, `updated_at`),
            KEY `static_pages_footer_index` (`status`, `show_in_footer`, `footer_order`),
            KEY `static_pages_sitemap_index` (`status`, `sitemap_include`, `robots_noindex`),
            CONSTRAINT `static_pages_created_by_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `static_pages_updated_by_foreign` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `static_page_redirects` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `page_id` BIGINT UNSIGNED NOT NULL,
            `old_slug` VARCHAR(191) NOT NULL,
            `created_by_admin_id` BIGINT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `static_page_redirects_old_slug_unique` (`old_slug`),
            KEY `static_page_redirects_page_index` (`page_id`),
            CONSTRAINT `static_page_redirects_page_foreign` FOREIGN KEY (`page_id`) REFERENCES `static_pages` (`id`) ON DELETE CASCADE,
            CONSTRAINT `static_page_redirects_admin_foreign` FOREIGN KEY (`created_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function createSqliteSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS static_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            system_key TEXT UNIQUE,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            body_html TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            seo_title TEXT NOT NULL DEFAULT '',
            meta_description TEXT NOT NULL DEFAULT '',
            og_image TEXT NOT NULL DEFAULT '',
            robots_noindex INTEGER NOT NULL DEFAULT 0,
            robots_nofollow INTEGER NOT NULL DEFAULT 0,
            sitemap_include INTEGER NOT NULL DEFAULT 1,
            show_in_footer INTEGER NOT NULL DEFAULT 0,
            footer_label TEXT NOT NULL DEFAULT '',
            footer_order INTEGER NOT NULL DEFAULT 0,
            created_by_admin_id INTEGER,
            updated_by_admin_id INTEGER,
            published_at TEXT,
            archived_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS static_pages_status_updated_index ON static_pages (status, updated_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS static_pages_footer_index ON static_pages (status, show_in_footer, footer_order)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS static_pages_sitemap_index ON static_pages (status, sitemap_include, robots_noindex)');

        $pdo->exec("CREATE TABLE IF NOT EXISTS static_page_redirects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            old_slug TEXT NOT NULL UNIQUE,
            created_by_admin_id INTEGER,
            created_at TEXT NOT NULL,
            FOREIGN KEY (page_id) REFERENCES static_pages (id) ON DELETE CASCADE
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS static_page_redirects_page_index ON static_page_redirects (page_id)');
    }

    private function seedSystemPages(PDO $pdo): void
    {
        $pages = [
            ['about', 'Hakkımızda', 'hakkimizda', 10],
            ['faq', 'Sıkça Sorulan Sorular', 'sss', 20],
            ['dmca', 'DMCA / Telif Hakkı İhlali Bildirimi', 'dmca', 30],
            ['privacy', 'Gizlilik Politikası', 'gizlilik-politikasi', 40],
            ['terms', 'Kullanım Koşulları', 'kullanim-kosullari', 50],
        ];
        $exists = $pdo->prepare('SELECT 1 FROM static_pages WHERE system_key = :system_key LIMIT 1');
        $insert = $pdo->prepare("INSERT INTO static_pages
            (system_key, title, slug, body_html, status, seo_title, meta_description, og_image, robots_noindex, robots_nofollow, sitemap_include, show_in_footer, footer_label, footer_order, created_at, updated_at)
            VALUES (:system_key, :title, :slug, '', 'draft', '', '', '', 0, 0, 1, 1, '', :footer_order, :created_at, :updated_at)");
        $now = date('Y-m-d H:i:s');

        foreach ($pages as [$systemKey, $title, $slug, $footerOrder]) {
            $exists->execute(['system_key' => $systemKey]);
            if ($exists->fetchColumn()) {
                continue;
            }
            $insert->execute([
                'system_key' => $systemKey,
                'title' => $title,
                'slug' => $slug,
                'footer_order' => $footerOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
