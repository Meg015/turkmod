<?php

declare(strict_types=1);
/**
 * Topic card partial — reusable card component for topic listings.
 *
 * Expected variables (set before include):
 *   $item     — Topic row array (title, slug, category, category_slug, parent_slug, topic_descriptions, view_count, published_at, created_at, author, author_id, primary_media_path)
 *   $baseUri  — Application base URI
 */

$item = isset($item) && is_array($item) ? $item : [];
$baseUri = $baseUri ?? "";

// Load SEO settings for image alt text
$_seoSettings = $settings ?? (function_exists('getAdminSettings') && isset($pdo) ? getAdminSettings($pdo) : []);
$_showAuthor = ($_seoSettings['show_author_info'] ?? "1") === "1";
$_showViews = ($_seoSettings['show_view_count'] ?? "1") === "1";

$_cardCategory = (string) ($item["category"] ?? "Genel");
$_cardCategorySlug =
    (string) ($item["category_slug"] ?? strtolower($_cardCategory));
$_cardCategoryParentSlug =
    (string) ($item["parent_slug"] ??
        ($item["category_parent_slug"] ??
            ($item["parent_category_slug"] ?? "")));
$_cardSummary = mb_substr(
    strip_tags((string) ($item["topic_descriptions"] ?? "")),
    0,
    150,
);
$_cardViews = isset($item["view_count"]) ? (int) $item["view_count"] : 0;
$_cardViewsFormatted = number_format($_cardViews, 0, ",", ".");
$_cardAuthor = trim((string) ($item["author"] ?? "Admin"));
$_cardAuthorUrl = !empty($item["author_id"])
    ? publicProfileUrl([
        "id" => (int) $item["author_id"],
        "name" => $_cardAuthor !== "" ? $_cardAuthor : "Admin",
    ])
    : "";
$_cardPublished = function_exists("formatAppDate")
    ? formatAppDate((string) ($item["published_at"] ?? ($item["created_at"] ?? "now")), $pdo ?? null)
    : date("d M Y", strtotime((string) ($item["published_at"] ?? ($item["created_at"] ?? "now"))));
$_cardTitle = (string) ($item["title"] ?? "İçerik");
$_cardHref = topicUrlForRow($item);
$_cardImageMap = [
    "Design" => "portal-ui.svg",
    "Development" => "portal-code.svg",
    "Operations" => "portal-server.svg",
];
$_cardCover = $_cardImageMap[$_cardCategory] ?? "portal-pack.svg";
$_cardFallbackSrc = asset_url("assets/portal-pack.svg", $baseUri);
$_cardHero = getTopicPrimaryMediaPath($item);

