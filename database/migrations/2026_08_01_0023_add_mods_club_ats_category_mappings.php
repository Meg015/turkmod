<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_08_01_0023_add_mods_club_ats_category_mappings';
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
            $slug = (string)$mapping['category_slug'];
            if (!isset($categoryIds[$slug])) {
                throw new RuntimeException('Local ATS category is missing for mods.club mapping: ' . $slug);
            }

            $url = (string)$mapping['url'];
            $exists->execute([$siteId, $url]);
            if ($exists->fetchColumn()) {
                $update->execute([
                    (string)$mapping['name'],
                    (string)$mapping['title_prefix'],
                    $categoryIds[$slug],
                    $now,
                    $siteId,
                    $url,
                ]);
                continue;
            }

            $insert->execute([
                $siteId,
                (string)$mapping['name'],
                $url,
                (string)$mapping['title_prefix'],
                $categoryIds[$slug],
                $now,
                $now,
            ]);
        }
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Mods.club ATS category mappings are not reverted automatically.');
    }

    private function modsClubSiteId(PDO $pdo): int
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM bot_sites WHERE slug = ? OR base_url = ? OR base_url = ? ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['mods-club', 'https://mods.club', 'https://mods.club/']);
        $siteId = (int)($stmt->fetchColumn() ?: 0);
        if ($siteId <= 0) {
            throw new RuntimeException('Mods.club scraper source is missing.');
        }

        return $siteId;
    }

    /**
     * @return array<string,int>
     */
    private function categoryIdsBySlug(PDO $pdo): array
    {
        $slugs = array_values(array_unique(array_map(
            static fn(array $mapping): string => (string)$mapping['category_slug'],
            $this->mappings()
        )));
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare("SELECT id, slug FROM categories WHERE slug IN ({$placeholders})");
        $stmt->execute($slugs);

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ids[(string)$row['slug']] = (int)$row['id'];
        }

        return $ids;
    }

    /**
     * @return array<int,array{name:string,url:string,title_prefix:string,category_slug:string}>
     */
    private function mappings(): array
    {
        return [
            ['name' => 'Ats-truck-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-truck-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-cekici-modlari'],
            ['name' => 'Ats-trailers-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-trailers-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-dorse-modlari'],
            ['name' => 'Ats-sound-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-sound-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-ses-modlari'],
            ['name' => 'Ats-skins', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-skins/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-skinler'],
            ['name' => 'Ats-parts-tuning-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-parts-tuning-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-modifiye-parca-modlari'],
            ['name' => 'Ats-other-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-other-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-diger-modlar'],
            ['name' => 'Ats-maps', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-maps/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-harita-modlari'],
            ['name' => 'Ats-interior-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-interior-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-modifiye-parca-modlari'],
            ['name' => 'Ats-car-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-car-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-araba-otobus-modlari'],
            ['name' => 'Ats-bus-mods', 'url' => 'https://mods.club/category/american-truck-simulator-mods/ats-bus-mods/', 'title_prefix' => 'ATS -', 'category_slug' => 'ats-araba-otobus-modlari'],
        ];
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid database table name.');
        }

        if ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

        return (bool)($stmt && $stmt->fetchColumn());
    }
};
