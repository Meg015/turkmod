<?php
declare(strict_types=1);



$settings = function_exists('getAdminSettings') ? getAdminSettings($pdo) : [];

$cacheEnabled = ($settings['cache_enabled'] ?? '1') === '1';

if ($cacheEnabled && empty($_SESSION['_auth_user_id'])) {

    // Topic pages can render CSRF-backed comment/download/auth controls.
    // Keep them out of shared caches so one visitor's token is never reused by another.
    header("Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
    header('Vary: Accept-Encoding, Cookie');
} else {

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    header("Pragma: no-cache");

    header("Expires: 0");

}

require_once $projectRoot . "/includes/src/Engine/Seo/Support/helpers.php";


$id = (int) ($_GET["id"] ?? 0);

$slug = trim($_GET["slug"] ?? "");

$routedTopicParts = topicRouteParts($slug, $settings);

$lookupTopicBySlug = static function (string $lookupSlug) use ($pdo): ?array {

    $lookupSlug = trim($lookupSlug);

    if ($lookupSlug === "" || !$pdo) {

        return null;

    }



    try {

        $stmt = $pdo->prepare("SELECT t.*, pm.path AS primary_media_path, cat.name AS category, cat.slug AS category_slug, u.username AS author

                               FROM topics t

                               LEFT JOIN media_files pm ON pm.id = t.primary_media_file_id

                               LEFT JOIN categories cat ON t.category_id = cat.id

                               LEFT JOIN users u ON t.author_id = u.id

                               WHERE t.slug = :slug AND t.deleted_at IS NULL AND t.status = 'published'");

        $stmt->execute(["slug" => $lookupSlug]);

        return $stmt->fetch() ?: null;

    } catch (Throwable $e) {

        appLogException($e, [

            "source" => "topic.php slugLookup",

            "slug" => $lookupSlug,

        ]);

        return null;

    }

};



if ($id > 0) {

    $topic = getTopic($pdo, $id);

} elseif (!empty($routedTopicParts["has_id_suffix"]) && (int) ($routedTopicParts["id"] ?? 0) > 0) {

    $candidate = getTopic($pdo, (int) $routedTopicParts["id"]);

    $candidateCanonical = $candidate

        ? topicCanonicalSlug((string) ($candidate["slug"] ?? ""), (int) ($candidate["id"] ?? 0), $settings)

        : "";



    if ($candidate && rawurldecode($slug) === $candidateCanonical) {

        $topic = $candidate;

    } elseif ($candidate && $candidateCanonical !== "") {

        header("Location: " . topicUrl((string) ($candidate["slug"] ?? ""), (int) ($candidate["id"] ?? 0)), true, 301);
        exit();

    } else {

        $topic = $lookupTopicBySlug($slug) ?: $lookupTopicBySlug((string) ($routedTopicParts["slug"] ?? ""));

    }

} elseif ($slug !== "") {

    $topic = $lookupTopicBySlug($slug);

} else {

    $topic = null;

}



if (!$topic) {

    http_response_code(404);

    $pageTitle = "Konu Bulunamadı";

    require_once $projectRoot . "/includes/public-header.php";

    echo uiRenderAlert('Konu bulunamadı veya silinmiş.', 'danger');

    require_once $projectRoot . "/includes/public-footer.php";

    exit();

}







if (

    ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&

    ($_POST["action"] ?? "") === "toggle_favorite"

) {

    if (!verify_csrf_token($_POST["_token"] ?? "")) {

        flash("error", "Güvenlik doğrulaması başarısız.");

        header(

            "Location: " . topicUrl((string) ($topic["slug"] ?? $topic["id"]), (int) ($topic["id"] ?? 0)),

        );

        exit();

    }



    if (!$isLoggedIn) {

        flash("error", "Favorilere eklemek için giriş yapmalısınız.");

        $loginUrl = routePublicStaticUrl("login");

        header("Location: " . $loginUrl);

        exit();

    }



    try {

        $favorited = toggleTopicFavorite(

            $pdo,

            (int) $topic["id"],

            (int) ($_SESSION["_auth_user_id"] ?? 0),

        );

        flash(

            "success",

            $favorited

                ? "İçerik favorilere eklendi."

                : "İçerik favorilerden kaldırıldı.",

        );

    } catch (Throwable $e) {

        flash("error", "Favori işlemi sırasında bir hata oluştu.");

    }



    header("Location: " . topicUrl((string) ($topic["slug"] ?? $topic["id"]), (int) ($topic["id"] ?? 0)));

    exit();

}



// Görüntülenme sayısını artır işlemi asenkron (api/track-view.php) tarafına taşındı.



$pageTitle = trim((string) ($topic['meta_title'] ?? ''));

if ($pageTitle === '') {

    $pageTitle = (string) ($topic['title'] ?? 'Konu');

}

$cleanTopicDescription = topicDescriptionWithoutRepeatedTitle(

    (string) ($topic["topic_descriptions"] ?? ""),

    (string) ($topic["title"] ?? ""),

);

$metaDescriptionSource = trim((string) ($topic['meta_description'] ?? ''));

if ($metaDescriptionSource === '') {

    $metaDescriptionSource = $cleanTopicDescription;

}

$metaDescription = mb_substr(

    strip_tags($metaDescriptionSource),

    0,

    META_DESCRIPTION_MAX_LENGTH,

    'UTF-8'

);

$settings =

    function_exists("getAdminSettings") && $pdo ? getAdminSettings($pdo) : [];

$topicDownloadUrl = "";

if ($pdo && !empty($topic["id"])) {

    try {

        $downloadStmt = $pdo->prepare(

            "SELECT url FROM topic_download_links WHERE topic_id = ? ORDER BY display_order ASC, id ASC LIMIT 1",

        );

        $downloadStmt->execute([(int) $topic["id"]]);

        $topicDownloadUrl = trim((string) ($downloadStmt->fetchColumn() ?: ""));
        if (function_exists('topicDownloadNormalizeUrl')) {
            $topicDownloadUrl = topicDownloadNormalizeUrl($topicDownloadUrl);
        } else {
            $topicDownloadUrl = preg_replace('/[\x00-\x1F\x7F]+/u', '', $topicDownloadUrl) ?? $topicDownloadUrl;
            $topicDownloadUrl = trim($topicDownloadUrl);
        }
    } catch (Throwable $e) {

        appLogException($e, [

            "source" => "topic.php structuredDataDownloadUrl",

            "topic_id" => $topic["id"] ?? null,

        ]);

    }

}

$topicCanonicalPath = topicUrl((string) ($topic["slug"] ?? $topic["id"]), (int) ($topic["id"] ?? 0));

$topicCanonicalUrl = function_exists("seoCanonicalUrl")

    ? seoCanonicalUrl($topicCanonicalPath, $settings)

    : $topicCanonicalPath;

$topicPrimaryImage = getTopicPrimaryMediaPath($topic) ?? "";

$topicPrimaryImageUrl = "";

if ($topicPrimaryImage !== "") {

    $topicPrimaryImageUrl = strpos($topicPrimaryImage, "http") === 0

        ? $topicPrimaryImage

        : (function_exists("seoCanonicalUrl") ? seoCanonicalUrl($baseUri . "/" . ltrim($topicPrimaryImage, "/"), $settings) : $baseUri . "/" . ltrim($topicPrimaryImage, "/"));

}

$seoMetaTags = function_exists('seoGenerateTopicMeta')

    ? seoGenerateTopicMeta($topic, $settings, $topicCanonicalPath, true)

    : getSeoMeta($pageTitle, $metaDescription, $topicCanonicalPath, $topicPrimaryImageUrl, true, 'article');

$topicSchemaBody = mb_substr(strip_tags($cleanTopicDescription !== "" ? $cleanTopicDescription : (string) ($topic["content"] ?? "")), 0, 500, "UTF-8");
$resolveProfileSchemaUrl = static function (int $userId, string $username) use ($settings): string {
    if ($userId <= 0) {
        return '';
    }

    $profileUrl = publicProfileUrl([
        'id' => $userId,
        'name' => $username,
        'username' => $username,
    ]);

    if ($profileUrl === '' || $profileUrl === '#') {
        return '';
    }

    return function_exists('seoCanonicalUrl')
        ? seoCanonicalUrl($profileUrl, $settings)
        : $profileUrl;
};
$topicAuthorUrl = $resolveProfileSchemaUrl((int) ($topic['author_id'] ?? 0), (string) ($topic['author'] ?? 'Anonim'));
// SEO Schema.org: DiscussionForumPosting
$schemaData = [
    "@context" => "https://schema.org",

    "@type" => "DiscussionForumPosting",

    "headline" => $topic["title"],

    "url" => $topicCanonicalUrl,

    "mainEntityOfPage" => [

        "@type" => "WebPage",

        "@id" => $topicCanonicalUrl,

    ],

    "datePublished" => date('c', strtotime((string)($topic["created_at"] ?? 'now'))),
    "dateModified" => date('c', strtotime((string)($topic["updated_at"] ?? $topic["created_at"] ?? 'now'))),
    "author" => [
        "@type" => "Person",
        "name" => $topic["author"] ?? "Anonim"
    ],
    "articleBody" => $topicSchemaBody,

    "isAccessibleForFree" => true,

    "about" => [

        "@type" => "Thing",

        "name" => (string) ($topic["category"] ?? "Mod"),

    ],

    "interactionStatistic" => [

        [

            "@type" => "InteractionCounter",

            "interactionType" => "https://schema.org/ViewAction",

            "userInteractionCount" => (int) ($topic["view_count"] ?? 0),

        ],

        [

            "@type" => "InteractionCounter",

            "interactionType" => "https://schema.org/DownloadAction",

            "userInteractionCount" => (int) ($topic["download_count"] ?? 0),

        ],

    ],
];
if ($topicAuthorUrl !== '') {
    $schemaData["author"]["url"] = $topicAuthorUrl;
}
if ($topicPrimaryImageUrl !== "") {
    $schemaData["image"] = [$topicPrimaryImageUrl];
    $schemaData["thumbnailUrl"] = $topicPrimaryImageUrl;
}
if ($topicDownloadUrl !== "") {

    $schemaData["downloadUrl"] = $topicDownloadUrl;

}

if (!empty($topic["topic_version"])) {
    $schemaData["softwareVersion"] = (string) $topic["topic_version"];
}

$topicVideoSchema = null;

if ($pdo && !empty($topic["id"]) && function_exists('getTopicMediaGallery')) {
    try {
        $mediaLinks = getTopicMediaGallery($pdo, (int) $topic["id"]);
        $publishedAt = date('c', strtotime((string) ($topic['published_at'] ?? $topic['created_at'] ?? 'now')));
        foreach ($mediaLinks as $mediaUrl) {
            $mediaUrl = trim((string) $mediaUrl);
            if ($mediaUrl === '') {
                continue;
            }

            if (
                preg_match(
                    '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i',
                    $mediaUrl,
                    $ytMatch,
                )
            ) {
                $videoId = $ytMatch[1];
                $topicVideoSchema = [
                    '@type' => 'VideoObject',
                    'name' => (string) ($topic['title'] ?? 'Video'),
                    'description' => $topicSchemaBody,
                    'thumbnailUrl' => 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg',
                    'uploadDate' => $publishedAt,
                    'embedUrl' => 'https://www.youtube.com/embed/' . $videoId . '?rel=0',
                    'contentUrl' => 'https://www.youtube.com/watch?v=' . $videoId,
                ];
                break;
            }

            if (preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/i', $mediaUrl, $vimMatch)) {
                $videoId = $vimMatch[1];
                $topicVideoSchema = [
                    '@type' => 'VideoObject',
                    'name' => (string) ($topic['title'] ?? 'Video'),
                    'description' => $topicSchemaBody,
                    'uploadDate' => $publishedAt,
                    'embedUrl' => 'https://player.vimeo.com/video/' . $videoId,
                    'contentUrl' => 'https://vimeo.com/' . $videoId,
                ];
                if ($topicPrimaryImageUrl !== '') {
                    $topicVideoSchema['thumbnailUrl'] = $topicPrimaryImageUrl;
                }
                break;
            }

            if (preg_match('/\.(mp4|webm|ogg)$/i', $mediaUrl)) {
                $videoUrl = preg_match('~^(?:https?:)?//~i', $mediaUrl) === 1
                    ? $mediaUrl
                    : rtrim($baseUri, '/') . '/' . ltrim($mediaUrl, '/');
                $topicVideoSchema = [
                    '@type' => 'VideoObject',
                    'name' => (string) ($topic['title'] ?? 'Video'),
                    'description' => $topicSchemaBody,
                    'uploadDate' => $publishedAt,
                    'contentUrl' => $videoUrl,
                    'url' => $topicCanonicalUrl,
                ];
                if ($topicPrimaryImageUrl !== '') {
                    $topicVideoSchema['thumbnailUrl'] = $topicPrimaryImageUrl;
                }
                break;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}
if ($topicVideoSchema !== null) {
    $schemaData['video'] = [$topicVideoSchema];
}

if ($pdo && !empty($topic["id"])) {
    try {

        $stmtComments = $pdo->prepare("SELECT c.id, c.body, c.created_at, u.id AS author_id, u.username AS author FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.topic_id = ? AND c.status = 'approved' AND c.deleted_at IS NULL AND c.parent_id IS NULL ORDER BY c.created_at ASC LIMIT 10");
        $stmtComments->execute([(int) $topic["id"]]);

        $totalCommentsStmt = $pdo->prepare("SELECT COUNT(id) FROM comments WHERE topic_id = ? AND status = 'approved' AND deleted_at IS NULL");

        $totalCommentsStmt->execute([(int) $topic["id"]]);

        $schemaData['commentCount'] = (int)$totalCommentsStmt->fetchColumn();

        $schemaData['interactionStatistic'][] = [

            "@type" => "InteractionCounter",

            "interactionType" => "https://schema.org/CommentAction",

            "userInteractionCount" => (int) $schemaData['commentCount'],

        ];



        $schemaComments = [];
        while ($c = $stmtComments->fetch(PDO::FETCH_ASSOC)) {
            $commentAuthorUrl = $resolveProfileSchemaUrl((int) ($c['author_id'] ?? 0), (string) ($c['author'] ?? 'Anonim'));
            $commentAuthor = [
                "@type" => "Person",
                "name" => $c["author"] ?? "Anonim",
            ];
            if ($commentAuthorUrl !== '') {
                $commentAuthor['url'] = $commentAuthorUrl;
            }
            $schemaComments[] = [
                "@type" => "Comment",
                "text" => mb_substr(strip_tags((string)$c["body"]), 0, 300),
                "datePublished" => date('c', strtotime((string)$c["created_at"])),
                "author" => $commentAuthor
            ];
        }
        if (!empty($schemaComments)) {

            $schemaData['comment'] = $schemaComments;

        }

    } catch (Throwable $e) {

        // ignore

    }

}

$seoStructuredData = "<script type=\"application/ld+json\">\n" . json_encode($schemaData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n</script>";



$favoritesCount = getTopicFavoriteCount($pdo, (int) ($topic["id"] ?? 0));

$isFavorited = $isLoggedIn

    ? userHasFavoritedTopic(

        $pdo,

        (int) ($topic["id"] ?? 0),

        (int) ($_SESSION["_auth_user_id"] ?? 0),

    )

    : false;

$currentUserId = (int) ($_SESSION["_auth_user_id"] ?? 0);

$isAdminUser = function_exists("userIsAdmin") && userIsAdmin($pdo, $currentUserId);

$canManageTopic = $isLoggedIn

    && (

        $isAdminUser

        || (

            function_exists("userHasPermission")

            && userHasPermission($pdo, $currentUserId, "topics.edit")

        )

    );

$isTopicOwner = $isLoggedIn

    && $currentUserId > 0

    && (int) ($topic["author_id"] ?? 0) === $currentUserId;

$canEditTopic = $canManageTopic || $isTopicOwner;

$topicEditUrl = $canManageTopic

    ? $baseUri . "/admin/edit.php?id=" . (int) ($topic["id"] ?? 0)
    : routePublicStaticUrl('edit_topic') . '?id=' . (int) ($topic['id'] ?? 0);
$topicDetailShowToolbar = ($settings['topic_detail_show_toolbar'] ?? '1') === '1';

$topicDetailShowInfoPanel = ($settings['topic_detail_show_info_panel'] ?? '1') === '1';

$topicDetailShowMedia = ($settings['topic_detail_show_media'] ?? '1') === '1';

$topicDetailShowDownloadPanel = ($settings['topic_detail_show_download_panel'] ?? '1') === '1';

$topicDetailShowTags = ($settings['topic_detail_show_tags'] ?? '1') === '1';

$topicDetailCommentsEnabled = ($settings['topic_detail_comments_enabled'] ?? '1') === '1';

$topicDetailShowRelated = ($settings['show_related_topics'] ?? '1') === '1';



$bodyClass = trim((string) ($bodyClass ?? '') . ' topic-detail-page');

$pageCssFiles = ["assets/css/topic-report-modal.css"];

require_once $projectRoot . "/includes/public-header.php";

?>

<link rel="stylesheet" href="<?= asset_url("assets/css/pro-comments.css", $baseUri) ?>">

<script src="<?= asset_url("assets/js/enhanced-comments.js", $baseUri) ?>" defer></script>

<script src="<?= asset_url("assets/js/topic-enhanced-comments-init.js", $baseUri) ?>" defer></script>

<?php

$category = (string) ($topic["category"] ?? "Genel");

$categorySlug = (string) ($topic["category_slug"] ?? strtolower($category));

$_showAuthor = ($settings['show_author_info'] ?? "1") === "1";

$_showViews = ($settings['show_view_count'] ?? "1") === "1";

$_showDownloads = ($settings['show_download_count'] ?? "1") === "1";

$summary = mb_substr(

    trim(strip_tags($cleanTopicDescription)),

    0,

    150,

);

$downloadCount = isset($topic["download_count"])

    ? (int) $topic["download_count"]

    : 0;

$views = isset($topic["view_count"]) ? (int) $topic["view_count"] : 0;

$published = function_exists("formatAppDate")

    ? formatAppDate((string) ($topic["published_at"] ?? ($topic["created_at"] ?? "now")), $pdo)

    : date("d M Y", strtotime((string) ($topic["published_at"] ?? ($topic["created_at"] ?? "now"))));

$topicInfoValueAttrs = static function (string $value): string {

    $plainValue = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, "UTF-8"));

    if ($plainValue === "") {

        return "";

    }



    return ' title="' . htmlspecialchars($plainValue, ENT_QUOTES, "UTF-8") . '" data-info-value tabindex="0"';

};

$imageMap = [

    "Design" => "topic-ui.svg",

    "Development" => "topic-code.svg",

    "Operations" => "topic-server.svg",

];

$cover = $imageMap[$category] ?? "topic-pack.svg";
$topicHeroImageAlt = function_exists("seoGenerateImageAlt")
    ? seoGenerateImageAlt("topic-hero", (string) ($topic["title"] ?? "Konu"), $settings)
    : (string) ($topic["title"] ?? "Konu") . " kapak görseli";
$topicHeroImageTitle = function_exists("seoGenerateImageTitle")
    ? seoGenerateImageTitle("topic-hero", (string) ($topic["title"] ?? "Konu"), $settings)
    : $topicHeroImageAlt;


$favoritesCount = getTopicFavoriteCount($pdo, (int) ($topic["id"] ?? 0));

$isFavorited = $isLoggedIn

    ? userHasFavoritedTopic(

        $pdo,

        (int) ($topic["id"] ?? 0),

        (int) ($_SESSION["_auth_user_id"] ?? 0),

    )

    : false;

$currentUserId = (int) ($_SESSION["_auth_user_id"] ?? 0);

$isAdminUser = function_exists("userIsAdmin") && userIsAdmin($pdo, $currentUserId);

$canManageTopic = $isLoggedIn

    && (

        $isAdminUser

        || (

            function_exists("userHasPermission")

            && userHasPermission($pdo, $currentUserId, "topics.edit")

        )

    );

$isTopicOwner = $isLoggedIn

    && $currentUserId > 0

    && (int) ($topic["author_id"] ?? 0) === $currentUserId;

$canEditTopic = $canManageTopic || $isTopicOwner;

$topicEditUrl = $canManageTopic

    ? $baseUri . "/admin/edit.php?id=" . (int) ($topic["id"] ?? 0)
    : routePublicStaticUrl('edit_topic') . '?id=' . (int) ($topic['id'] ?? 0);


// Yorumları DB'den çek

$comments = getTopicComments($pdo, (int) ($topic["id"] ?? $id));

?>



<?= getBreadcrumbs([

    ["label" => "Ana Sayfa", "url" => $baseUri . "/index.php"],

    ["label" => $category, "url" => categoryUrl($categorySlug)],

    ["label" => $topic["title"]],

]) ?>



<div class="topic-layout topic-detail-layout topic-detail ui-section"
     data-topic-view-id="<?= (int) $topic["id"] ?>"
     data-topic-view-url="<?= htmlspecialchars($baseUri . "/api/track-view.php", ENT_QUOTES, "UTF-8") ?>"
     data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="topic-main topic-detail-main">

        <div class="topic-title-bar topic-header ui-panel__head">

            <div class="topic-title-bar-text">

                <h1><?= htmlspecialchars($topic["title"]) ?></h1>

            </div>

        </div>



        <div class="topic-hero topic-first-image gallery-main">

            <?php $heroImage = getTopicPrimaryMediaPath($topic) ?? ""; ?>

            <?php if ($heroImage !== ""): ?>

                <img src="<?= htmlspecialchars(

                    strpos($heroImage, "http") === 0

                        ? $heroImage

                        : $baseUri . "/" . ltrim($heroImage, "/"),

                ) ?>" alt="<?= htmlspecialchars($topicHeroImageAlt) ?>" title="<?= htmlspecialchars($topicHeroImageTitle) ?>" loading="eager" fetchpriority="high" decoding="async" width="1200" height="675">
            <?php endif; ?>
</div>



        <section class="topic-section topic-descriptions ui-section" aria-labelledby="desc-heading">

            <h2 id="desc-heading">Açıklama</h2>

            <div class="topic-content topic-detail-content ui-section">

                <?= sanitizeTopicHtml(

                    $cleanTopicDescription,

                ) ?>

            </div>

        </section>



        <?php if ($topicDetailShowMedia): ?>

        <section class="topic-section topic-images-videos ui-section" aria-labelledby="media-heading">

            <h2 id="media-heading">Resim ve Videolar</h2>

            <?php

            $mediaLinks = getTopicMediaGallery($pdo, (int) ($topic["id"] ?? 0));

            $slides = [];

            $others = [];

            foreach ($mediaLinks as $url) {

                if (

                    preg_match(

                        '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i',

                        $url,

                        $ytMatch,

                    )

                ) {

                    $slides[] = [

                        "type" => "youtube",

                        "id" => $ytMatch[1],

                        "thumb" =>

                            "https://img.youtube.com/vi/" .

                            $ytMatch[1] .

                            "/mqdefault.jpg",

                    ];

                } elseif (

                    preg_match("/vimeo\.com\/(?:.*\/)?(\d+)/i", $url, $vimMatch)

                ) {

                    $slides[] = [

                        "type" => "vimeo",

                        "id" => $vimMatch[1],

                        "thumb" => "",

                    ];

                } elseif (preg_match('/\.(mp4|webm|ogg)$/i', $url)) {

                    $slides[] = [

                        "type" => "video",

                        "url" => $url,

                        "thumb" => "",

                    ];

                } elseif (

                    preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url)

                ) {

                    $src =

                        strpos($url, "http") === 0

                            ? $url

                            : $baseUri . "/" . ltrim($url, "/");

                    $slides[] = [

                        "type" => "image",

                        "url" => $src,

                        "thumb" => $src,

                    ];

                } else {

                    $others[] = $url;

                }

            }

            ?>



            <?php if (!empty($slides)): ?>

                <div class="topic-carousel" data-topic-carousel-slides="<?= htmlspecialchars(

                    json_encode($slides, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),

                    ENT_QUOTES,

                    'UTF-8'

                ) ?>">

                    <!-- Büyük Görüntüleyici -->

                    <div class="topic-carousel-main">

                        <button id="ui-comment-prev" class="topic-carousel-nav topic-carousel-nav-prev" type="button" aria-label="Onceki medya"><i class="bi bi-chevron-left"></i></button>

                        <button id="ui-comment-next" class="topic-carousel-nav topic-carousel-nav-next" type="button" aria-label="Sonraki medya"><i class="bi bi-chevron-right"></i></button>



                        <div id="ui-comment-content" class="topic-carousel-content ui-section">

                            <!-- JS ile doldurulacak -->

                        </div>

                        <div class="topic-carousel-counter" id="tcCounter" aria-live="polite">1 / <?= count($slides) ?></div>

                    </div>



                    <!-- Küçük Önizlemeler -->

                    <?php if (count($slides) > 1): ?>

                    <div class="topic-carousel-thumbs" aria-label="Galeri onizlemeleri">

                        <?php foreach ($slides as $idx => $slide): ?>

                            <button type="button" class="ui-comment-thumb<?= $idx === 0 ? ' active' : '' ?>" data-idx="<?= $idx ?>" aria-label="Galeri gorseli <?= $idx + 1 ?>" <?= $idx === 0 ? 'aria-current="true"' : '' ?>>

                                <?php if (

                                    $slide["type"] === "image" ||

                                    $slide["type"] === "youtube"

                                ): ?>

                                    <img src="<?= htmlspecialchars(
                                        $slide["thumb"],
                                    ) ?>" alt="" title="Galeri gorseli <?= $idx + 1 ?>" loading="lazy" decoding="async" width="90" height="60">
                                <?php endif; ?>
