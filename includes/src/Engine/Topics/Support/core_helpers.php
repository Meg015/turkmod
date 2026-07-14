<?php

declare(strict_types=1);

if (!function_exists('topicSearchNormalizeText')) {
function topicSearchNormalizeText(string $value): string
{
    $value = mb_strtolower(strip_tags($value), "UTF-8");
    $value = strtr($value, [
        "ç" => "c",
        "ğ" => "g",
        "ı" => "i",
        "i̇" => "i",
        "ö" => "o",
        "ş" => "s",
        "ü" => "u",
        "Ç" => "c",
        "Ğ" => "g",
        "İ" => "i",
        "I" => "i",
        "Ö" => "o",
        "Ş" => "s",
        "Ü" => "u",
    ]);
    $value = preg_replace("/[^a-z0-9]+/u", " ", $value) ?? "";
    return trim((string) preg_replace("/\s+/", " ", $value));
}

function topicSearchFuzzyScore(string $search, array $row): int
{
    $needle = topicSearchNormalizeText($search);
    if ($needle === "") {
        return 0;
    }

    $haystack = topicSearchNormalizeText(
        implode(" ", [
            (string) ($row["title"] ?? ""),
            (string) ($row["slug"] ?? ""),
            (string) ($row["category"] ?? ""),
            (string) ($row["author"] ?? ""),
            (string) ($row["topic_descriptions"] ?? ""),
        ]),
    );
    if ($haystack === "") {
        return 0;
    }

    if (str_contains($haystack, $needle)) {
        return 120;
    }

    $queryTokens = array_values(array_unique(array_filter(explode(" ", $needle))));
    $targetTokens = array_values(array_unique(array_filter(explode(" ", $haystack))));
    if ($queryTokens === [] || $targetTokens === []) {
        return 0;
    }

    $score = 0;
    foreach ($queryTokens as $queryToken) {
        if (mb_strlen($queryToken, "UTF-8") < 3) {
            continue;
        }

        $bestDistance = PHP_INT_MAX;
        foreach ($targetTokens as $targetToken) {
            if ($targetToken === $queryToken || str_contains($targetToken, $queryToken)) {
                $bestDistance = 0;
                break;
            }
            if (abs(strlen($targetToken) - strlen($queryToken)) > 3) {
                continue;
            }
            $bestDistance = min($bestDistance, levenshtein($queryToken, $targetToken));
        }

        $maxDistance = max(1, min(3, (int) floor(strlen($queryToken) * 0.34)));
        if ($bestDistance === 0) {
            $score += 35;
        } elseif ($bestDistance <= $maxDistance) {
            $score += max(10, 30 - ($bestDistance * 7));
        }
    }

    return min(95, $score);
}

function getTopicsFallbackSearch(
    ?PDO $pdo,
    string $search,
    int $page = 1,
    int $perPage = 20,
): array {
    $offset = max(0, ($page - 1) * $perPage);
    if (!$pdo || trim($search) === "") {
        return [
            "items" => [],
            "total" => 0,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }

    try {
        // Son yayinlardan aday alip toleransli metin skoru uygula.
        $sql = "SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, u.username AS author
                FROM topics t
                LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                LEFT JOIN categories cat ON t.category_id = cat.id
                LEFT JOIN users u ON t.author_id = u.id
                WHERE t.status = 'published' AND t.deleted_at IS NULL
                ORDER BY COALESCE(t.published_at, t.created_at) DESC
                LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $candidates = $stmt->fetchAll();

        $items = [];
        foreach ($candidates as $row) {
            $fuzzyScore = topicSearchFuzzyScore($search, $row);
            $score = $fuzzyScore;

            if ($score >= 45) {
                $row["_search_score"] = max($score, $fuzzyScore);
                $items[] = $row;
            }
        }

        usort(
            $items,
            static fn(array $a, array $b): int => ((int) ($b["_search_score"] ?? 0)) <=>
                ((int) ($a["_search_score"] ?? 0)),
        );
        $total = count($items);
        $items = array_slice($items, $offset, $perPage);

        $items = array_map(static function (array $row): array {
            $row["topic_first_image"] = getTopicPrimaryMediaPath($row);
            return $row;
        }, $items);

        return [
            "items" => $items,
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
        ];
    } catch (Throwable $e) {
        appLogException($e, [
            "source" => "getTopicsFallbackSearch",
            "search" => $search,
        ]);
        return [
            "items" => [],
            "total" => 0,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }
}

function getTopics(
    ?PDO $pdo,
    int $page = 1,
    int $perPage = 20,
    string $search = "",
    string $sort = "newest",
    string $categorySlug = "",
): array {
    $page = max(1, $page);
    $perPage = max(1, min(60, $perPage));
    $offset = ($page - 1) * $perPage;
    $search = function_exists("sanitizeSearchQuery")
        ? sanitizeSearchQuery($search)
        : trim($search);
    $categorySlug = function_exists("validateSlug")
        ? validateSlug($categorySlug) ?? ""
        : preg_replace("/[^a-z0-9-]/", "", $categorySlug);
    $allowedSorts = ["newest", "popular", "downloads", "comments"];
    $sort = in_array($sort, $allowedSorts, true) ? $sort : "newest";
    $demoTopics = [];

    if (!$pdo) {
        if ($search !== "") {
            $demoTopics = array_filter($demoTopics, function ($t) use (
                $search,
            ) {
                return mb_stripos($t["title"], $search) !== false ||
                    mb_stripos($t["topic_descriptions"] ?? "", $search) !==
                        false;
            });
            $demoTopics = array_values($demoTopics);
        }
        $total = count($demoTopics);
        return [
            "items" => array_slice($demoTopics, $offset, $perPage),
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }

    try {
        $where = "WHERE t.status = 'published' AND t.deleted_at IS NULL";
        $params = [];

        if ($categorySlug !== "") {
            $where .= " AND cat.slug = :category_slug";
            $params["category_slug"] = $categorySlug;
        }

        if ($search !== "") {
            // FULLTEXT arama kullan (DB'de FULLTEXT index: title, topic_descriptions)
            $where .=
                " AND MATCH(t.title, t.topic_descriptions) AGAINST(:search IN BOOLEAN MODE)";
            // Boolean mode'da kelime bazlı arama için + prefix ekle
            $searchTerms = implode(
                " ",
                array_map(
                    fn($w) => "+" . $w . "*",
                    preg_split("/\s+/", trim($search)),
                ),
            );
            $params["search"] = $searchTerms;
        }

        $orderBy = match ($sort) {
            "popular"
                => "t.view_count DESC, t.download_count DESC, COALESCE(t.published_at, t.created_at) DESC, t.id DESC",
            "downloads"
                => "t.download_count DESC, COALESCE(t.published_at, t.created_at) DESC, t.id DESC",
            "comments"
                => "t.comment_count DESC, COALESCE(t.published_at, t.created_at) DESC, t.id DESC",
            default => "COALESCE(t.published_at, t.created_at) DESC, t.id DESC",
        };

        // Count total
        $countSql = "SELECT COUNT(*) FROM topics t LEFT JOIN categories cat ON t.category_id = cat.id {$where}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch items
        $sql = "SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, u.username AS author
                FROM topics t
                LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                LEFT JOIN categories cat ON t.category_id = cat.id
                LEFT JOIN users u ON t.author_id = u.id
                {$where}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":" . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(":limit", $perPage, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows) && $search !== "") {
            return $categorySlug === ""
                ? getTopicsFallbackSearch($pdo, $search, $page, $perPage)
                : [
                    "items" => [],
                    "total" => 0,
                    "page" => $page,
                    "perPage" => $perPage,
                ];
        }

        if (empty($rows) && $page === 1 && $search === "") {
            $total = count($demoTopics);
            return [
                "items" => $demoTopics,
                "total" => $total,
                "page" => $page,
                "perPage" => $perPage,
            ];
        }

        $rows = array_map(static function (array $row): array {
            $row["topic_first_image"] = getTopicPrimaryMediaPath($row);
            return $row;
        }, $rows);

        return [
            "items" => $rows,
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
        ];
    } catch (Throwable $e) {
        $total = count($demoTopics);
        return [
            "items" => $demoTopics,
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }
}

function getTopic(?PDO $pdo, int $id): ?array
{
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, u.username AS author
                               FROM topics t
                               LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                               LEFT JOIN categories cat ON t.category_id = cat.id
                               LEFT JOIN users u ON t.author_id = u.id
                               WHERE t.id = :id AND t.deleted_at IS NULL");
        $stmt->execute(["id" => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $row["topic_first_image"] = getTopicPrimaryMediaPath($row);
        }
        return $row ?: null;
    } catch (Throwable $e) {
        appLogException($e, ["fn" => "getTopic", "id" => $id]);
        return null;
    }
}

function getTopicsByCategorySlug(
    ?PDO $pdo,
    string $slug,
    int $page = 1,
    int $perPage = 20,
): array {
    $offset = ($page - 1) * $perPage;

    if (!$pdo) {
        return [
            "items" => [],
            "total" => 0,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }

    try {
        // Find category ID by slug
        $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE slug = :slug AND deleted_at IS NULL LIMIT 1");
        $stmtCat->execute(["slug" => $slug]);
        $catId = $stmtCat->fetchColumn();

        if (!$catId) {
            return [
                "items" => [],
                "total" => 0,
                "page" => $page,
                "perPage" => $perPage,
            ];
        }

        $catId = (int) $catId;

        // Find self and subcategory IDs
        $stmtSub = $pdo->prepare("SELECT id FROM categories WHERE (id = :id OR parent_id = :parent_id) AND deleted_at IS NULL");
        $stmtSub->execute(["id" => $catId, "parent_id" => $catId]);
        $catIds = array_map('intval', $stmtSub->fetchAll(PDO::FETCH_COLUMN));

        if (empty($catIds)) {
            $catIds = [$catId];
        }

        $placeholders = implode(",", array_fill(0, count($catIds), "?"));

        // Count sql
        $countSql = "SELECT COUNT(*) FROM topics t
                     WHERE t.status = 'published' AND t.deleted_at IS NULL AND t.category_id IN ($placeholders)";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($catIds);
        $total = (int) $countStmt->fetchColumn();

        // Fetch sql
        $sql = "SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, u.username AS author
                FROM topics t
                LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id
                INNER JOIN categories cat ON t.category_id = cat.id
                LEFT JOIN users u ON t.author_id = u.id
                WHERE t.status = 'published' AND t.deleted_at IS NULL AND t.category_id IN ($placeholders)
                ORDER BY t.published_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        // Bind placeholders
        foreach ($catIds as $idx => $id) {
            $stmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($catIds) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($catIds) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $items = array_map(static function (array $row): array {
            $row["topic_first_image"] = getTopicPrimaryMediaPath($row);
            return $row;
        }, $items);

        return [
            "items" => $items,
            "total" => $total,
            "page" => $page,
            "perPage" => $perPage,
        ];
    } catch (Throwable $e) {
        appLogException($e, [
            "fn" => "getTopicsByCategorySlug",
            "slug" => $slug,
        ]);
        return [
            "items" => [],
            "total" => 0,
            "page" => $page,
            "perPage" => $perPage,
        ];
    }
}

function getTopicComments(?PDO $pdo, int $topicId): array
{
    if (!$pdo) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT c.*, u.username AS author
                               FROM comments c
                               LEFT JOIN users u ON c.user_id = u.id
                               WHERE c.topic_id = :topic_id AND c.status = 'approved' AND c.deleted_at IS NULL
                               ORDER BY c.created_at ASC");
        $stmt->execute(["topic_id" => $topicId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

}
