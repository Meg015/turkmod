<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_07_22_0020_add_mods_club_category_mappings';
    }

    public function up(PDO $pdo): void
    {
        if (
            !$this->tableExists($pdo, 'bot_sites')
            || !$this->tableExists($pdo, 'bot_category_mappings')
            || !$this->tableExists($pdo, 'categories')
        ) {
            return;
        }

        $siteId = $this->modsClubSiteId($pdo);
        $categoryIds = $this->categoryIdsBySlug($pdo);
        $now = date('Y-m-d H:i:s');

        $exists = $pdo->prepare(
            'SELECT id FROM bot_category_mappings WHERE bot_site_id = ? AND remote_category_url = ? LIMIT 1'
        );
        $insert = $pdo->prepare(
            "INSERT INTO bot_category_mappings
                (bot_site_id, remote_category_name, remote_category_url, title_prefix, local_category_id, status, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, ?, 'active', ?, ?)"
        );
        $update = $pdo->prepare(
            "UPDATE bot_category_mappings
             SET remote_category_name = ?, title_prefix = ?, local_category_id = ?, status = 'active', updated_at = ?
             WHERE bot_site_id = ? AND remote_category_url = ?"
        );

        foreach ($this->mappings() as $mapping) {
            $slug = (string) $mapping['category_slug'];
            if (!isset($categoryIds[$slug])) {
                throw new RuntimeException('Local category is missing for mods.club mapping: ' . $slug);
            }

            $url = (string) $mapping['url'];
            $exists->execute([$siteId, $url]);
            if ($exists->fetchColumn()) {
                $update->execute([
                    (string) $mapping['name'],
                    (string) $mapping['title_prefix'],
                    $categoryIds[$slug],
                    $now,
                    $siteId,
                    $url,
                ]);
                continue;
            }

            $insert->execute([
                $siteId,
                (string) $mapping['name'],
                $url,
                (string) $mapping['title_prefix'],
                $categoryIds[$slug],
                $now,
                $now,
            ]);
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Mods.club category mappings are not reverted automatically.');
    }

    private function modsClubSiteId(PDO $pdo): int
    {
        $siteId = $this->findModsClubSiteId($pdo);
        if ($siteId > 0) {
            return $siteId;
        }

        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare(
            "INSERT INTO bot_sites
                (name, slug, base_url, description, status, selectors, settings, created_at, updated_at)
             VALUES
                (?, ?, ?, ?, 'active', ?, ?, ?, ?)"
        );
        $insert->execute([
            'Mods.club',
            'mods-club',
            'https://mods.club',
            'Mods.club scraper source',
            $this->modsClubSelectors(),
            $this->modsClubSettings(),
            $now,
            $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function findModsClubSiteId(PDO $pdo): int
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM bot_sites WHERE slug = ? OR base_url = ? OR base_url = ? ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['mods-club', 'https://mods.club', 'https://mods.club/']);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<string,int>
     */
    private function categoryIdsBySlug(PDO $pdo): array
    {
        $slugs = array_values(array_unique(array_map(
            static fn (array $mapping): string => (string) $mapping['category_slug'],
            $this->mappings()
        )));
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare("SELECT id, slug FROM categories WHERE slug IN ({$placeholders})");
        $stmt->execute($slugs);

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ids[(string) $row['slug']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @return array<int,array{name:string,url:string,title_prefix:string,category_slug:string}>
     */
    private function mappings(): array
    {
        return [
            ['name' => 'Ets2-maps', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-maps/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-harita-modlari'],
            ['name' => 'Ets2-car-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-car-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-araba-otobus-modlari'],
            ['name' => 'Ets2-bus-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-bus-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-araba-otobus-modlari'],
            ['name' => 'Ets2-interior-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-interior-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-modifiye-parca-modlari'],
            ['name' => 'Ets2-other-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-other-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-diger-modlar'],
            ['name' => 'Ets2-part-and-tuning-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-part-and-tuning-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-modifiye-parca-modlari'],
            ['name' => 'Ets2-skins', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-skins/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-skin'],
            ['name' => 'Ets2-sound-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-sound-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-trafik-ses-modlari'],
            ['name' => 'Ets2-trailer-mods', 'url' => 'https://mods.club/category/euro-truck-simulator-2-mods/ets2-trailer-mods/', 'title_prefix' => 'ETS2 -', 'category_slug' => 'ets-2-dorse-modlari'],
            ['name' => 'Fs25-forklifts-and-excavator-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-forklifts-and-excavator-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-arac-modlari'],
            ['name' => 'Fs25-implements-and-tools-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-implements-and-tools-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-diger-modlar'],
            ['name' => 'Fs25-vehicles', 'url' => 'https://mods.club/category/fs25-mods/fs25-vehicles/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-arac-modlari'],
            ['name' => 'Fs25-packs', 'url' => 'https://mods.club/category/fs25-mods/fs25-packs/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-diger-modlar'],
            ['name' => 'Fs25-other-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-other-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-diger-modlar'],
            ['name' => 'Fs25-object-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-object-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-bina-obje-nesne-modlari'],
            ['name' => 'Fs25-maps', 'url' => 'https://mods.club/category/fs25-mods/fs25-maps/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-harita-modlari'],
            ['name' => 'Fs25-harvester-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-harvester-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs25-bicerdover-modlari'],
            ['name' => 'Fs25-cutter-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-cutter-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs25-bicerdover-modlari'],
            ['name' => 'Fs25-truck-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-truck-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-kamyon-araba-modlari'],
            ['name' => 'Fs25-car-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-car-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-kamyon-araba-modlari'],
            ['name' => 'Fs25-texture-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-texture-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs-25-diger-modlar'],
            ['name' => 'Fs25-trailer-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-trailer-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs25-romork-modlari'],
            ['name' => 'Fs25-tractor-mods', 'url' => 'https://mods.club/category/fs25-mods/fs25-tractor-mods/', 'title_prefix' => 'FS25 -', 'category_slug' => 'fs25-traktor-modlari'],
        ];
    }

    private function modsClubSelectors(): string
    {
        return (string) json_encode([
            'topic_list' => 'h2.post-title',
            'topic_link' => 'a',
            'title' => 'h1.post-title',
            'content' => 'div.post-body',
            'images' => 'div.post-body img, img[fetchpriority="high"]',
            'download_links' => 'a.btn[href*="sharemods"], a.btn[href*="modsfire"], a.btn[href*="mediafire"], a.btn[href*="mega.nz"], a.btn[href*="mods.to"], a.btn[href*="steamcommunity"], a[href*="sharemods.com"], a[href*="modsfire.com"], a[href*="mediafire.com"], a[href*="steamcommunity.com"]',
            'pagination' => 'a.next, .pagination a[rel="next"]',
        ], JSON_UNESCAPED_SLASHES);
    }

    private function modsClubSettings(): string
    {
        return (string) json_encode([
            'max_images' => 8,
            'translate' => true,
            'source_lang' => 'EN',
            'target_lang' => 'TR',
            'custom_headers' => '',
            'replacements' => [],
            'remove_texts' => [],
            'title_template' => '{title}',
            'content_prepend' => '',
            'content_append' => '',
            'remove_selectors' => '.ads, .advertisement, .social-share, script, style',
            'trim_before_text' => '',
            'trim_after_text' => '',
            'auto_tags' => [],
            'site_default_category_id' => 0,
            'site_default_status' => 'published',
            'site_default_author_id' => 0,
            'skip_image_contains' => 'logo,avatar,icon,banner,button',
            'allowed_image_domains' => 'mods.club,sharemods.com',
            'min_image_width' => 100,
            'download_link_replacements' => [],
            'skip_download_domains' => '',
            'detect_author_enabled' => true,
            'detect_author_labels' => 'author,authors,credit,credits,by,created by',
            'detect_version_enabled' => true,
            'detect_version_pattern' => '1\\.(?:[3-9]\\d|[1-9]\\d{2,})',
        ], JSON_UNESCAPED_SLASHES);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid database table name.');
        }

        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return (bool) ($stmt && $stmt->fetchColumn());
    }
};
