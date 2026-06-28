<?php

declare(strict_types=1);

require_once $projectRoot . '/includes/init.php';
require_once $projectRoot . '/includes/src/Engine/Users/Legacy/profile-helpers.php';
require_once $projectRoot . '/includes/src/Engine/Seo/Legacy/helpers.php';

$profileSlug = trim((string) ($_GET['profile'] ?? $_GET['slug'] ?? ''));
$profileUserId = publicProfileIdFromSlug($profileSlug);

if ($profileUserId <= 0 && $pdo && $profileSlug !== '') {
    try {
        $fallbackStmt = $pdo->prepare("SELECT id FROM users WHERE deleted_at IS NULL AND status = 'active' AND public_profile = 1 AND name = :name LIMIT 1");
        $fallbackStmt->execute(['name' => $profileSlug]);
        $profileUserId = (int) ($fallbackStmt->fetchColumn() ?: 0);
    } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
}

if (!$pdo || $profileUserId <= 0) {
    http_response_code(404);
    $pageTitle = 'Profil Bulunamadı';
    require_once $projectRoot . '/includes/public-header.php';
    echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">Kullanıcı profili bulunamadı.</div>';
    require_once $projectRoot . '/includes/public-footer.php';
    exit;
}

profileEnsureColumns($pdo);
$user = profileGetUser($pdo, $profileUserId);

if (!$user || (string) ($user['status'] ?? 'active') !== 'active' || !empty($user['is_banned']) || (int) ($user['public_profile'] ?? 1) !== 1) {
    http_response_code(404);
    $pageTitle = 'Profil Bulunamadı';
    require_once $projectRoot . '/includes/public-header.php';
    echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">Kullanıcı profili bulunamadı.</div>';
    require_once $projectRoot . '/includes/public-footer.php';
    exit;
}

$canonicalSlug = publicProfileSlug((int) $user['id'], (string) $user['name']);
if ($profileSlug !== $canonicalSlug || routeRequestNeedsCanonicalRedirect('profile', $canonicalSlug)) {
    header('Location: ' . publicProfileUrl($user), true, 301);
    exit;
}

$stats = profileGetStatsReal($pdo, (int) $user['id']);
$commentCount = (int) ($stats['comments'] ?? 0);
$viewCount = (int) ($stats['views'] ?? 0);
$downloadCount = (int) ($stats['downloads'] ?? 0);
$showTopics = (int) ($user['public_show_topics'] ?? 1) === 1;
$showComments = (int) ($user['public_show_comments'] ?? 0) === 1;
$showSocials = (int) ($user['public_show_socials'] ?? 1) === 1;
$canReportProfile = $isLoggedIn && (int) ($_SESSION['_auth_user_id'] ?? 0) !== (int) $user['id'];
$publishedTopicCount = $showTopics ? profileCountPublishedTopics($pdo, (int) $user['id']) : 0;
$profileContext = profileBuildProfileContext($user, [
    'base_uri' => $baseUri,
    'bio' => (string) ($user['bio'] ?? ''),
    'location' => (string) ($user['location'] ?? ''),
    'website' => (string) ($user['website'] ?? ''),
    'social_github' => (string) ($user['social_github'] ?? ''),
    'social_twitter' => (string) ($user['social_twitter'] ?? ''),
    'social_discord' => (string) ($user['social_discord'] ?? ''),
    'social_links' => $showSocials ? profileBuildSocialLinks($user) : [],
    'topic_count' => $publishedTopicCount,
    'comment_count' => $showComments ? $commentCount : 'Gizli',
    'view_count' => $viewCount,
    'download_count' => $downloadCount,
    'stat_labels' => [
        'topics' => 'Yayın',
        'comments' => 'Yorum',
        'views' => 'Görüntülenme',
        'downloads' => 'İndirme',
    ],
    'can_report' => $canReportProfile,
    'cover' => (string) ($user['cover'] ?? $user['cover_image'] ?? ''),
]);
$topicsPerPage = 12;
$profileTotalPages = max(1, (int) ceil($publishedTopicCount / $topicsPerPage));
$profileCurrentPage = max(1, min($profileTotalPages, (int) ($_GET['page'] ?? 1)));
$profileTopicOffset = ($profileCurrentPage - 1) * $topicsPerPage;
$profileBaseUrl = publicProfileUrl($user);
$profilePageUrl = $profileCurrentPage > 1
    ? $profileBaseUrl . '?page=' . $profileCurrentPage
    : $profileBaseUrl;