</button>

                        <?php endforeach; ?>

                    </div>

                    <?php endif; ?>

                </div>



                <script src="<?= asset_url("assets/js/topic-carousel.js", $baseUri) ?>" defer></script>

            <?php endif; ?>



            <?php if (!empty($others)): ?>

                <div class="topic-other-links mt-3">

                    <?php foreach ($others as $othUrl): ?>

                        <div class="topic-media-item topic-media-item-inline">

                            <a href="<?= htmlspecialchars(

                                $othUrl,

                            ) ?>" target="_blank" rel="noopener" class="topic-media-link-inline"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars(

    basename($othUrl),

) ?></a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>



            <?php if (empty($slides) && empty($others)): ?>

                <div class="topic-media-grid ui-grid">

                    <div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div>

                    <div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div>

                    <div class="topic-media-placeholder"><i class="bi bi-play-circle" aria-hidden="true"></i></div>

                </div>

            <?php endif; ?>

        </section>

        <?php endif; ?>



        <?php if ($topicDetailShowInfoPanel): ?>

        <section class="topic-section topic-details ui-section" aria-labelledby="content-info-heading">

            <h2 id="content-info-heading">İçerik Bilgileri</h2>

            <div class="topic-info-grid ui-grid">

                <?php if ($_showAuthor): ?>

                <div class="topic-info-row">

                    <i class="bi bi-person-badge"></i>

                    <span>Konu Sahibi</span>

                    <strong<?= $topicInfoValueAttrs((string) ($topic["author"] ?? "Anonim")) ?>>

                        <?php if (!empty($topic["author_id"])): ?>

                            <a href="<?= htmlspecialchars(

                                publicProfileUrl([

                                    "id" => (int) $topic["author_id"],

                                    "name" =>

                                        (string) ($topic["author"] ??

                                            "Anonim"),

                                ]),

                            ) ?>"><?= htmlspecialchars(

    (string) ($topic["author"] ?? "Anonim"),

) ?></a>
</strong>

                </div>

                <?php endif; ?>



                <div class="topic-info-row">

                    <i class="bi bi-calendar3"></i>

                    <span>Yayın Tarihi</span>

                    <strong<?= $topicInfoValueAttrs($published) ?>><?= htmlspecialchars(

                        $published,

                    ) ?></strong>

                </div>



                <?php if (

                    !empty($topic["author_topic"]) ||

                    !empty($topic["topic_version"])

                ): ?>

                <div class="topic-info-row">

                    <i class="bi bi-tools"></i>

                    <span>Mod Yapımcısı</span>

                    <strong<?= $topicInfoValueAttrs((string) ($topic["author_topic"] ?? "" ?: "-")) ?>><?= htmlspecialchars(

                        (string) ($topic["author_topic"] ?? "" ?: "-"),

                    ) ?></strong>

                </div>

                <div class="topic-info-row">

                    <i class="bi bi-controller"></i>

                    <span>Oyun Sürümü</span>

                    <strong<?= $topicInfoValueAttrs((string) ($topic["topic_version"] ?? "" ?: "-")) ?>><?= htmlspecialchars(

                        (string) ($topic["topic_version"] ?? "" ?: "-"),

                    ) ?></strong>

                </div>

                <?php endif; ?>



                <div class="topic-info-row">

                    <i class="bi bi-folder2-open"></i>

                    <span>Kategori</span>

                    <strong<?= $topicInfoValueAttrs($category) ?>><a href="<?= categoryUrl(

                        $categorySlug,

                    ) ?>"><?= htmlspecialchars($category) ?></a></strong>

                </div>



                <?php if ($_showViews && $views > 0): ?>

                <div class="topic-info-row">

                    <i class="bi bi-eye"></i>

                    <span>Görüntülenme</span>

                    <strong<?= $topicInfoValueAttrs(number_format($views, 0, ",", ".")) ?>><?= number_format(

                        $views,

                        0,

                        ",",

                        ".",

                    ) ?></strong>

                </div>

                <?php endif; ?>

            </div>

        </section>

        <?php endif; ?>

        <?php endif; ?>



        <?php
        $reporterName = trim((string) ($_SESSION["_auth_user_name"] ?? ""));
        $reporterEmail = trim((string) ($_SESSION["_auth_user_email"] ?? ""));
        $reporterReadonlyAttrs = $isLoggedIn ? ' readonly aria-readonly="true"' : '';
        ?>

        <div class="topic-report-modal" id="topicReportModal" role="dialog" aria-modal="true" aria-labelledby="report-heading" hidden aria-hidden="true">

            <div class="topic-report-backdrop" data-report-modal-close data-ui-modal-close></div>

            <div class="topic-report-dialog ui-panel">

            <div class="topic-report-header ui-panel__head">

                <h2 id="report-heading"><i class="bi bi-flag"></i> İçeriği Raporla</h2>

                <button type="button" class="topic-report-close" data-report-modal-close data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>

            </div>
