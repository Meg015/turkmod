<?php

declare(strict_types=1);

if (!function_exists('topicSearchBuildBooleanQuery')) {
function topicSearchBuildBooleanQuery(string $search): string
{
    $search = trim($search);
    if ($search === '') {
        return '';
    }

    $search = preg_replace('/[+\-><()~*"@]+/u', ' ', $search) ?? $search;
    $search = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $search) ?? $search;

    $terms = [];
    foreach (preg_split('/\s+/u', $search) ?: [] as $token) {
        $token = trim((string) $token, '_');
        if (mb_strlen($token, 'UTF-8') < 2) {
            continue;
        }

        $terms[$token] = '+' . $token . '*';
    }

    return implode(' ', array_slice(array_values($terms), 0, 8));
}
}

if (!function_exists('getTopics')) {
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
    if (!$pdo) {
        return [
            "items" => [],
            "total" => 0,
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
            // Keep BOOLEAN MODE safe for version-like searches such as (1.60).
            $searchLike = '%' . $search . '%';
            $searchFilters = [
                "t.title LIKE :search_title_like",
                "t.slug LIKE :search_slug_like",
                "COALESCE(cat.name, '') LIKE :search_category_like",
            ];
            $params["search_title_like"] = $searchLike;
            $params["search_slug_like"] = $searchLike;
            $params["search_category_like"] = $searchLike;

            $fullTextSearch = topicSearchBuildBooleanQuery($search);
            if ($fullTextSearch !== '') {
                array_unshift(
                    $searchFilters,
                    "MATCH(t.title, t.topic_descriptions) AGAINST(:search_fulltext IN BOOLEAN MODE)"
                );
                $params["search_fulltext"] = $fullTextSearch;
            }

            $where .= " AND (" . implode(" OR ", $searchFilters) . ")";
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
        appLogException($e, [
            "fn" => "getTopics",
            "search" => $search,
            "category" => $categorySlug,
        ]);
        return [
            "items" => [],
            "total" => 0,
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