$userTopics = $showTopics ? profileGetTopics($pdo, (int) $user['id'], $topicsPerPage, $profileTopicOffset) : [];
$publicCollections = $showTopics ? profileGetPublicCollections($pdo, (int) $user['id'], 6) : [];
$paginationHtml = $showTopics ? renderPagination($publishedTopicCount, $profileCurrentPage, $topicsPerPage, $profileBaseUrl) : '';
$pageTitle = (string) $user['name'] . ' Profili';
$metaDescription = (string) $user['name'] . ' profilini, paylaştığı modları ve topluluk istatistiklerini inceleyin.';

// SEO Integration
$settings = getAdminSettings($pdo);
$profileContext['show_leaderboard'] = ($settings['leaderboard_show_profile'] ?? '1') === '1';
$profileContext['leaderboard_user_id'] = (int) $user['id'];

// Meta tags
$isProfilePaginated = $profileCurrentPage > 1 || $profileTotalPages > 1;
$seoMetaTags = seoGenerateProfileMeta($user, $stats, $settings, $profilePageUrl, !$isProfilePaginated);

// Structured data
$seoStructuredData = seoGetProfileStructuredData($user, $stats, $settings);

// Pagination tags
$seoPaginationTags = '';
if ($profileCurrentPage > 1 || $profileTotalPages > 1) {
    $seoPaginationTags = seoGetPaginationTags(
        $profileCurrentPage,
        $profileTotalPages,
        $profileBaseUrl,
        $settings
    );
}

$profile_is_public = true;
$profile_is_private = false;
$profile_name = (string) ($profileContext['name'] ?? '');
$profile_email = (string) ($profileContext['email'] ?? '');
$profile_bio = (string) ($profileContext['bio'] ?? '');
$profile_avatar_url = (string) ($profileContext['avatar_url'] ?? '');
$profile_avatar_fallback = (string) ($profileContext['avatar_fallback'] ?? '');
$profile_has_avatar = !empty($profileContext['has_avatar']);
$profile_initials = (string) ($profileContext['initials'] ?? '');
$profile_group = (string) ($profileContext['group'] ?? '');
$profile_group_name = (string) ($profileContext['group_name'] ?? '');
$profile_group_slug = (string) ($profileContext['group_slug'] ?? '');
$profile_private_group = (string) ($profileContext['private_group'] ?? '');
$profile_private_role = (string) ($profileContext['private_role'] ?? '');
$profile_role_name = (string) ($profileContext['role_name'] ?? '');
$profile_member_since = (string) ($profileContext['member_since'] ?? '');
$profile_location = (string) ($profileContext['location'] ?? '');
$profile_has_location = !empty($profileContext['has_location']);
$profile_can_report = !empty($profileContext['can_report']);
$profile_website = (string) ($profileContext['website'] ?? '');
$profile_social_github = (string) ($profileContext['social_github'] ?? '');
$profile_social_twitter = (string) ($profileContext['social_twitter'] ?? '');
$profile_social_discord = (string) ($profileContext['social_discord'] ?? '');
$profile_social_links = is_array($profileContext['social_links'] ?? null) ? $profileContext['social_links'] : [];
$profile_has_social_links = !empty($profileContext['has_social_links']);
$profile_cover = (string) ($profileContext['cover'] ?? '');
$profile_id = (int) ($profileContext['id'] ?? $user['id'] ?? 0);
$profile_csrf_token = csrf_token();
$profile_report_endpoint = $baseUri . '/api/user-reports.php';
$profile_report_reasons = profileBuildReportReasonOptions();
$profile_show_topics = $showTopics;
$profile_topics_hidden = !$showTopics;
$profile_topics_empty = $showTopics && empty($userTopics);
$profile_topic_count_label = number_format($publishedTopicCount) . ' icerik';
$profile_page_summary = $profileTotalPages > 1 ? 'Sayfa ' . number_format($profileCurrentPage) . ' / ' . number_format($profileTotalPages) : '';
$profile_topics = [];
foreach ($userTopics as $index => $topic) {
    if (!is_array($topic)) {
        continue;
    }
    $topicTitle = (string) ($topic['title'] ?? 'Konu');
    $topicImageAlt = function_exists('seoGenerateImageAlt')
        ? seoGenerateImageAlt('topic-hero', $topicTitle, $settings)
        : $topicTitle . ' kapak görseli';
    $heroImage = (string) ($topic['hero_image'] ?? '');
    $heroImageUrl = function_exists('uiUrlValue') ? uiUrlValue($heroImage, $baseUri) : '';
    $profile_topics[] = [
        'rank' => str_pad((string) ($profileTopicOffset + $index + 1), 2, '0', STR_PAD_LEFT),
        'title' => $topicTitle,
        'url' => topicUrlForRow($topic),
        'image' => $heroImageUrl,
        'image_alt' => $topicImageAlt,
        'views' => number_format((int) ($topic['view_count'] ?? 0)),
        'downloads' => number_format((int) ($topic['download_count'] ?? 0)),
        'comments' => number_format((int) ($topic['comment_count'] ?? 0)),
        'category' => (string) ($topic['category'] ?? 'Genel'),
    ];
}
$profile_pagination_html = $paginationHtml;
$profile_public_collections = [];
foreach ($publicCollections as $collection) {
    if (!is_array($collection)) {
        continue;
    }
    $previewTopics = profileGetCollectionPreviewTopics($pdo, (int) ($collection['id'] ?? 0), 3);
    $preview = [];
    foreach ($previewTopics as $topic) {
        if (!is_array($topic)) {
            continue;
        }
        $preview[] = [
            'title' => (string) ($topic['title'] ?? 'Konu'),
            'url' => topicUrlForRow($topic),
        ];
    }
    $profile_public_collections[] = [
        'name' => (string) ($collection['name'] ?? 'Koleksiyon'),
        'count' => number_format((int) ($collection['item_count'] ?? 0)) . ' icerik',
        'description' => (string) ($collection['description'] ?? ''),
        'has_description' => trim((string) ($collection['description'] ?? '')) !== '',
        'preview_topics' => $preview,
        'has_preview_topics' => $preview !== [],
    ];
}
$profile_has_public_collections = $profile_public_collections !== [];

