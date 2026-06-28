<?php

declare(strict_types=1);

function getPublicCategories(?PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $fallback = [
        [
            "name" => "Design",
            "slug" => "design",
            "description" => "Arayuz, gorsel ve deneyim odakli icerikler.",
            "seo_description" => "",
            "topic_count" => 0,
            "parent_slug" => null,
        ],
        [
            "name" => "Development",
            "slug" => "development",
            "description" => "Kod, entegrasyon ve gelistirme araclari.",
            "seo_description" => "",
            "topic_count" => 0,
            "parent_slug" => null,
        ],
        [
            "name" => "Operations",
            "slug" => "operations",
            "description" => "Sunucu, yayinlama ve operasyon yardimcilari.",
            "seo_description" => "",
            "topic_count" => 0,
            "parent_slug" => null,
        ],
    ];

    if (!$pdo) {
        return $fallback;
    }

    try {
        // parent_slug aynı sorguda LEFT JOIN ile çekiliyor → N+1 önleniyor.
        $stmt = $pdo->query("SELECT cat.id, cat.name, cat.slug, cat.parent_id, cat.description, cat.seo_description,
                                    parent.slug AS parent_slug,
                                    COUNT(t.id) AS topic_count
                             FROM categories cat
                             LEFT JOIN categories parent ON parent.id = cat.parent_id
                             LEFT JOIN topics t ON t.category_id = cat.id
                                 AND t.status = 'published'
                                 AND t.deleted_at IS NULL
                             WHERE cat.status = 'active' AND cat.deleted_at IS NULL
                             GROUP BY cat.id, cat.name, cat.slug, cat.parent_id, cat.description, cat.seo_description, parent.slug, cat.display_order
                             ORDER BY cat.display_order, cat.name");
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            $categories = [];
            foreach ($rows as $row) {
                $categories[] = [
                    "id" => (int) ($row["id"] ?? 0),
                    "name" => (string) $row["name"],
                    "slug" => (string) ($row["slug"] ?? ""),
                    "description" => (string) ($row["description"] ?? ""),
                    "seo_description" => (string) ($row["seo_description"] ?? ""),
                    "parent_id" =>
                        isset($row["parent_id"]) && $row["parent_id"] !== null
                            ? (int) $row["parent_id"]
                            : null,
                    "parent_slug" =>
                        $row["parent_slug"] !== null
                            ? (string) $row["parent_slug"]
                            : null,
                    "topic_count" => (int) ($row["topic_count"] ?? 0),
                ];
            }

            // Sum child topic counts into parent categories
            $by_id = [];
            foreach ($categories as $c) {
                $by_id[$c["id"]] = $c;
            }
            foreach ($categories as $c) {
                if ($c["parent_id"] !== null && isset($by_id[$c["parent_id"]])) {
                    $by_id[$c["parent_id"]]["topic_count"] += $c["topic_count"];
                }
            }

            $cache = [];
            foreach ($categories as $c) {
                $cache[] = $by_id[$c["id"]];
            }
            return $cache;
        }
    } catch (Throwable $e) {
        appLogException($e, ["source" => "getPublicCategories"]);
    }

    $cache = $fallback;
    return $cache;
}

function getPublicCategoriesTree(?PDO $pdo): array
{
    $cacheFile = dirname(__DIR__, 4) . '/storage/cache/categories_tree.php';
    if (is_file($cacheFile) && filemtime($cacheFile) > (time() - 3600)) {
        return require $cacheFile;
    }

    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->query("SELECT cat.id, cat.parent_id, cat.name, cat.slug, cat.description, cat.seo_description, COUNT(t.id) AS topic_count
                             FROM categories cat
                             LEFT JOIN topics t ON t.category_id = cat.id
                                 AND t.status = 'published'
                                 AND t.deleted_at IS NULL
                             WHERE cat.status = 'active' AND cat.deleted_at IS NULL
                             GROUP BY cat.id, cat.parent_id, cat.name, cat.slug, cat.description, cat.seo_description, cat.display_order
                             ORDER BY cat.display_order, cat.name");
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    if (empty($rows)) {
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $id = (int) $row["id"];
        $byId[$id] = [
            "id" => $id,
            "parent_id" =>
                $row["parent_id"] !== null ? (int) $row["parent_id"] : null,
            "name" => (string) $row["name"],
            "slug" => (string) ($row["slug"] ?? ""),
            "description" => (string) ($row["description"] ?? ""),
            "seo_description" => (string) ($row["seo_description"] ?? ""),
            "topic_count" => (int) ($row["topic_count"] ?? 0),
            "children" => [],
        ];
    }

    $tree = [];
    foreach ($byId as $id => &$node) {
        if ($node["parent_id"] !== null && isset($byId[$node["parent_id"]])) {
            $byId[$node["parent_id"]]["children"][] = &$node;
        } else {
            $tree[] = &$node;
        }
    }
    unset($node);

    if (!is_dir(dirname($cacheFile))) {
        @mkdir(dirname($cacheFile), 0775, true);
    }
    file_put_contents($cacheFile, "<?php\nreturn " . var_export($tree, true) . ";\n", LOCK_EX);

    return $tree;
}