<form class="topic-report-form" action="<?= $baseUri ?>/api/reports.php" method="post">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="create">

                <input type="hidden" name="topic_id" value="<?= (int) $topic["id"] ?>">

                <div class="topic-report-grid topic-report-grid--identity ui-grid">

                    <label class="topic-report-field">

                        <span>Ad Soyad</span>

                        <input type="text" name="reporter_name" value="<?= htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ad soyad" maxlength="255" required<?= $reporterReadonlyAttrs ?>>

                    </label>

                    <label class="topic-report-field">

                        <span>E-posta</span>

                        <input type="email" name="reporter_email" value="<?= htmlspecialchars($reporterEmail, ENT_QUOTES, 'UTF-8') ?>" placeholder="E-posta" maxlength="255" required<?= $reporterReadonlyAttrs ?>>

                    </label>

                    <label class="topic-report-field topic-report-field--full">

                        <span>Neden</span>

                        <select name="reason" required>

                            <?php foreach ((function_exists('topicReportReasonLabels') ? topicReportReasonLabels() : ['other' => 'Diğer']) as $value => $label): ?>

                                <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>

                            <?php endforeach; ?>

                        </select>

                    </label>

                    <label class="topic-report-field topic-report-field--full">

                        <span>Detay</span>

                        <textarea name="details" rows="3" maxlength="1000" placeholder="Ek bilgi varsa yazın"></textarea>

                    </label>

                </div>

                <button type="submit" class="topic-report-submit" data-loading-label="Gönderiliyor..."><i class="bi bi-send"></i> Rapor Gönder</button>

                <div class="topic-report-feedback" aria-live="polite"></div>

            </form>