// Generate SEO-friendly alt text
if (function_exists('seoGenerateImageAlt')) {
    $_cardImageAlt = seoGenerateImageAlt('topic-card', $_cardTitle, $_seoSettings);
} else {
    $_cardImageAlt = $_cardTitle . ' kapak görseli';
}
if (function_exists('seoGenerateImageTitle')) {
    $_cardImageTitle = seoGenerateImageTitle('topic-card', $_cardTitle, $_seoSettings);
} else {
    $_cardImageTitle = $_cardImageAlt;
}
$_cardThemeManager = $GLOBALS["themeManager"] ?? null;
if ($_cardThemeManager instanceof ThemeManager && $_cardThemeManager->usesPublicRenderer()) {
    $_cardImage = (string) ($_cardHero ?? "");
    if ($_cardImage !== "" && !preg_match('~^(https?:)?//|^data:|^/~i', $_cardImage)) {
        $_cardImage = rtrim($baseUri, "/") . "/" . ltrim($_cardImage, "/");
    }
    if ($_cardImage === "") {
        $_cardImage = asset_url("assets/" . $_cardCover, $baseUri);
    }
    echo $_cardThemeManager->render("partials.topic_card", [
        "topic" => [
            "url" => $_cardHref,
            "image" => $_cardImage,
            "image_alt" => $_cardImageAlt,
            "image_title" => $_cardImageTitle,
            "image_srcset" => "",
            "image_sizes" => "",
            "image_loading" => "lazy",
            "image_decoding" => "async",
            "image_fetchpriority" => "",
            "title" => $_cardTitle,
            "category" => $_cardCategory,
            "category_url" => categoryUrl($_cardCategorySlug, $_cardCategoryParentSlug),
            "date" => $_cardPublished,
            "excerpt" => $_cardSummary,
            "views" => $_cardViewsFormatted,
            "likes" => number_format((int) ($item["likes"] ?? 0), 0, ",", "."),
            "comments_count" => number_format((int) ($item["comment_count"] ?? $item["comments_count"] ?? 0), 0, ",", "."),
            "author" => $_cardAuthor !== "" ? $_cardAuthor : "Admin",
            "author_url" => $_cardAuthorUrl,
        ],
    ]);
    return;
}
?>
<article class="feed-card feed-card--list" data-topic-url="<?= htmlspecialchars($_cardHref, ENT_QUOTES, 'UTF-8') ?>">
    <a class="card__img topic-list-thumb" href="<?= htmlspecialchars($_cardHref, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($_cardHero && strpos((string) $_cardHero, "http") === 0): ?>
            <img src="<?= htmlspecialchars(
                (string) $_cardHero,
            ) ?>" alt="<?= htmlspecialchars($_cardImageAlt) ?>" title="<?= htmlspecialchars($_cardImageTitle) ?>" loading="lazy" decoding="async" width="640" height="360" data-fallback-src="<?= htmlspecialchars($_cardFallbackSrc) ?>">
        <?php elseif ($_cardHero): ?>
            <img src="<?= htmlspecialchars($baseUri . '/' . ltrim((string) $_cardHero, '/'), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($_cardImageAlt) ?>" title="<?= htmlspecialchars($_cardImageTitle) ?>" loading="lazy" decoding="async" width="640" height="360" data-fallback-src="<?= htmlspecialchars($_cardFallbackSrc) ?>">
        <?php else: ?>
            <img class="topic-list-fallback-image" src="<?= htmlspecialchars($baseUri . '/assets/' . $_cardCover, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($_cardImageAlt) ?>" title="<?= htmlspecialchars($_cardImageTitle) ?>" loading="lazy" decoding="async" width="640" height="360" data-fallback-src="<?= htmlspecialchars($_cardFallbackSrc) ?>">
        <?php endif; ?>
    </a>
    <div class="card__body topic-list-body ui-panel__body">
        <div class="topic-list-topline">
            <a href="<?= categoryUrl(
                $_cardCategorySlug,
                $_cardCategoryParentSlug,
            ) ?>" class="card__category topic-category"><?= htmlspecialchars(
    $_cardCategory,
) ?></a>
            <span class="topic-list-date"><i class="bi bi-calendar3" aria-hidden="true"></i><?= htmlspecialchars(
                $_cardPublished,
            ) ?></span>
        </div>
        <h3>
            <a href="<?= htmlspecialchars($_cardHref, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($_cardTitle) ?>
            </a>
        </h3>
        <p><?= htmlspecialchars($_cardSummary) ?></p>
        <div class="card__footer topic-list-bottom-row">
            <div class="card__meta topic-list-meta">
                <?php if ($_showAuthor): ?>
                    <?php if ($_cardAuthorUrl !== ""): ?>
                        <a href="<?= htmlspecialchars($_cardAuthorUrl) ?>"><i class="bi bi-person" aria-hidden="true"></i><?= htmlspecialchars(
                            $_cardAuthor !== "" ? $_cardAuthor : "Admin",
                        ) ?></a>
                    <?php else: ?>
                        <span><i class="bi bi-person" aria-hidden="true"></i><?= htmlspecialchars(
                            $_cardAuthor !== "" ? $_cardAuthor : "Admin",
                        ) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($_showViews): ?>
                <span class="topic-list-metric" title="Görüntülenme" aria-label="<?= htmlspecialchars($_cardViewsFormatted) ?> görüntülenme">
                    <i class="bi bi-eye" aria-hidden="true"></i><strong><?= $_cardViewsFormatted ?></strong><small>Görüntülenme</small>
                </span>
                <?php endif; ?>
            </div>
            <button class="btn-download topic-read-more" type="button" aria-label="<?= htmlspecialchars(
                $_cardTitle,
            ) ?> detay sayfasını aç" data-ui-href="<?= htmlspecialchars($_cardHref, ENT_QUOTES, 'UTF-8') ?>" data-ui-stop>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                <span class="topic-read-more-label">Devamını Oku</span>
            </button>
        </div>
    </div>
</article>