require_once $projectRoot . '/includes/public-header.php';
?>

<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container breadcrumb-container profile-breadcrumb-shell ui-container ui-section">
    <nav class="breadcrumb">
        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right"></i>
        <a href="<?= $baseUri ?>/profil">Profiller</a>
        <i class="bi bi-chevron-right"></i>
        <span><?= htmlspecialchars((string) $user['name']) ?></span>
    </nav>
</div>
<?php endif; ?>

<div class="container profile-container profile-page-shell profile-shell profile-public-shell ui-container ui-section">
    <div class="topic-report-modal user-report-modal" id="userReportModal" role="dialog" aria-modal="true" aria-labelledby="user-report-heading" hidden aria-hidden="true">
        <div class="topic-report-backdrop" data-user-report-modal-close data-ui-modal-close></div>
        <div class="topic-report-dialog ui-panel">
            <div class="topic-report-header ui-panel__head">
                <h2 id="user-report-heading"><i class="bi bi-flag"></i> Kullanıcıyı Åikayet Et</h2>
                <button type="button" class="topic-report-close" data-user-report-modal-close data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg"></i></button>
            </div>
            <?php if ($canReportProfile): ?>
            <form class="user-report-form" action="<?= $baseUri ?>/api/user-reports.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="reported_user_id" value="<?= (int) $user['id'] ?>">
                <div class="topic-report-grid ui-grid">
                    <label>
                        <span>Neden</span>
                        <select name="reason" required>
                            <?php foreach ($profile_report_reasons as $reason): ?>
                                <option value="<?= htmlspecialchars((string) ($reason['value'] ?? '')) ?>"><?= htmlspecialchars((string) ($reason['label'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Detay</span>
                        <textarea name="details" rows="3" maxlength="1000" placeholder="Ek bilgi varsa yazın"></textarea>
                    </label>
                </div>
                <button type="submit" class="topic-report-submit"><i class="bi bi-send"></i> Åikayet Gönder</button>
                <div class="topic-report-feedback" aria-live="polite"></div>
            </form>
            <?php else: ?>
            <div class="topic-report-login">
                <i class="bi bi-shield-exclamation"></i>
                <span>Kullanıcı şikayeti göndermek için giriş yapmalısınız.</span>
                <a href="<?= htmlspecialchars(function_exists('routePublicStaticUrl') ? routePublicStaticUrl('login') : ($baseUri . '/giris'), ENT_QUOTES, 'UTF-8') ?>">Giriş yap</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2 Sütunlu Layout -->

    <div class="profile-two-column-layout ui-section">
        <!-- Sol Sütun (Ana İçerik) -->
        <div class="profile-main-content ui-section">
    <section class="profile-section profile-topics profile-public-topics ui-card ui-section" id="profile-topics">
        <div class="profile-section-head ui-panel__head">
            <div>
                <span class="profile-section-kicker">Yayın arşivi</span>
                <h2 class="profile-section-title">
                    <i class="bi bi-file-earmark-text"></i>
                    Yayınlanan Konular
                </h2>
            </div>
            <span class="profile-count"><?= number_format($publishedTopicCount) ?> içerik</span>
        </div>

        <?php if ($profileTotalPages > 1): ?>
            <div class="profile-page-summary">
                Sayfa <?= number_format($profileCurrentPage) ?> / <?= number_format($profileTotalPages) ?>
            </div>
        <?php endif; ?>

        <?php if (!$showTopics): ?>
            <div class="profile-empty ui-empty">
                <i class="bi bi-lock"></i>
                <p>Bu kullanıcı konularını gizlemiş.</p>
            </div>
        <?php elseif (empty($userTopics)): ?>
            <div class="profile-empty ui-empty">
                <i class="bi bi-journal-x"></i>
                <p>Bu kullanıcı henüz yayınlanmış konu paylaşmadı.</p>
            </div>
        <?php else: ?>
            <div class="profile-topics-grid ui-grid">
                <?php foreach ($profile_topics as $topic): ?>
                    <article class="profile-topic-card profile-public-topic-card ui-card">
                        <?php if (!empty($topic['image'])): ?>
                            <img class="profile-topic-card__image" src="<?= htmlspecialchars((string) $topic['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($topic['image_alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" width="640" height="360" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <div class="profile-topic-rank"><?= htmlspecialchars((string) ($topic['rank'] ?? '')) ?></div>
                        <a href="<?= htmlspecialchars((string) ($topic['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="profile-topic-link">
                            <h3 class="profile-topic-title"><?= htmlspecialchars((string) ($topic['title'] ?? 'Konu')) ?></h3>
                        </a>
                        <div class="profile-topic-meta">
                            <span><i class="bi bi-eye"></i> <?= htmlspecialchars((string) ($topic['views'] ?? '0')) ?></span>
                            <span><i class="bi bi-download"></i> <?= htmlspecialchars((string) ($topic['downloads'] ?? '0')) ?></span>
                            <span><i class="bi bi-chat-dots"></i> <?= htmlspecialchars((string) ($topic['comments'] ?? '0')) ?></span>
                        </div>
                        <div class="profile-topic-footer ui-panel__foot">
                            <span class="profile-topic-category">
                                <i class="bi bi-folder2"></i>
                                <?= htmlspecialchars((string) ($topic['category'] ?? 'Genel')) ?>
                            </span>
                            <a href="<?= htmlspecialchars((string) ($topic['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="profile-topic-action" title="Konuya Git">
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($paginationHtml !== ''): ?>
                <div class="profile-paging">
                    <?= $paginationHtml ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php if (!empty($profile_public_collections)): ?>
    <section class="profile-section profile-collections profile-public-collections ui-card ui-section">
        <div class="profile-section-head ui-panel__head">
            <div>
                <span class="profile-section-kicker">Secili listeler</span>
                <h2 class="profile-section-title">
                    <i class="bi bi-bookmarks"></i>
                    Public Koleksiyonlar
                </h2>
            </div>
        </div>
        <div class="profile-collection-grid ui-grid">
            <?php foreach ($profile_public_collections as $collection): ?>
                <article class="profile-collection-card ui-card">
                    <div class="profile-collection-head">
                        <strong><?= htmlspecialchars((string) ($collection['name'] ?? 'Koleksiyon')) ?></strong>
                        <span><?= htmlspecialchars((string) ($collection['count'] ?? '0 icerik')) ?></span>
                    </div>
                    <?php if (!empty($collection['has_description'])): ?>
                        <p><?= htmlspecialchars((string) $collection['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($collection['has_preview_topics'])): ?>
                        <div class="profile-collection-topics">
                            <?php foreach ($collection['preview_topics'] as $topic): ?>
                                <a href="<?= htmlspecialchars((string) ($topic['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-arrow-right-short"></i>
                                    <span><?= htmlspecialchars((string) ($topic['title'] ?? 'Konu')) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
        </div><!-- .profile-main-content -->

        <!-- Sağ Sütun (Sidebar - Kullanıcı Bilgileri) -->
        <?php
        $profileSidebar = profileBuildSidebarData($user, [
            'base_uri' => $baseUri,
            'avatar' => (string) ($profileContext['avatar_url'] ?? ''),
            'avatar_fallback' => (string) ($profileContext['avatar_fallback'] ?? ''),
            'group_slug' => (string) ($profileContext['group_slug'] ?? ''),
            'group_name' => (string) ($profileContext['group_name'] ?? ''),
            'bio' => (string) ($profileContext['bio'] ?? ''),
            'created_at' => (string) ($user['created_at'] ?? date('Y-m-d')),
            'location' => (string) ($profileContext['location'] ?? ''),
            'social_links' => $profileContext['social_links'] ?? [],
            'stats' => is_array($profileContext['stats'] ?? null) ? $profileContext['stats'] : [],
            'can_report' => !empty($profileContext['can_report']),
            'show_leaderboard' => !empty($profileContext['show_leaderboard']),
            'leaderboard_user_id' => (int) ($profileContext['leaderboard_user_id'] ?? $user['id']),
        ]);
        include $projectRoot . '/includes/partials/profile-sidebar.php';
        ?>
    </div><!-- .profile-two-column-layout -->
</div>

<script src="<?= asset_url('assets/js/public-profile-report.js', $baseUri) ?>" defer></script>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>