</div>

        </div>



        <script src="<?= asset_url("assets/js/topic-report.js", $baseUri) ?>" defer></script>



        <?php

        $downloadLinks = getTopicDownloadLinks($pdo, (int) $topic['id']);

        $downloadCountdownSeconds = max(0, (int) ($settings['download_countdown_seconds'] ?? 5));

        $downloadReadyText = trim((string) ($settings['download_ready_text'] ?? 'İndirmek için tıklayınız')) ?: 'İndirmek için tıklayınız';
        $downloadWaitText = trim((string) ($settings['download_wait_text'] ?? 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz')) ?: 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz';
        $downloadDoneText = trim((string) ($settings['download_done_text'] ?? 'İndirme linkiniz hazır, indirmek için tıklayın')) ?: 'İndirme linkiniz hazır, indirmek için tıklayın';
        $downloadSecurityNoticeDefault = 'İndirme bağlantısı açılmadan önce kısa bir güvenlik beklemesi uygulanır. Hedef alan adını kontrol edip dış bağlantı onay ekranından devam edebilirsiniz.';
        $downloadSecurityNoticeText = trim((string) ($settings['download_security_notice_text'] ?? $downloadSecurityNoticeDefault)) ?: $downloadSecurityNoticeDefault;
        $downloadShowCounts = (string) ($settings['download_show_counts'] ?? '1') === '1';



        $downloadAccessState = function_exists('topicDownloadAccessState')

            ? topicDownloadAccessState($pdo, $settings, (int) ($topic['id'] ?? 0), (int) $currentUserId)

            : ['locked' => false, 'reason' => 'none', 'message' => '', 'mode' => 'public'];

        $downloadLocked = !empty($downloadAccessState['locked']);

        $downloadLockReason = trim((string) ($downloadAccessState['reason'] ?? 'none')) ?: 'none';

        $downloadLockMessage = trim((string) ($downloadAccessState['message'] ?? ''));

        $downloadLockButtonText = trim((string) ($settings['download_access_locked_button_text'] ?? 'Kilidi Ac')) ?: 'Kilidi Ac';

        $downloadCommentCtaLabel = trim((string) ($settings['download_access_comment_cta_label'] ?? 'Yorumlara Git')) ?: 'Yorumlara Git';

        $downloadOpenAuthPopup = (string) ($settings['download_access_open_auth_popup'] ?? '1') === '1';

        $downloadFocusCommentForm = (string) ($settings['download_access_focus_comment_form'] ?? '1') === '1';

        $downloadUnlockAfterAuth = (string) ($settings['download_access_unlock_after_auth'] ?? '1') === '1';

        $downloadUnlockAfterComment = (string) ($settings['download_access_unlock_after_comment'] ?? '1') === '1';

        $downloadAuthModalTitle = trim((string) ($settings['download_access_auth_modal_title'] ?? 'İndirme linklerini açmak için giriş yapın')) ?: 'İndirme linklerini açmak için giriş yapın';
        $downloadAuthLoginLabel = trim((string) ($settings['download_access_auth_login_label'] ?? 'Giriş Yap')) ?: 'Giriş Yap';
        $downloadAuthRegisterLabel = trim((string) ($settings['download_access_auth_register_label'] ?? 'Kayıt Ol')) ?: 'Kayıt Ol';
        $downloadAuthSuccessMessage = trim((string) ($settings['download_access_auth_success_message'] ?? 'Oturum basariyla acildi. Kilitli indirme kartlari guncelleniyor.')) ?: 'Oturum basariyla acildi. Kilitli indirme kartlari guncelleniyor.';
        $downloadSuccessNoticeEnabled = (string) ($settings['download_access_success_notice_enabled'] ?? '1') === '1';
        $downloadSuccessMessage = trim((string) ($settings['download_access_success_message'] ?? 'Tüm erişim şartlarını tamamladınız. İndirme bağlantıları kullanıma hazır.')) ?: 'Tüm erişim şartlarını tamamladınız. İndirme bağlantıları kullanıma hazır.';
        $downloadProgressEnabled = (string) ($settings['download_access_progress_enabled'] ?? '1') === '1';
        $downloadCommentTitle = trim((string) ($settings['download_access_comment_title'] ?? 'Yorum gerekli')) ?: 'Yorum gerekli';
        $downloadProgressTemplate = function_exists('topicDownloadProgressTemplate')
            ? topicDownloadProgressTemplate($settings)
            : '{{completed}} adımdan {{total}} adımı tamamlandı';
        $downloadSuccessAnimationEnabled = (string) ($settings['download_access_success_animation_enabled'] ?? '1') === '1';
        $downloadSuccessAutoCompact = (string) ($settings['download_access_success_auto_compact'] ?? '1') === '1';
        $downloadSuccessCompactDelay = max(0, min(60, (int) ($settings['download_access_success_compact_delay'] ?? 5)));
        $downloadHighlightFirstCard = (string) ($settings['download_access_highlight_first_card'] ?? '1') === '1';
        $downloadPendingMessage = trim((string) ($settings['download_access_pending_message'] ?? 'Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.')) ?: 'Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.';
        $downloadPendingButtonText = trim((string) ($settings['download_access_pending_button_text'] ?? 'Onay Bekleniyor')) ?: 'Onay Bekleniyor';
        $downloadExpiredTitle = trim((string) ($settings['download_access_expired_title'] ?? 'Yorum erişim süreniz doldu')) ?: 'Yorum erişim süreniz doldu';
        $downloadExpiredMessage = trim((string) ($settings['download_access_expired_message'] ?? 'İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.')) ?: 'İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.';
        $downloadAccessUntilText = trim((string) ($downloadAccessState['access_until_text'] ?? ''));
        $downloadAccessExpiresAt = trim((string) ($downloadAccessState['expires_at'] ?? ''));
        $downloadCommentStepRequired = !empty($downloadAccessState['comment_step_required']);
        $downloadLoginUrl = routePublicStaticUrl('login');

        $downloadRegisterUrl = routePublicStaticUrl('register');
        if (function_exists('loginSafeRedirect') && function_exists('authUrlWithRedirect')) {
            $downloadRedirect = loginSafeRedirect((string) ($_SERVER['REQUEST_URI'] ?? ($baseUri . '/index.php')), $baseUri . '/index.php');
            $downloadLoginUrl = authUrlWithRedirect($downloadLoginUrl, $downloadRedirect, $baseUri . '/index.php');
            $downloadRegisterUrl = authUrlWithRedirect($downloadRegisterUrl, $downloadRedirect, $baseUri . '/index.php');
        }

        $downloadStatusApi = rtrim($baseUri, '/') . '/api/download-access.php';

        $downloadAuthApi = rtrim($baseUri, '/') . '/api/auth-popup.php';

        $downloadTopicId = (int) ($topic['id'] ?? 0);

        $downloadSectionLockMessage = $downloadLockMessage !== ''
            ? $downloadLockMessage
            : match ($downloadLockReason) {
                'comment_required' => 'İndirme linklerini görmek için önce yorum yapmanız gerekir.',
                'comment_pending' => $downloadPendingMessage,
                'comment_expired' => $downloadExpiredMessage,
                default => 'Bu içeriği görmek için kayıt olmanız veya giriş yapmanız gerekir.',
            };
        $downloadAccessStage = function_exists('topicDownloadAccessStage')
            ? topicDownloadAccessStage($downloadLocked, $downloadLockReason)
            : ($downloadLocked
                ? ($downloadLockReason === 'comment_required' ? 'comment' : 'login')
                : 'open');
        $downloadProgressCompleted = max(0, (int) ($downloadAccessState['progress_completed'] ?? 0));
        $downloadProgressTotal = max(0, (int) ($downloadAccessState['progress_total'] ?? 0));
        $downloadProgressText = $downloadProgressTotal > 0
            ? (function_exists('topicDownloadProgressText')
                ? topicDownloadProgressText($settings, $downloadProgressCompleted, $downloadProgressTotal)
                : str_replace(['{{completed}}', '{{total}}'], [(string) $downloadProgressCompleted, (string) $downloadProgressTotal], $downloadProgressTemplate))
            : '';
        $downloadAccessStepClasses = function_exists('topicDownloadAccessStepClasses')
            ? topicDownloadAccessStepClasses($downloadAccessStage, $downloadCommentStepRequired)
            : [
                'login' => $downloadAccessStage === 'login' ? 'is-active' : ($downloadAccessStage === 'open' ? 'is-complete' : 'is-pending'),
                'comment' => !$downloadCommentStepRequired ? 'is-muted' : ($downloadAccessStage === 'comment' ? 'is-active' : ($downloadAccessStage === 'open' ? 'is-complete' : 'is-pending')),
                'open' => $downloadAccessStage === 'open' ? 'is-active' : 'is-pending',
            ];
        $downloadAccessMode = trim((string) ($downloadAccessState['mode'] ?? 'public')) ?: 'public';
        $downloadAccessSuccess = !$downloadLocked && $downloadAccessMode !== 'public' && $downloadSuccessNoticeEnabled;
        $downloadShowAccessNotice = $downloadLocked || $downloadAccessSuccess;
        $downloadAccessNoticeMessage = $downloadAccessSuccess ? $downloadSuccessMessage : $downloadSectionLockMessage;
        if ($downloadLockReason === 'comment_pending') {
            $downloadAccessNoticeMessage = $downloadPendingMessage;
        }
        if ($downloadLockReason === 'comment_expired') {
            $downloadAccessNoticeMessage = $downloadExpiredMessage;
        }
        $downloadAccessNoticeTitle = $downloadAccessSuccess
            ? 'İndirmeye hazırsınız'
            : match ($downloadLockReason) {
                'comment_required' => $downloadCommentTitle,
                'comment_pending' => 'Yorum onayı bekleniyor',
                'comment_expired' => $downloadExpiredTitle,
                'auth_required' => 'Giriş gerekli',
                default => 'İndirme erişimi kısıtlı',
            };
        if ($downloadAccessSuccess) {
            $downloadAccessStepClasses = [
                'login' => 'is-complete',
                'comment' => $downloadCommentStepRequired ? 'is-complete' : 'is-muted',
                'open' => 'is-complete',
            ];
        }
        $downloadTopicUrl = topicUrl((string) ($topic['slug'] ?? ''), $downloadTopicId);
        $downloadCommentTarget = $downloadTopicUrl . '#comments-heading';
        $downloadCurrentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');


        if ($topicDetailShowDownloadPanel && !empty($downloadLinks)): ?>



        <section class="topic-section topic-downloads topic-download-links ui-section" aria-labelledby="dl-heading">



            <h2 id="dl-heading">İndirme Bağlantıları</h2>


            <div class="topic-dl-trust" role="note">



                <i class="bi bi-shield-check" aria-hidden="true"></i>



                <span><?= htmlspecialchars($downloadSecurityNoticeText, ENT_QUOTES, 'UTF-8') ?></span>


            </div>



            <?php if ($downloadShowAccessNotice): ?>

            <div class="topic-dl-access-notice<?= $downloadAccessSuccess ? ' is-success' : '' ?>" data-download-lock-notice data-download-stage="<?= htmlspecialchars($downloadAccessStage, ENT_QUOTES, 'UTF-8') ?>" role="status" aria-live="polite">

                <i class="bi <?= $downloadAccessSuccess ? 'bi-check-circle-fill' : ($downloadLockReason === 'comment_pending' ? 'bi-hourglass-split' : ($downloadLockReason === 'comment_expired' ? 'bi-clock-history' : 'bi-lock-fill')) ?>" aria-hidden="true"></i>

                <div class="topic-dl-access-notice__body">
                    <strong class="topic-dl-access-notice__title"><?= htmlspecialchars($downloadAccessNoticeTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="topic-dl-access-notice__text"><?= htmlspecialchars($downloadAccessNoticeMessage, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="topic-dl-access-until" data-download-access-until<?= $downloadAccessUntilText === '' ? ' hidden' : '' ?>><?= htmlspecialchars($downloadAccessUntilText, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($downloadProgressEnabled && $downloadProgressText !== ''): ?>
                    <span class="topic-dl-access-progress" data-download-progress aria-label="<?= htmlspecialchars($downloadProgressText, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($downloadProgressText, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <div class="topic-dl-access-steps" aria-label="İndirme kilidi adımları">
                        <span class="topic-dl-access-step <?= htmlspecialchars($downloadAccessStepClasses['login'] ?? 'is-pending', ENT_QUOTES, 'UTF-8') ?>" data-download-step="login" title="Giriş yap"><i class="bi bi-1-circle-fill" aria-hidden="true"></i><span>Giriş</span></span>
                        <?php if ($downloadCommentStepRequired): ?>
                        <span class="topic-dl-access-step <?= htmlspecialchars($downloadAccessStepClasses['comment'] ?? 'is-pending', ENT_QUOTES, 'UTF-8') ?>" data-download-step="comment" title="Yorum gönder"><i class="bi bi-2-circle-fill" aria-hidden="true"></i><span>Yorum</span></span>
                        <?php endif; ?>
                        <span class="topic-dl-access-step <?= htmlspecialchars($downloadAccessStepClasses['open'] ?? 'is-pending', ENT_QUOTES, 'UTF-8') ?>" data-download-step="open" title="Bağlantıyı aç"><i class="bi <?= $downloadCommentStepRequired ? 'bi-3-circle-fill' : 'bi-2-circle-fill' ?>" aria-hidden="true"></i><span>Aç</span></span>
                    </div>
                </div>

            </div>

            <?php endif; ?>


            <div class="topic-dl-section ui-section"



                 data-topic-id="<?= $downloadTopicId ?>"



                 data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"



                 data-countdown-seconds="<?= $downloadCountdownSeconds ?>"



                 data-wait-text="<?= htmlspecialchars($downloadWaitText, ENT_QUOTES, 'UTF-8') ?>"



                 data-done-text="<?= htmlspecialchars($downloadDoneText, ENT_QUOTES, 'UTF-8') ?>"



                 data-status-api="<?= htmlspecialchars($downloadStatusApi, ENT_QUOTES, 'UTF-8') ?>"



                 data-auth-api="<?= htmlspecialchars($downloadAuthApi, ENT_QUOTES, 'UTF-8') ?>"



                 data-login-url="<?= htmlspecialchars($downloadLoginUrl, ENT_QUOTES, 'UTF-8') ?>"



                 data-register-url="<?= htmlspecialchars($downloadRegisterUrl, ENT_QUOTES, 'UTF-8') ?>"



                 data-comment-target="<?= htmlspecialchars($downloadCommentTarget, ENT_QUOTES, 'UTF-8') ?>"

                 data-current-request-uri="<?= htmlspecialchars($downloadCurrentRequestUri, ENT_QUOTES, 'UTF-8') ?>"

                 data-access-mode="<?= htmlspecialchars($downloadAccessMode, ENT_QUOTES, 'UTF-8') ?>"

                 data-success-notice-enabled="<?= $downloadSuccessNoticeEnabled ? '1' : '0' ?>"

                 data-success-message="<?= htmlspecialchars($downloadSuccessMessage, ENT_QUOTES, 'UTF-8') ?>"

                 data-progress-enabled="<?= $downloadProgressEnabled ? '1' : '0' ?>"

                 data-comment-title="<?= htmlspecialchars($downloadCommentTitle, ENT_QUOTES, 'UTF-8') ?>"

                 data-progress-template="<?= htmlspecialchars($downloadProgressTemplate, ENT_QUOTES, 'UTF-8') ?>"

                 data-progress-completed="<?= $downloadProgressCompleted ?>"

                 data-progress-total="<?= $downloadProgressTotal ?>"

                 data-comment-step-required="<?= $downloadCommentStepRequired ? '1' : '0' ?>"

                 data-success-animation-enabled="<?= $downloadSuccessAnimationEnabled ? '1' : '0' ?>"

                 data-success-auto-compact="<?= $downloadSuccessAutoCompact ? '1' : '0' ?>"

                 data-success-compact-delay="<?= $downloadSuccessCompactDelay ?>"

                 data-highlight-first-card="<?= $downloadHighlightFirstCard ? '1' : '0' ?>"

                 data-pending-message="<?= htmlspecialchars($downloadPendingMessage, ENT_QUOTES, 'UTF-8') ?>"

                 data-pending-button-text="<?= htmlspecialchars($downloadPendingButtonText, ENT_QUOTES, 'UTF-8') ?>"

                 data-expired-title="<?= htmlspecialchars($downloadExpiredTitle, ENT_QUOTES, 'UTF-8') ?>"

                 data-expired-message="<?= htmlspecialchars($downloadExpiredMessage, ENT_QUOTES, 'UTF-8') ?>"

                 data-access-until-text="<?= htmlspecialchars($downloadAccessUntilText, ENT_QUOTES, 'UTF-8') ?>"

                 data-access-expires-at="<?= htmlspecialchars($downloadAccessExpiresAt, ENT_QUOTES, 'UTF-8') ?>"

                 data-download-stage="<?= htmlspecialchars($downloadAccessStage, ENT_QUOTES, 'UTF-8') ?>"

                 data-locked="<?= $downloadLocked ? '1' : '0' ?>"


                 data-lock-reason="<?= htmlspecialchars($downloadLockReason, ENT_QUOTES, 'UTF-8') ?>"



                 data-lock-message="<?= htmlspecialchars($downloadSectionLockMessage, ENT_QUOTES, 'UTF-8') ?>"



                 data-lock-button-text="<?= htmlspecialchars($downloadLockButtonText, ENT_QUOTES, 'UTF-8') ?>"



                 data-comment-cta-label="<?= htmlspecialchars($downloadCommentCtaLabel, ENT_QUOTES, 'UTF-8') ?>"



                 data-open-auth-popup="<?= $downloadOpenAuthPopup ? '1' : '0' ?>"



                 data-focus-comment-form="<?= $downloadFocusCommentForm ? '1' : '0' ?>"



                 data-unlock-after-auth="<?= $downloadUnlockAfterAuth ? '1' : '0' ?>"



                 data-unlock-after-comment="<?= $downloadUnlockAfterComment ? '1' : '0' ?>"



                 data-auth-modal-title="<?= htmlspecialchars($downloadAuthModalTitle, ENT_QUOTES, 'UTF-8') ?>"



                 data-auth-login-label="<?= htmlspecialchars($downloadAuthLoginLabel, ENT_QUOTES, 'UTF-8') ?>"



                 data-auth-register-label="<?= htmlspecialchars($downloadAuthRegisterLabel, ENT_QUOTES, 'UTF-8') ?>"



                 data-auth-success-message="<?= htmlspecialchars($downloadAuthSuccessMessage, ENT_QUOTES, 'UTF-8') ?>">



                <div class="download-grid topic-dl-grid ui-grid">



                    <?php foreach ($downloadLinks as $dl):



                        $dlId = (int) ($dl['id'] ?? 0);

                        $dlName = trim((string) ($dl['name'] ?? ''));

                        if (!mb_check_encoding($dlName, 'UTF-8')) {

                            $dlName = mb_convert_encoding($dlName, 'UTF-8', 'ISO-8859-9');

                        }

                        if ($dlName === '') {

                $dlName = 'İndirme Linki';
                        }

                        $dlUrl = trim((string) ($dl['url'] ?? ''));

                        $dlCount = (int) ($dl['download_count'] ?? 0);

                        if ($dlUrl === '') {

                            continue;

                        }

                                                $dlHref = $dlId > 0
                            ? (function_exists('topicDownloadBuildActionUrl')
                                ? topicDownloadBuildActionUrl($dlId, $downloadTopicId)
                                : routePublicStaticUrl('download') . '?id=' . $dlId)
                            : $dlUrl;
                        $cardLocked = $downloadLocked;

                        $cardLockReason = $cardLocked ? $downloadLockReason : 'none';

                        $cardLockMessage = $cardLocked ? $downloadSectionLockMessage : '';

                        $cardButtonText = $downloadReadyText;

                        if ($cardLocked) {
                            $cardButtonText = match ($cardLockReason) {
                                'comment_required' => $downloadCommentCtaLabel,
                                'comment_expired' => $downloadCommentCtaLabel,
                                'comment_pending' => $downloadPendingButtonText,
                                default => $downloadLockButtonText,
                            };
                        }

                        $cardHref = $cardLocked ? '#' : $dlHref;

                        ?>



                    <a href="<?= htmlspecialchars($cardHref, ENT_QUOTES, 'UTF-8') ?>"

                       rel="noopener"

                       class="download-card topic-dl-card ui-card<?= $cardLocked ? ' is-locked' : '' ?>"

                       data-download-href="<?= htmlspecialchars($dlHref, ENT_QUOTES, 'UTF-8') ?>"

                       data-ready-text="<?= htmlspecialchars($downloadReadyText, ENT_QUOTES, 'UTF-8') ?>"

                       data-locked="<?= $cardLocked ? '1' : '0' ?>"

                       data-lock-reason="<?= htmlspecialchars($cardLockReason, ENT_QUOTES, 'UTF-8') ?>"

                       data-lock-message="<?= htmlspecialchars($cardLockMessage, ENT_QUOTES, 'UTF-8') ?>"

                       data-locked-button-text="<?= htmlspecialchars($downloadLockButtonText, ENT_QUOTES, 'UTF-8') ?>"

                       data-comment-cta-label="<?= htmlspecialchars($downloadCommentCtaLabel, ENT_QUOTES, 'UTF-8') ?>"

                       aria-disabled="<?= $cardLocked ? 'true' : 'false' ?>">



                        <div class="download-icon topic-dl-icon"><i class="bi <?= $cardLocked ? ($cardLockReason === 'comment_pending' ? 'bi-hourglass-split' : ($cardLockReason === 'comment_expired' ? 'bi-clock-history' : 'bi-lock-fill')) : 'bi-cloud-arrow-down' ?>" aria-hidden="true"></i></div>


                        <div class="download-info topic-dl-info">



                            <strong><?= htmlspecialchars($dlName, ENT_QUOTES, 'UTF-8') ?></strong>



                            <small><?= htmlspecialchars((string) (parse_url($dlUrl, PHP_URL_HOST) ?: $dlUrl), ENT_QUOTES, 'UTF-8') ?></small>



                            <?php if ($cardLocked): ?>



                            <small class="topic-dl-lock-message"><?= htmlspecialchars($cardLockMessage, ENT_QUOTES, 'UTF-8') ?></small>



                            <?php endif; ?>



                            <?php if ($downloadShowCounts): ?>



                            <span class="download-count topic-dl-count"><i class="bi bi-download"></i> <?= number_format($dlCount, 0, ',', '.') ?> indirme</span>



                            <?php endif; ?>



                        </div>



                        <span class="download-btn topic-dl-button">



                            <span class="topic-dl-spinner"></span>



                            <span class="topic-dl-action"><?= htmlspecialchars($cardButtonText, ENT_QUOTES, 'UTF-8') ?></span>



                        </span>



                    </a>



                    <?php endforeach; ?>



                </div>



            </div>



            <script src="<?= asset_url('assets/js/topic-downloads.js', $baseUri) ?>" defer></script>



        </section>



            <?php endif; ?><?php

$currentUserAvatar = '';

if ($isLoggedIn && !empty($_SESSION['_auth_user_id'])) {

    try {

        $currentUserId = (int) $_SESSION['_auth_user_id'];

        $avatarCache = $_SESSION['_auth_avatar_cache'] ?? null;

        $rawCurrentUserAvatar = '';

        if (

            is_array($avatarCache)

            && (int) ($avatarCache['uid'] ?? 0) === $currentUserId

            && (time() - (int) ($avatarCache['ts'] ?? 0)) <= 60

        ) {

            $rawCurrentUserAvatar = trim((string) ($avatarCache['raw'] ?? ''));

        } else {

            $avStmt = $pdo->prepare('SELECT avatar FROM users WHERE id = :id LIMIT 1');

            $avStmt->execute(['id' => $currentUserId]);

            $rawCurrentUserAvatar = trim((string) ($avStmt->fetchColumn() ?? ''));

            if (session_status() === PHP_SESSION_ACTIVE) {

                $_SESSION['_auth_avatar_cache'] = [

                    'uid' => $currentUserId,

                    'raw' => $rawCurrentUserAvatar,

                    'ts' => time(),

                ];

            }

        }

        $currentUserAvatar = function_exists('resolveAvatarUrl')

            ? resolveAvatarUrl($rawCurrentUserAvatar, $baseUri, true)

            : $rawCurrentUserAvatar;

    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }

}

$defaultAvatarUrl = function_exists('defaultAvatarUrl')

    ? defaultAvatarUrl($baseUri)

    : $baseUri . '/assets/images/noavatar-neon-helmet.svg';

$commentFormInfoText = trim((string) ($settings['comment_form_info_text'] ?? 'Spam, anlamsız veya tekrarlı yorumlar otomatik olarak engellenir. Lütfen konuya katkı sağlayan bir yorum yazın.'));

?>

<?php if ($topicDetailCommentsEnabled): ?>

         <section class="topic-section topic-comments ui-section" aria-labelledby="comments-heading"

                 data-topic-id="<?= (int) $topic["id"] ?>"

                 data-api="<?= $baseUri ?>/api/comments.php"

                 data-csrf="<?= htmlspecialchars(csrf_token()) ?>"

                 data-logged-in="<?= $isLoggedIn ? "1" : "0" ?>"

                 data-user-name="<?= htmlspecialchars(

                     $_SESSION["_auth_user_name"] ?? "",

                 ) ?>"

                 data-user-avatar="<?= htmlspecialchars($currentUserAvatar) ?>"

                 data-avatar-fallback="<?= htmlspecialchars($defaultAvatarUrl) ?>"

                 data-report-enabled="<?= ($settings['comment_report_enabled'] ?? '1') === '1' ? '1' : '0' ?>"

                 data-topic-author="<?= htmlspecialchars($topic['author'] ?? '') ?>"

                 data-poll="<?= (int) ($settings["comment_realtime_poll"] ??

                     15) ?>"

                 data-reactions-enabled="<?= ($settings['comment_reactions_enabled'] ?? '1') === '1' ? '1' : '0' ?>"

                 data-comments-markdown-enabled="<?= ($settings['comment_markdown_enabled'] ?? '1') === '1' ? '1' : '0' ?>"

                 data-comments-mentions-enabled="<?= ($settings['comment_mentions_enabled'] ?? '1') === '1' ? '1' : '0' ?>"

                 data-comments-edit-history-enabled="<?= ($settings['comment_edit_history'] ?? '1') === '1' ? '1' : '0' ?>"

                 data-comment-max-length="<?= (int) COMMENT_MAX_LENGTH ?>"

                 data-base-url="<?= htmlspecialchars($baseUri, ENT_QUOTES, 'UTF-8') ?>">



            <div class="ui-comment-header ui-comment-header--compact ui-panel__head">

                <h2 id="comments-heading" class="ui-comment-header__title">Yorumlar <span class="ui-comment-count" id="tcCount">(0)</span></h2>

                <div class="ui-comment-sort ui-comment-header__sort">

                    <span class="ui-comment-sort-label">Sırala:</span>

                    <select class="ui-comment-sort-select" id="tcSort">

                        <option value="asc">En Eski</option>

                        <option value="desc">En Yeni</option>

                        <option value="popular">Popüler</option>

                        <option value="liked">Beğenilenler</option>

                        <option value="disliked">Beğenilmeyenler</option>

                    </select>

                </div>

            </div>



            <!-- Yorum formu -->

            <?php if ($isLoggedIn): ?>

            <div class="ui-comment-form-wrap" id="tcFormWrap">

                <div class="ui-comment-form-avatar"><?php

                    if (function_exists('avatarImageHtml')):

                        echo avatarImageHtml((string) ($_SESSION["_auth_user_name"] ?? "U"), $currentUserAvatar, ['base_uri' => $baseUri, 'width' => 40, 'height' => 40]);

                    elseif (!empty($currentUserAvatar)):

                ?><img src="<?= htmlspecialchars($currentUserAvatar) ?>" alt="<?= htmlspecialchars($_SESSION["_auth_user_name"] ?? "U") ?>" title="<?= htmlspecialchars($_SESSION["_auth_user_name"] ?? "U") ?>" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($defaultAvatarUrl) ?>"><?php
                    else:
                ?><img src="<?= htmlspecialchars($defaultAvatarUrl) ?>" alt="<?= htmlspecialchars($_SESSION["_auth_user_name"] ?? "U") ?>" title="<?= htmlspecialchars($_SESSION["_auth_user_name"] ?? "U") ?>" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($defaultAvatarUrl) ?>"><?php
                    endif;
                ?></div>
                <div class="ui-comment-form-body ui-panel__body">

                    <textarea id="tcInput" class="ui-comment-textarea" placeholder="Düşüncelerini paylaş..." maxlength="<?= COMMENT_MAX_LENGTH ?>" rows="1"></textarea>

                    <div class="ui-comment-form-footer">

                        <?php if ($commentFormInfoText !== ''): ?>

                            <span class="ui-comment-form-info" role="note"><i class="bi bi-info-circle" aria-hidden="true"></i><span><?= htmlspecialchars($commentFormInfoText, ENT_QUOTES, 'UTF-8') ?></span></span>

                        <?php endif; ?>

                    <div class="ui-comment-form-actions is-hidden" id="tcActions">

                        <span class="ui-comment-char-count"><span id="tcCharCount">0</span>/<?= COMMENT_MAX_LENGTH ?></span>

                        <div class="ui-comment-form-btns">

                            <button type="button" class="ui-comment-btn-cancel" id="tcCancel">İptal</button>

                            <button type="button" class="ui-comment-btn-submit" id="tcSubmit" disabled>Gönder</button>

                        </div>

                    </div>

                    </div>

                </div>

            </div>
<!-- Alert Container is removed, handled dynamically via toast -->



            <!-- Yorum listesi -->

            <div class="ui-comment-list" id="tcList">

                <div class="ui-comment-loading" id="tcLoading">

                    <div class="ui-comment-skeleton">

                        <div class="ui-comment-skeleton-avatar"></div>

                        <div class="ui-comment-skeleton-body">

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--short"></div>

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--full"></div>

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--medium"></div>

                        </div>

                    </div>

                    <div class="ui-comment-skeleton ui-comment-skeleton--muted">

                        <div class="ui-comment-skeleton-avatar"></div>

                        <div class="ui-comment-skeleton-body">

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--compact"></div>

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--wide"></div>

                            <div class="ui-comment-skeleton-line ui-comment-skeleton-line--half"></div>

                        </div>

                    </div>

                </div>

            </div>



            <!-- Load more -->

            <div class="ui-comment-load-more-wrap is-hidden" id="tcLoadMoreWrap">

                <button type="button" class="ui-comment-load-more-btn" id="tcLoadMore">

                    Daha fazla yorum yükle

                </button>

            </div>

            <div class="ui-comment-pagination-info is-hidden" id="tcPaginationInfo"></div>

        </section>



        <script src="<?= asset_url("assets/js/topic-comments.js", $baseUri) ?>" defer></script>
<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
        <script src="<?= asset_url("assets/js/topic-view-track.js", $baseUri) ?>" defer></script>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>

    </div>



</div>



<?php require_once $projectRoot . "/includes/public-footer.php"; ?>


