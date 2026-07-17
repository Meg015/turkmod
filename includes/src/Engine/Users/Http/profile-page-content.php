<?php



declare(strict_types=1);

/**

 * Kullanıcı Profili — Detaylı profil sayfası

 * İş mantığı: includes/src/Engine/Users/Support/profile-helpers.php

 */

require_once $projectRoot . "/includes/init.php";

require_once $projectRoot . "/includes/src/Engine/Users/Support/profile-helpers.php";

require_once $projectRoot . "/includes/src/Engine/Logs/Support/helpers.php";



if (!$isLoggedIn) {

    $loginRedirectUrl = routePublicStaticUrl('login');
    header("Location: " . $loginRedirectUrl . "?redirect=" . rawurlencode((string) ($_SERVER["REQUEST_URI"] ?? routeCanonicalPath('profile'))));

    exit();

}



$userId = (int) $_SESSION["_auth_user_id"];



if (!$pdo) {

    http_response_code(500);

    $pageTitle = "Hata";

    require_once $projectRoot . "/includes/public-header.php";
    echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">Veritabanı bağlantısı kurulamadı.</div>';

    require_once $projectRoot . "/includes/public-footer.php";
    exit();

}



profileEnsureColumns($pdo);

$profilePageBaseUrl = routeCanonicalPath('profile');



$profileSettings = function_exists("getAdminSettings") ? getAdminSettings($pdo) : [];

$passwordPolicy = passwordPolicyConfig($profileSettings);

$passwordMinLength = (int) $passwordPolicy["min_length"];

$passwordPolicyHint = passwordPolicyHint($profileSettings);

$successMsg = "";

$errorMsg = "";

$pwSuccess = "";

$pwError = "";

$suppressPageSuccessAlert = false;

$activeTab = profileResolveTab($_GET["tab"] ?? "overview");

$profileTabLabels = [

    "overview" => "Genel Bakış",

    "topics" => "Konularım",

    "comments" => "Yorumlarım",

    "favorites" => "Favorilerim",

    "reports" => "Raporlarım",

    "activity" => "Aktivite",

    "settings" => "Ayarlar",

    "security" => "Güvenlik",

];

$topicStatusFilter = (string)($_GET["topic_status"] ?? "all");

$topicStatusOptions = [

    "all" => ["Tümü", "bi-grid"],
    "published" => ["Yayında", "bi-check-circle"],
    "revision" => ["Revizyon", "bi-arrow-repeat"],
    "rejected" => ["Reddedildi", "bi-x-circle"],
    "draft" => ["Taslak", "bi-pencil-square"],
];

if (!array_key_exists($topicStatusFilter, $topicStatusOptions)) {

    $topicStatusFilter = "all";

}

$activityFilterOptions = profileActivityFilterOptions();

$activityFilter = $activeTab === "activity"

    ? profileResolveActivityFilter($_GET["activity_type"] ?? "all")

    : "all";



// ---- POST İşlemleri ----

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token($_POST["_token"] ?? "")) {

        $errorMsg = "Güvenlik doğrulaması başarısız.";

    } else {

        $action = $_POST["action"] ?? "";



        if ($action === "update_profile") {

            try {

                profileUpdate($pdo, $userId, [

                    "username" => trim((string) ($_POST["username"] ?? "")),

                    "bio" => trim($_POST["bio"] ?? ""),

                    "website" => trim($_POST["website"] ?? ""),

                    "location" => trim($_POST["location"] ?? ""),

                    "social_github" => trim($_POST["social_github"] ?? ""),

                    "social_twitter" => trim($_POST["social_twitter"] ?? ""),

                    "social_discord" => trim($_POST["social_discord"] ?? ""),

                    "public_profile" => isset($_POST["public_profile"])

                        ? "1"

                        : "0",

                    "public_show_topics" => isset($_POST["public_show_topics"])

                        ? "1"

                        : "0",

                    "public_show_comments" => isset(

                        $_POST["public_show_comments"],

                    )

                        ? "1"

                        : "0",

                    "public_show_socials" => isset(

                        $_POST["public_show_socials"],

                    )

                        ? "1"

                        : "0",

                ]);

                $_SESSION["_auth_user_name"] =

                    trim((string) ($_POST["username"] ?? "")) ?: $_SESSION["_auth_user_name"];

                $successMsg = "Profil bilgileriniz güncellendi.";

                logActivity($pdo, "profile_updated", "user", $userId);

            } catch (Throwable $e) {

                $errorMsg = "Profil güncellenemedi: " . safeErrorMessage($e);

            }

            $activeTab = "settings";

        }



        if ($action === "change_password") {

            $wasRemembered = !empty($_SESSION["_auth_remember_session"]);
            $pwErr = profileChangePassword(

                $pdo,

                $userId,

                $_POST["current_password"] ?? "",

                $_POST["new_password"] ?? "",

                $_POST["new_password_confirm"] ?? "",

            );

            if ($pwErr === "") {

                $_SESSION["_auth_login_time"] = time() + 1;
                $_SESSION["_auth_last_activity"] = time();
                if ($wasRemembered && function_exists("authIssueRememberToken")) {
                    authIssueRememberToken($pdo, $userId, $profileSettings);
                    $_SESSION["_auth_remember_session"] = 1;
                } else {
                    unset($_SESSION["_auth_remember_session"]);
                }
                $pwSuccess = "Şifreniz başarıyla değiştirildi.";

                logActivity($pdo, "password_changed", "user", $userId);
                try {
                    accountEmailService($pdo)->send('password_changed', (string) ($user['email'] ?? ''), [
                        'username' => (string) ($user['username'] ?? ''),
                        'actor_context' => 'Hesap sahibi',
                        'action_url' => routePublicStaticUrl('forgot_password'),
                    ]);
                } catch (Throwable $mailError) {
                    if (function_exists('appLogException')) appLogException($mailError, ['source' => 'profile_password_changed']);
                }

            } else {

                $pwError = $pwErr;

            }

            $activeTab = "security";

        }



        if ($action === "logout_all_devices") {

            try {
                $wasRemembered = !empty($_SESSION["_auth_remember_session"]);

                // Tüm cihazlardaki oturumları geçersiz kıl. refreshAuthenticatedSession,

                // login_time < password_changed_at olan oturumları reddeder; bu sütunu

                // güncellemek (şifreyi değiştirmeden) tüm aktif oturumları sonlandırır.

                $stmt = $pdo->prepare(

                    "UPDATE users SET password_changed_at = NOW(), remember_token = NULL, updated_at = NOW() WHERE id = ?",

                );

                $stmt->execute([$userId]);



                // Bu cihazın oturumu açık kalsın diye giriş zamanını ileri al.

                $_SESSION["_auth_login_time"] = time() + 1;

                $_SESSION["_auth_last_activity"] = time();
                if ($wasRemembered && function_exists("authIssueRememberToken")) {
                    authIssueRememberToken($pdo, $userId, $profileSettings);
                    $_SESSION["_auth_remember_session"] = 1;
                } else {
                    unset($_SESSION["_auth_remember_session"]);
                }



                logActivity($pdo, "sessions_revoked", "user", $userId);

                $suppressPageSuccessAlert = true;

                $successMsg =

                    "Diğer tüm cihazlardaki oturumlar sonlandırıldı. Bu cihazdaki oturumunuz açık kaldı.";

            } catch (Throwable $e) {

                $errorMsg =

                    "Oturumlar sonlandırılamadı: " . safeErrorMessage($e);

            }

            $activeTab = "security";

        }



        if ($action === "upload_avatar") {

            $uploadBase =

                realpath($projectRoot . "/uploads") ?: $projectRoot . "/uploads";

            $err = profileUploadAvatar(

                $pdo,

                $userId,

                $_FILES["avatar"] ?? ["error" => UPLOAD_ERR_NO_FILE],

                $uploadBase,

            );

            if ($err === "") {

                $successMsg = "Profil fotoğrafınız güncellendi.";

                logActivity($pdo, "avatar_updated", "user", $userId);

            } else {

                $errorMsg = $err;

            }

            $activeTab = "settings";

        }



        if ($action === "create_collection") {

            $result = createTopicCollection(

                $pdo,

                $userId,

                (string) ($_POST["collection_name"] ?? ""),

                (string) ($_POST["collection_description"] ?? ""),

            );

            if ($result["success"]) {

                $successMsg = (string) $result["message"];

            } else {

                $errorMsg = (string) $result["message"];

            }

            $activeTab = "favorites";

        }



        if ($action === "delete_collection") {

            $deleted = deleteTopicCollection(

                $pdo,

                (int) ($_POST["collection_id"] ?? 0),

                $userId,

            );

            $deleted

                ? $successMsg = "Koleksiyon silindi."

                : $errorMsg = "Koleksiyon silinemedi.";

            $activeTab = "favorites";

        }



        if ($action === "update_collection_visibility") {

            $updated = profileUpdateCollectionVisibility(

                $pdo,

                (int) ($_POST["collection_id"] ?? 0),

                $userId,

                (string) ($_POST["visibility"] ?? "private"),

            );

            $updated

                ? $successMsg = "Koleksiyon gizliligi guncellendi."

                : $errorMsg = "Koleksiyon gizliligi guncellenemedi.";

            $activeTab = "favorites";

        }



        if ($action === "add_to_collection") {

            $added = addTopicToCollection(

                $pdo,

                (int) ($_POST["collection_id"] ?? 0),

                (int) ($_POST["topic_id"] ?? 0),

                $userId,

            );

            $added

                ? $successMsg = "İçerik koleksiyona eklendi."

                : $errorMsg = "İçerik koleksiyona eklenemedi.";

            $activeTab = "favorites";

        }



        if ($action === "remove_from_collection") {

            $removed = removeTopicFromCollection(

                $pdo,

                (int) ($_POST["collection_id"] ?? 0),

                (int) ($_POST["topic_id"] ?? 0),

                $userId,

            );

            $removed

                ? $successMsg = "İçerik koleksiyondan kaldırıldı."

                : $errorMsg = "İçerik koleksiyondan kaldırılamadı.";

            $activeTab = "favorites";

        }



        if ($action === "resubmit_topic") {

            $resubmitted = profileResubmitTopicForModeration(

                $pdo,

                (int) ($_POST["topic_id"] ?? 0),

                $userId,

            );

            if ($resubmitted) {

                $successMsg = "Konu tekrar onaya gönderildi.";

                logActivity($pdo, "topic_resubmitted", "topic", (int) ($_POST["topic_id"] ?? 0));

            } else {

                $errorMsg = "Konu tekrar onaya gönderilemedi.";

            }

            $activeTab = "topics";

        }

    }

}



// ---- Veri Yükle ----

$user = profileGetUser($pdo, $userId);

if (!$user) {

    http_response_code(404);

    $pageTitle = "Profil Bulunamadı";

    require_once $projectRoot . "/includes/public-header.php";
    echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">Kullanıcı profili bulunamadı.</div>';

    require_once $projectRoot . "/includes/public-footer.php";
    exit();

}



$stats = profileGetStatsReal($pdo, $userId);

$profilePerPage = 10;

$profilePageParams = [

    "topics" => "topics_page",

    "comments" => "comments_page",

    "favorites" => "favorites_page",

    "reports" => "reports_page",

    "activity" => "activity_page",

];

$profileTotals = [

    "topics" => profileCountPublishedTopics($pdo, $userId),

    "comments" => profileCountComments($pdo, $userId),

    "favorites" => profileCountFavorites($pdo, $userId),

    "reports" => profileCountReports($pdo, $userId),

    "activity" => profileCountActivity($pdo, $userId, $activityFilter),

];

$activityFilterCounts = [];

if ($activeTab === "activity") {

    foreach (array_keys($activityFilterOptions) as $filterKey) {

        $activityFilterCounts[$filterKey] = profileCountActivity($pdo, $userId, (string) $filterKey);

    }

}

$profilePages = [];

$profileOffsets = [];

foreach ($profilePageParams as $tabKey => $paramName) {

    $totalPages = max(1, (int) ceil($profileTotals[$tabKey] / $profilePerPage));

    $pageValue = max(1, (int) ($_GET[$paramName] ?? 1));

    $profilePages[$tabKey] = min($pageValue, $totalPages);

    $profileOffsets[$tabKey] = ($profilePages[$tabKey] - 1) * $profilePerPage;

}

$safeTopicStatusFilter = (string) $topicStatusFilter;

$safeActivityFilter = profileResolveActivityFilter($activityFilter);

$renderProfilePagination = static function (string $tabKey) use ($baseUri, $profilePageBaseUrl, $profileTotals, $profilePages, $profilePerPage, $profilePageParams, $safeTopicStatusFilter, $safeActivityFilter): string {

    if ($profileTotals[$tabKey] <= 0) {

        return "";

    }

    $totalPages = max(1, (int) ceil($profileTotals[$tabKey] / $profilePerPage));



    $paramName = $profilePageParams[$tabKey];

    $currentPage = $profilePages[$tabKey];

    $urlForPage = static function (int $page) use ($baseUri, $tabKey, $paramName, $safeTopicStatusFilter, $safeActivityFilter): string {

        $url = $profilePageBaseUrl . '?tab=' . urlencode($tabKey) . "&" . urlencode($paramName) . "=" . $page;

        if ($tabKey === "topics" && $safeTopicStatusFilter !== "all") {

            $url .= "&topic_status=" . urlencode($safeTopicStatusFilter);

        }

        if ($tabKey === "activity" && $safeActivityFilter !== "all") {

            $url .= "&activity_type=" . urlencode($safeActivityFilter);

        }

        return $url;

    };



    $html = '<nav class="topic-pagination profile-tab-pagination" aria-label="Profil sayfalama"><ul>';

    if ($currentPage > 1) {

        $html .= '<li><a href="' . htmlspecialchars($urlForPage($currentPage - 1)) . '" aria-label="Önceki sayfa">&laquo;</a></li>';

    }



    $maxVisible = 7;

    $start = max(1, $currentPage - (int) floor($maxVisible / 2));

    $end = min($totalPages, $start + $maxVisible - 1);

    if ($end - $start < $maxVisible - 1) {

        $start = max(1, $end - $maxVisible + 1);

    }

    if ($start > 1) {

        $html .= '<li><a href="' . htmlspecialchars($urlForPage(1)) . '">1</a></li>';

        if ($start > 2) {

            $html .= '<li class="disabled"><span>&hellip;</span></li>';

        }

    }

    for ($i = $start; $i <= $end; $i++) {

        $active = $i === $currentPage ? ' class="active" aria-current="page"' : '';

        $html .= '<li' . $active . '><a href="' . htmlspecialchars($urlForPage($i)) . '">' . $i . '</a></li>';

    }

    if ($end < $totalPages) {

        if ($end < $totalPages - 1) {

            $html .= '<li class="disabled"><span>&hellip;</span></li>';

        }

        $html .= '<li><a href="' . htmlspecialchars($urlForPage($totalPages)) . '">' . $totalPages . '</a></li>';

    }

    if ($currentPage < $totalPages) {

        $html .= '<li><a href="' . htmlspecialchars($urlForPage($currentPage + 1)) . '" aria-label="Sonraki sayfa">&raquo;</a></li>';

    }



    return $html . '</ul></nav>';

};

$userTopics = in_array($topicStatusFilter, ["all", "published"], true)

    ? profileGetTopics($pdo, $userId, $profilePerPage, $profileOffsets["topics"])

    : [];

$pendingTopics = $topicStatusFilter === "published"

    ? []

    : profileGetPendingTopics($pdo, $userId, 10, $topicStatusFilter === "all" ? "" : $topicStatusFilter);

$userComments = profileGetComments($pdo, $userId, $profilePerPage, $profileOffsets["comments"]);

$userActivity = profileGetActivity($pdo, $userId, $profilePerPage, $profileOffsets["activity"], $activityFilter);

$userFavorites = profileGetFavorites($pdo, $userId, $profilePerPage, $profileOffsets["favorites"]);

$userCollections = profileGetCollections($pdo, $userId);

$collectionItems = profileGetCollectionItems($pdo, $userId);

$userReports = profileGetReports($pdo, $userId, $profilePerPage, $profileOffsets["reports"]);
$userRestrictions = profileGetActiveRestrictions($pdo, $userId);
$profileContext = profileBuildProfileContext($user, [
    'base_uri' => $baseUri,
    'bio' => (string) ($user["bio"] ?? ''),
    'location' => (string) ($user["location"] ?? ''),
    'website' => (string) ($user["website"] ?? ''),
    'social_github' => (string) ($user["social_github"] ?? ''),
    'social_twitter' => (string) ($user["social_twitter"] ?? ''),
    'social_discord' => (string) ($user["social_discord"] ?? ''),
    'topic_count' => (int) ($stats["topics"] ?? 0),
    'comment_count' => (int) ($stats["comments"] ?? 0),
    'view_count' => (int) ($stats["views"] ?? 0),
    'download_count' => (int) ($stats["downloads"] ?? 0),
    'stat_labels' => [
        'topics' => 'Konu',
        'comments' => 'Yorum',
        'views' => 'Görüntülenme',
        'downloads' => 'İndirme',
    ],
    'cover' => (string) ($user["cover"] ?? $user["cover_image"] ?? ''),
]);
$profileContext['show_leaderboard'] = ($profileSettings["leaderboard_show_profile"] ?? "1") === "1";
$profileContext['leaderboard_user_id'] = $userId;


$pageTitle = "Profilim";

$profile_is_public = false;
$profile_is_private = true;
$profile_private_username = (string) ($profileContext['username'] ?? '');
$profile_private_name = $profile_private_username;
$profile_private_email = (string) ($profileContext['email'] ?? '');
$profile_private_bio = (string) ($profileContext['bio'] ?? '');
$profile_private_location = (string) ($profileContext['location'] ?? '');
$profile_private_website = (string) ($profileContext['website'] ?? '');
$profile_private_social_github = (string) ($profileContext['social_github'] ?? '');
$profile_private_social_twitter = (string) ($profileContext['social_twitter'] ?? '');
$profile_private_social_discord = (string) ($profileContext['social_discord'] ?? '');
$profile_private_avatar = (string) ($profileContext['avatar_url'] ?? '');
$profile_private_avatar_fallback = (string) ($profileContext['avatar_fallback'] ?? '');
$profile_private_initials = (string) ($profileContext['initials'] ?? '');
$profile_group = (string) ($profileContext['group'] ?? '');
$profile_group_name = (string) ($profileContext['group_name'] ?? '');
$profile_group_slug = (string) ($profileContext['group_slug'] ?? '');
$profile_private_group = (string) ($profileContext['private_group'] ?? '');
$profile_role_name = (string) ($profileContext['role_name'] ?? '');
$profile_private_role = (string) ($profileContext['private_role'] ?? '');
$profile_status_label = (string) ($profileContext['status_label'] ?? '');
$profile_status_badge_class = (string) ($profileContext['status_badge_class'] ?? '');
$profile_private_success = $successMsg;
$profile_private_suppress_success_alert = $suppressPageSuccessAlert;
$profile_private_error = $errorMsg;
$profile_private_pw_success = $pwSuccess;
$profile_private_pw_error = $pwError;
$profile_private_csrf_token = csrf_token();

$profile_tab_overview = $activeTab === 'overview';

$profile_tab_topics = $activeTab === 'topics';

$profile_tab_comments = $activeTab === 'comments';

$profile_tab_favorites = $activeTab === 'favorites';

$profile_tab_reports = $activeTab === 'reports';

$profile_tab_activity = $activeTab === 'activity';

$profile_tab_settings = $activeTab === 'settings';

$profile_tab_security = $activeTab === 'security';

$profile_private_tabs = [];

foreach ($profileTabLabels as $tabKey => $tabLabel) {

    $profile_private_tabs[] = [

        'url' => $profilePageBaseUrl . '?tab=' . rawurlencode((string) $tabKey),

        'class' => $activeTab === $tabKey ? 'profile-tab active' : 'profile-tab',

        'label' => (string) $tabLabel,

        'icon' => match ((string) $tabKey) {

            'topics' => 'bi-file-earmark-text',

            'comments' => 'bi-chat-dots',

            'favorites' => 'bi-heart',

            'reports' => 'bi-flag',

            'activity' => 'bi-clock-history',

            'settings' => 'bi-gear',

            'security' => 'bi-shield-lock',

            default => 'bi-grid',

        },

    ];

}

$profile_private_topics = [];
foreach (array_merge($userTopics, $pendingTopics) as $topicRow) {

    if (!is_array($topicRow)) {

        continue;

    }

    $profile_private_topics[] = [

        'title' => (string) ($topicRow['title'] ?? 'Konu'),

        'url' => topicUrlForRow($topicRow),

        'status' => (string) ($topicRow['status'] ?? 'published'),

        'views' => number_format((int) ($topicRow['view_count'] ?? 0)),

        'downloads' => number_format((int) ($topicRow['download_count'] ?? 0)),

        'comments' => number_format((int) ($topicRow['comment_count'] ?? 0)),

    ];

}

$profile_private_has_topics = $profile_private_topics !== [];

$profile_private_comments = [];

foreach ($userComments as $commentRow) {

    if (!is_array($commentRow)) {

        continue;

    }

    $profile_private_comments[] = [

        'body' => mb_substr(trim(strip_tags((string) ($commentRow['body'] ?? $commentRow['content'] ?? ''))), 0, 180),

        'topic' => (string) ($commentRow['topic_title'] ?? $commentRow['title'] ?? 'Konu'),

        'date' => !empty($commentRow['created_at']) ? date('d.m.Y H:i', strtotime((string) $commentRow['created_at'])) : '',

        'url' => isset($commentRow['topic_id']) ? topicUrlForRow($commentRow) : '#',

    ];

}

$profile_private_has_comments = $profile_private_comments !== [];

$profile_private_favorites = [];

foreach ($userFavorites as $favRow) {

    if (!is_array($favRow)) {

        continue;

    }

    $profile_private_favorites[] = [

        'title' => (string) ($favRow['title'] ?? 'Konu'),

        'url' => topicUrlForRow($favRow),

        'category' => (string) ($favRow['category'] ?? $favRow['category_name'] ?? 'Genel'),

    ];

}

$profile_private_has_favorites = $profile_private_favorites !== [];

$profile_private_activity = [];

foreach ($userActivity as $activityRow) {

    if (!is_array($activityRow)) {

        continue;

    }

    $profile_private_activity[] = [

        'title' => function_exists('profileActivitySentence') ? profileActivitySentence($activityRow) : (string) ($activityRow['action'] ?? 'Aktivite'),

        'date' => !empty($activityRow['created_at']) ? date('d.m.Y H:i', strtotime((string) $activityRow['created_at'])) : '',

    ];

}

$profile_private_has_activity = $profile_private_activity !== [];

$profile_private_reports = [];

foreach ($userReports as $reportRow) {

    if (!is_array($reportRow)) {

        continue;

    }

    $profile_private_reports[] = [

        'title' => (string) ($reportRow['topic_title'] ?? $reportRow['title'] ?? 'Rapor'),

        'status' => (string) ($reportRow['status'] ?? ''),

        'date' => !empty($reportRow['created_at']) ? date('d.m.Y H:i', strtotime((string) $reportRow['created_at'])) : '',

    ];

}

$profile_private_has_reports = $profile_private_reports !== [];

$profile_password_min_length = (string) $passwordMinLength;

$profile_password_policy_hint = $passwordPolicyHint;

$profile_password_require_uppercase = !empty($passwordPolicy['require_uppercase']);

$profile_password_require_numbers = !empty($passwordPolicy['require_numbers']);

$profile_password_require_special = !empty($passwordPolicy['require_special']);

$profile_privacy_options = [

    ['field' => 'public_profile', 'id' => 'pp_profile', 'icon' => 'bi-person-badge', 'title' => 'Profil sayfam yayinda olsun', 'description' => 'Kapaliyken public profiliniz ziyaretcilere gosterilmez.', 'checked' => (int) ($user['public_profile'] ?? 1) === 1 ? 'checked' : ''],

    ['field' => 'public_show_topics', 'id' => 'pp_topics', 'icon' => 'bi-collection', 'title' => 'Konularim gorunsun', 'description' => 'Public profilde yayinlanmis icerikleriniz listelenir.', 'checked' => (int) ($user['public_show_topics'] ?? 1) === 1 ? 'checked' : ''],

    ['field' => 'public_show_comments', 'id' => 'pp_comments', 'icon' => 'bi-chat-square-text', 'title' => 'Yorum sayim gorunsun', 'description' => 'Profil ozetinde yorum aktiviteniz paylasilir.', 'checked' => (int) ($user['public_show_comments'] ?? 0) === 1 ? 'checked' : ''],

    ['field' => 'public_show_socials', 'id' => 'pp_socials', 'icon' => 'bi-link-45deg', 'title' => 'Sosyal baglantilarim gorunsun', 'description' => 'Web sitesi ve sosyal hesaplariniz public profilde gorunur.', 'checked' => (int) ($user['public_show_socials'] ?? 1) === 1 ? 'checked' : ''],

];

require_once $projectRoot . "/includes/public-header.php";
?>



<!-- Breadcrumb -->

<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>

<div class="ui-container container public-container public-breadcrumb breadcrumb-container">

    <nav class="breadcrumb">

        <a href="<?= $baseUri ?>/index.php"><i class="bi bi-house-door"></i> Ana Sayfa</a>

        <i class="bi bi-chevron-right"></i>

        <span>Profilim</span>

        <?php if ($activeTab !== "overview"): ?>

            <i class="bi bi-chevron-right"></i>

            <span><?= htmlspecialchars($profileTabLabels[$activeTab] ?? "Sekme") ?></span>

        <?php endif; ?>

    </nav>

</div>

<?php endif; ?>



<div

    class="ui-container container public-container public-content profile-page-shell profile-shell profile-private-shell ui-section"

    data-profile-page

    data-profile-active-tab="<?= htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') ?>"

>



<?php if ($successMsg && !$suppressPageSuccessAlert): ?>

<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success" role="alert"><?= htmlspecialchars(

    $successMsg,

) ?><button type="button" class="ui-admin-alert-close" aria-label="Kapat"><i class="bi bi-x-lg"></i></button></div>

<?php endif; ?>

<?php if ($errorMsg): ?>

<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert"><?= htmlspecialchars(

    $errorMsg,

) ?><button type="button" class="ui-admin-alert-close" aria-label="Kapat"><i class="bi bi-x-lg"></i></button></div>

<?php endif; ?>



<?php if (($_GET["submitted"] ?? "") === "1" || ($_GET["edited"] ?? "") === "1"): ?>

<div class="profile-followup-panel ui-panel" role="status">

    <i class="bi bi-send-check"></i>

    <div>

        <strong><?= ($_GET["edited"] ?? "") === "1" ? "Değişiklikler onaya gönderildi" : "Modunuz onaya gönderildi" ?></strong>

        <span>Onay bekleyen içerikler bu sekmede görünür. Moderatör notu gelirse aynı karttan düzenleyip tekrar gönderebilirsiniz.</span>

    </div>

</div>

<?php endif; ?>



<?php if (!empty($userRestrictions)): ?>

<section class="profile-restriction-panel ui-panel" aria-label="Aktif hesap kisitlamalari">

    <div class="profile-restriction-panel-head ui-panel__head">

        <span class="profile-restriction-icon"><i class="bi bi-shield-exclamation"></i></span>

        <div>

            <strong>Hesabinizda aktif kisitlama var</strong>

            <span>Islem yapmadan once kapsam ve bitis tarihini kontrol edin.</span>

        </div>

        <a href="<?= routePublicStaticUrl('ban_appeals') ?>" class="profile-restriction-appeal">

            <i class="bi bi-envelope"></i> Itiraz

        </a>

    </div>

    <div class="profile-restriction-list">

        <?php foreach ($userRestrictions as $restriction): ?>

        <div class="profile-restriction-item">

            <strong><?= htmlspecialchars(profileRestrictionLabel((string) ($restriction["restriction_type"] ?? ""))) ?></strong>

            <span>

                <?php if (!empty($restriction["expires_at"])): ?>

                    <?= date("d.m.Y H:i", strtotime((string) $restriction["expires_at"])) ?> tarihine kadar

                <?php else: ?>

                    Suresiz

                <?php endif; ?>

            </span>

            <?php if (!empty($restriction["reason"])): ?>

                <small><?= htmlspecialchars((string) $restriction["reason"]) ?></small>

            <?php endif; ?>

        </div>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>



<!-- Tablar -->

<div class="profile-tabs">

    <a href="?tab=overview" class="profile-tab <?= $activeTab === "overview"

        ? "active"

        : "" ?>"><i class="bi bi-grid me-1 ui-grid"></i>Genel Bakış</a>

    <a href="?tab=topics" class="profile-tab <?= $activeTab === "topics"

        ? "active"

        : "" ?>"><i class="bi bi-file-earmark-text me-1"></i>Konularım</a>

    <a href="?tab=comments" class="profile-tab <?= $activeTab === "comments"

        ? "active"

        : "" ?>"><i class="bi bi-chat-dots me-1"></i>Yorumlarım</a>

    <a href="?tab=favorites" class="profile-tab <?= $activeTab === "favorites"

        ? "active"

        : "" ?>"><i class="bi bi-heart me-1"></i>Favorilerim</a>

    <a href="?tab=reports" class="profile-tab <?= $activeTab === "reports"

        ? "active"

        : "" ?>"><i class="bi bi-flag me-1"></i>Raporlarım</a>

    <a href="?tab=activity" class="profile-tab <?= $activeTab === "activity"

        ? "active"

        : "" ?>"><i class="bi bi-clock-history me-1"></i>Aktivite</a>

    <a href="?tab=settings" class="profile-tab <?= $activeTab === "settings"

        ? "active"

        : "" ?>"><i class="bi bi-gear me-1"></i>Ayarlar</a>

    <a href="?tab=security" class="profile-tab <?= $activeTab === "security"

        ? "active"

        : "" ?>"><i class="bi bi-shield-lock me-1"></i>Güvenlik</a>

</div>



<section class="profile-quick-access" aria-label="Profil hızlı erişim">

    <a href="<?= $profilePageBaseUrl ?>?tab=topics" class="profile-quick-card ui-card">

        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>

        <span><strong><?= number_format($profileTotals["topics"]) ?></strong> Konularım</span>

    </a>

    <a href="<?= $profilePageBaseUrl ?>?tab=favorites" class="profile-quick-card ui-card">

        <i class="bi bi-heart" aria-hidden="true"></i>

        <span><strong><?= number_format($profileTotals["favorites"]) ?></strong> Favorilerim</span>

    </a>

    <a href="<?= $profilePageBaseUrl ?>?tab=comments" class="profile-quick-card ui-card">

        <i class="bi bi-chat-dots" aria-hidden="true"></i>

        <span><strong><?= number_format($profileTotals["comments"]) ?></strong> Yorumlarım</span>

    </a>

    <a href="<?= htmlspecialchars(routePublicStaticUrl('notifications'), ENT_QUOTES, 'UTF-8') ?>" class="profile-quick-card ui-card">

        <i class="bi bi-bell" aria-hidden="true"></i>

        <span><strong>Merkez</strong> Bildirimler</span>

    </a>

    <a href="<?= $profilePageBaseUrl ?>?tab=settings" class="profile-quick-card ui-card">

        <i class="bi bi-gear" aria-hidden="true"></i>

        <span><strong>Profil</strong> Ayarlar</span>

    </a>

</section>



<!-- TAB: Genel Bakış (2 Sütunlu) -->

<?php if ($activeTab === "overview"): ?>

<div class="profile-two-column-layout ui-section">

    <div class="profile-main-content ui-section">



<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-file-earmark-text"></i>Son Konular</div>

    <?php if (empty($userTopics) && empty($pendingTopics)): ?>

        <div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-journal-x"></i><p>Henüz konu oluşturmadınız.</p><a href="<?= routePublicStaticUrl('upload_topic') ?>">İlk içeriği yükle</a></div>

    <?php else: ?>

        <?php foreach (array_slice($userTopics, 0, 5) as $t): ?>

        <div class="profile-topic-item">

            <div class="profile-stack-fill">

                <a href="<?= topicUrlForRow($t) ?>" class="profile-topic-title"><?= htmlspecialchars($t["title"]) ?></a>

                <div class="profile-topic-meta">

                    <span><i class="bi bi-folder2"></i> <?= htmlspecialchars($t["category"] ?? "Genel") ?></span>

                    <span><i class="bi bi-eye"></i> <?= number_format((int) ($t["view_count"] ?? 0)) ?></span>

                    <span><i class="bi bi-calendar3"></i> <?= date("d.m.Y", strtotime($t["published_at"] ?? ($t["created_at"] ?? "now"))) ?></span>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

        <?php if (count($userTopics) > 5): ?>

        <div class="profile-center-cta"><a href="?tab=topics">Tümünü gör &rarr;</a></div>

        <?php endif; ?>

    <?php endif; ?>

</div>



<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-chat-dots"></i>Son Yorumlar</div>

    <?php if (empty($userComments)): ?>

        <div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-chat-square-text"></i><p>Henüz yorum yapmadınız.</p><a href="<?= $baseUri ?>/index.php">İçerikleri keşfet</a></div>

    <?php else: ?>

        <?php foreach (array_slice($userComments, 0, 5) as $c): ?>

        <?php $commentUrl = topicUrl((string) ($c["topic_slug"] ?? ""), (int) ($c["topic_id"] ?? 0)) . "#comment-" . (int) ($c["id"] ?? 0); ?>

        <div class="profile-comment-item">

            <div class="profile-mini-row">

                <a href="<?= htmlspecialchars($commentUrl) ?>" class="profile-link-strong"><?= htmlspecialchars($c["topic_title"] ?? "Konu") ?></a>

                <span class="profile-small-muted"><?= date("d.m.Y", strtotime($c["created_at"])) ?></span>

            </div>

            <div class="profile-comment-body ui-panel__body"><?= htmlspecialchars(mb_substr($c["body"] ?? "", 0, 120)) . (mb_strlen($c["body"] ?? "") > 120 ? "..." : "") ?></div>

        </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>



<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-clock-history"></i>Son Aktivite</div>

    <?php if (empty($userActivity)): ?>

        <div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-hourglass"></i><p>Henüz aktivite yok.</p><a href="<?= $profilePageBaseUrl ?>?tab=settings">Profili tamamla</a></div>

    <?php else: ?>

        <?php

        $activityTones = [

            "login" => "is-success",

            "create" => "is-primary",

            "update" => "is-warning",

            "delete" => "is-danger",

            "comment" => "is-info",

        ];

        foreach (array_slice($userActivity, 0, 8) as $act):



            $dotTone = "is-muted";

            foreach ($activityTones as $k => $className) {

                if (str_contains($act["action"], $k)) {

                    $dotTone = $className;

                    break;

                }

            }

            $activityTitle = profileActivitySentence($act);

            $activityDetail = profileActivityDisplayDetail($act);

            $activityUrl = profileActivityTargetUrl($act);

            ?>

        <div class="profile-activity-item">

            <div class="profile-activity-dot <?= $dotTone ?>"></div>

            <div class="profile-stack-fill">

                <strong>

                    <?php if ($activityUrl !== ""): ?>

                        <a href="<?= htmlspecialchars($activityUrl) ?>" class="profile-activity-title-link"><?= htmlspecialchars($activityTitle) ?></a>

                    <?php else: ?>

                        <?= htmlspecialchars($activityTitle) ?>

                    <?php endif; ?>

                </strong>

                <?php if ($activityDetail !== ""): ?>

                    <span class="profile-muted"> — <?= htmlspecialchars($activityDetail) ?></span>

                <?php endif; ?>

            </div>

            <span class="profile-date-muted"><?= date(

                "d.m.Y H:i",

                strtotime($act["created_at"]),

            ) ?></span>

        </div>

        <?php

        endforeach;

        ?>

    <?php endif; ?>

</div>



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
        'created_at' => (string) ($user["created_at"] ?? date("Y-m-d")),
        'location' => (string) ($profileContext['location'] ?? ''),
        'social_links' => $profileContext['social_links'] ?? [],
        'stats' => is_array($profileContext['stats'] ?? null) ? $profileContext['stats'] : [],
        'show_leaderboard' => !empty($profileContext['show_leaderboard']),
        'leaderboard_user_id' => (int) ($profileContext['leaderboard_user_id'] ?? $userId),
    ]);
    include $projectRoot . '/includes/partials/profile-sidebar.php';
    ?>
</div><!-- .profile-two-column-layout -->
<?php endif; ?>



<!-- Diğer Sekmeler (Tek Sütun) -->

<?php if ($activeTab !== "overview"): ?>

<div class="profile-single-column">



<!-- TAB: Konularım -->

<?php if ($activeTab === "topics"): ?>

<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-file-earmark-text"></i>Tüm Konularım (<?= number_format(

        $profileTotals["topics"],

    ) ?>)</div>

    <div class="profile-topic-status-filter" aria-label="Konu durum filtresi">

        <?php foreach ($topicStatusOptions as $statusKey => $statusOption): ?>

            <a href="<?= $profilePageBaseUrl ?>?tab=topics&amp;topic_status=<?= urlencode($statusKey) ?>" class="profile-topic-status-filter-link <?= $topicStatusFilter === $statusKey ? "active" : "" ?>">

                <i class="bi <?= htmlspecialchars($statusOption[1]) ?>"></i>

                <?= htmlspecialchars($statusOption[0]) ?>

            </a>

        <?php endforeach; ?>

    </div>

    <?php if (!empty($pendingTopics)): ?>

        <div class="profile-section-title profile-section-title-tight"><i class="bi bi-pencil-square"></i>Taslak ve Revizyon Durumu</div>
        <div class="profile-pending-list">

            <?php foreach ($pendingTopics as $t): ?>

            <?php

                $pendingStatus = (string)($t["status"] ?? "draft");
                $pendingStatusLabels = [
                    "draft" => ["Taslak", "bi-pencil-square"],
                    "revision" => ["Revizyon İstendi", "bi-arrow-repeat"],
                    "rejected" => ["Reddedildi", "bi-x-circle"],
                ];

                $pendingStatusMeta = $pendingStatusLabels[$pendingStatus] ?? [ucfirst($pendingStatus), "bi-info-circle"];

                $moderationFlags = [];

                if (!empty($t["moderation_flags"])) {

                    $decodedModerationFlags = json_decode((string)$t["moderation_flags"], true);

                    if (is_array($decodedModerationFlags)) {

                        $moderationFlags = $decodedModerationFlags;

                    }

                }

                $moderationNote = trim((string)($moderationFlags["note"] ?? ""));

            ?>

            <div class="profile-pending-card ui-card">

                <div class="profile-stack-fill">

                    <a href="<?= routePublicStaticUrl('edit_topic') ?>?id=<?= (int) $t[

    "id"

] ?>" class="profile-pending-title"><?= htmlspecialchars($t["title"]) ?></a>

                    <div class="profile-pending-meta">

                        <span><i class="bi bi-folder2"></i> <?= htmlspecialchars(

                            $t["category"] ?? "Genel",

                        ) ?></span>

                        <span><i class="bi bi-clock-history"></i> <?= date(

                            "d.m.Y H:i",

                            strtotime(

                                $t["updated_at"] ?? ($t["created_at"] ?? "now"),

                            ),

                        ) ?></span>

                    </div>

                    <?php if ($moderationNote !== ""): ?>

                    <div class="profile-moderation-note">

                        <i class="bi bi-chat-left-text"></i>

                        <div>

                            <strong>Moderasyon notu</strong>

                            <span><?= htmlspecialchars($moderationNote) ?></span>

                        </div>

                    </div>

                    <?php endif; ?>

                    <?php if (in_array($pendingStatus, ["revision", "rejected"], true)): ?>

                    <div class="profile-correction-tips">

                        <strong><i class="bi bi-lightbulb"></i> Düzenlemeden önce kontrol edin</strong>

                        <span>Moderasyon notunu karşılayın, çalışan indirme linki ekleyin, kapak/galeri görsellerini yenileyin ve uyumlu oyun sürümünü açık yazın.</span>

                    </div>

                    <div class="profile-resubmit-form">

                        <a href="<?= routePublicStaticUrl('edit_topic') ?>?id=<?= (int) $t["id"] ?>" class="profile-resubmit-action">

                        <i class="bi bi-pencil-square"></i>

                        Düzenle ve tekrar gönder

                        </a>

                    </div>

                    <?php endif; ?>

                </div>

                <span class="profile-pending-badge"><i class="bi <?= htmlspecialchars($pendingStatusMeta[1]) ?>"></i> <?= htmlspecialchars($pendingStatusMeta[0]) ?></span>

            </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if (empty($userTopics)): ?>

        <div class="profile-empty-cta ui-empty"><i class="bi bi-stars"></i><h3>İlk konunu oluşturmaya hazır mısın?</h3><p>Henüz yayınlanmış bir konun görünmüyor. Yeni içerik ekleyerek profilini güçlendirebilir ve toplulukta görünür olmaya başlayabilirsin.</p><a href="<?= routePublicStaticUrl('upload_topic') ?>" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-plus-circle"></i> İlk Konuyu Oluştur</a></div>

    <?php else: ?>

        <?php foreach ($userTopics as $t): ?>

        <div class="profile-topic-item">

            <div class="profile-stack-fill">

                <a href="<?= topicUrlForRow($t) ?>" class="profile-topic-title"><?= htmlspecialchars(

    $t["title"],

) ?></a>

                <div class="profile-topic-meta">

                    <span><i class="bi bi-folder2"></i> <?= htmlspecialchars(

                        $t["category"] ?? "Genel",

                    ) ?></span>

                    <span><i class="bi bi-eye"></i> <?= number_format(

                        (int) ($t["view_count"] ?? 0),

                    ) ?></span>

                    <span><i class="bi bi-download"></i> <?= number_format(

                        (int) ($t["download_count"] ?? 0),

                    ) ?></span>

                    <span><i class="bi bi-calendar3"></i> <?= date(

                        "d.m.Y",

                        strtotime(

                            $t["published_at"] ?? ($t["created_at"] ?? "now"),

                        ),

                    ) ?></span>

                </div>

            </div>

            <a href="<?= routePublicStaticUrl('edit_topic') ?>?id=<?= (int) $t[

    "id"

] ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Düzenle"><i class="bi bi-pencil"></i></a>

        </div>

        <?php endforeach; ?>

        <?= $renderProfilePagination("topics") ?>

    <?php endif; ?>

</div>

<?php endif; ?>



<!-- TAB: Yorumlarım -->

<?php if ($activeTab === "comments"): ?>

<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-chat-dots"></i>Tüm Yorumlarım (<?= number_format(

        $profileTotals["comments"],

    ) ?>)</div>

    <?php if (empty($userComments)): ?>

        <div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-chat-square-text"></i><p>Henüz yorum yapmadınız.</p><a href="<?= $baseUri ?>/index.php">Yorum yapılacak içerik bul</a></div>

    <?php else: ?>

        <?php foreach ($userComments as $c): ?>

        <?php $commentUrl = topicUrl((string) ($c["topic_slug"] ?? ""), (int) ($c["topic_id"] ?? 0)) . "#comment-" . (int) ($c["id"] ?? 0); ?>

        <div class="profile-comment-item">

                    <div class="profile-mini-row-wrap">

                        <a href="<?= htmlspecialchars($commentUrl) ?>" class="profile-link-strong"><i class="bi bi-chat-quote me-1" class="profile-muted"></i><?= htmlspecialchars(

    $c["topic_title"] ?? "Konu",

) ?></a>

                        <span class="profile-small-muted"><?= date(

                            "d.m.Y H:i",

                            strtotime($c["created_at"]),

                        ) ?></span>

                    </div>

                    <div class="profile-comment-body ui-panel__body"><?= nl2br(

                        htmlspecialchars($c["body"] ?? ""),

                    ) ?></div>

                </div>

        <?php endforeach; ?>

        <?= $renderProfilePagination("comments") ?>

    <?php endif; ?>

</div>

<?php endif; ?>



<!-- TAB: Favorilerim -->

<?php if ($activeTab === "favorites"): ?>

<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-heart-fill" class="profile-danger-icon"></i>Favorilerim (<?= number_format(

        $profileTotals["favorites"],

    ) ?>)</div>

    <form method="post" action="<?= $profilePageBaseUrl ?>?tab=favorites" class="collection-create-form">

        <?= csrf_field() ?>

        <input type="hidden" name="action" value="create_collection">

        <div>

            <label for="collection_name">Yeni Koleksiyon</label>

            <input type="text" id="collection_name" name="collection_name" maxlength="120" placeholder="Örn. En iyi kamyon modları" required>

        </div>

        <div>

            <label for="collection_description">Not</label>

            <input type="text" id="collection_description" name="collection_description" maxlength="500" placeholder="İsteğe bağlı kısa açıklama">

        </div>

        <button type="submit"><i class="bi bi-plus-circle"></i> Oluştur</button>

    </form>

    <?php if (!empty($userCollections)): ?>

        <div class="collection-summary-grid ui-grid">

            <?php foreach ($userCollections as $collection): ?>

            <div class="collection-summary-card">

                <div>

                    <strong><?= htmlspecialchars($collection["name"] ?? "Koleksiyon") ?></strong>

                    <span><?= number_format((int) ($collection["item_count"] ?? 0)) ?> içerik</span>

                    <?php if (!empty($collection["description"])): ?>

                        <small><?= htmlspecialchars($collection["description"]) ?></small>

                    <?php endif; ?>

                    <small><?= (string) ($collection["visibility"] ?? "private") === "public" ? "Public profilde gorunur" : "Sadece size gorunur" ?></small>

                </div>

                <div class="collection-summary-actions">

                <form method="post" action="<?= $profilePageBaseUrl ?>?tab=favorites">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="update_collection_visibility">

                    <input type="hidden" name="collection_id" value="<?= (int) $collection["id"] ?>">

                    <input type="hidden" name="visibility" value="<?= (string) ($collection["visibility"] ?? "private") === "public" ? "private" : "public" ?>">

                    <button type="submit" title="<?= (string) ($collection["visibility"] ?? "private") === "public" ? "Gizle" : "Public yap" ?>">

                        <i class="bi <?= (string) ($collection["visibility"] ?? "private") === "public" ? "bi-eye-slash" : "bi-eye" ?>"></i>

                    </button>

                </form>

                <form method="post" action="<?= $profilePageBaseUrl ?>?tab=favorites" data-app-confirm="Bu koleksiyonu silmek istiyor musunuz?" data-app-confirm-title="Koleksiyon silinsin mi?" data-app-confirm-ok="Sil">

                    <?= csrf_field() ?>

                    <input type="hidden" name="action" value="delete_collection">

                    <input type="hidden" name="collection_id" value="<?= (int) $collection["id"] ?>">

                    <button type="submit" title="Koleksiyonu sil"><i class="bi bi-trash"></i></button>

                </form>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if (empty($userFavorites)): ?>

        <div class="profile-empty-cta ui-empty">

            <i class="bi bi-heart"></i>

            <h3>Henüz favori içeriğiniz yok</h3>

            <p>Beğendiğiniz konuları favorilere ekleyerek daha sonra kolayca erişebilirsiniz. Konu sayfalarındaki kalp ikonuna tıklayarak favorilere ekleyebilirsiniz.</p>

            <a href="<?= $baseUri ?>/index.php" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-compass me-1"></i>İçerikleri Keşfet</a>

        </div>

    <?php else: ?>

        <div class="profile-favorites-list">

        <?php foreach ($userFavorites as $fav): ?>

            <div class="profile-topic-item profile-topic-item-compact">

                <div class="profile-stack-fill">

                    <a href="<?= topicUrlForRow($fav) ?>" class="profile-topic-title"><?= htmlspecialchars(

    $fav["title"] ?? "Konu",

) ?></a>

                    <div class="profile-topic-meta">

                        <span><i class="bi bi-folder2"></i> <a href="<?= categoryUrl(

                            $fav["category_slug"] ??

                                slugify($fav["category"] ?? ""),

                        ) ?>" class="profile-link-plain"><?= htmlspecialchars(

    $fav["category"] ?? "Genel",

) ?></a></span>

                        <span><i class="bi bi-person"></i> <a href="<?= $baseUri ?>/index.php?q=<?= urlencode(

    $fav["author"] ?? "",

) ?>" class="profile-link-plain"><?= htmlspecialchars(

    $fav["author"] ?? "-",

) ?></a></span>

                        <span><i class="bi bi-eye"></i> <?= number_format(

                            (int) ($fav["view_count"] ?? 0),

                        ) ?></span>

                        <span><i class="bi bi-heart-fill" class="profile-danger-icon"></i> Favori Eklenme Tarihi: <?= date(

                            "d.m.Y",

                            strtotime($fav["favorited_at"] ?? "now"),

                        ) ?></span>

                    </div>

                </div>

                <div class="favorite-actions">

                    <?php if (!empty($userCollections)): ?>

                    <form method="post" action="<?= $profilePageBaseUrl ?>?tab=favorites" class="collection-picker-form">

                        <?= csrf_field() ?>

                        <input type="hidden" name="action" value="add_to_collection">

                        <input type="hidden" name="topic_id" value="<?= (int) ($fav["id"] ?? 0) ?>">

                        <select name="collection_id" aria-label="Koleksiyon seç">

                            <?php foreach ($userCollections as $collection): ?>

                                <?php

                                $topicId = (int) ($fav["id"] ?? 0);

                                $collectionId = (int) ($collection["id"] ?? 0);

                                $inCollection = !empty($collectionItems[$topicId][$collectionId]);

                                ?>

                                <option value="<?= $collectionId ?>" <?= $inCollection ? "disabled" : "" ?>>

                                    <?= htmlspecialchars($collection["name"] ?? "Koleksiyon") ?><?= $inCollection ? " (ekli)" : "" ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button type="submit" title="Koleksiyona ekle"><i class="bi bi-folder-plus"></i></button>

                    </form>

                    <?php endif; ?>

                    <?php foreach ($userCollections as $collection): ?>

                        <?php

                        $topicId = (int) ($fav["id"] ?? 0);

                        $collectionId = (int) ($collection["id"] ?? 0);

                        if (empty($collectionItems[$topicId][$collectionId])) {

                            continue;

                        }

                        ?>

                        <form method="post" action="<?= $profilePageBaseUrl ?>?tab=favorites" class="collection-remove-form">

                            <?= csrf_field() ?>

                            <input type="hidden" name="action" value="remove_from_collection">

                            <input type="hidden" name="topic_id" value="<?= $topicId ?>">

                            <input type="hidden" name="collection_id" value="<?= $collectionId ?>">

                            <button type="submit" title="<?= htmlspecialchars($collection["name"] ?? "Koleksiyon") ?> koleksiyonundan kaldır">

                                <i class="bi bi-folder-x"></i>

                            </button>

                        </form>

                    <?php endforeach; ?>

                    <form method="post" action="<?= topicUrlForRow($fav) ?>" class="ttb-favorite-form profile-favorite-remove-form" data-topic-id="<?= (int) ($fav[

        "id"

    ] ?? 0) ?>" class="m-0">

                        <?= csrf_field() ?>

                        <input type="hidden" name="action" value="toggle_favorite">

                        <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Favorilerden Kaldır"><i class="bi bi-heart-fill"></i></button>

                    </form>

                </div>

            </div>

        <?php endforeach; ?>

        </div>

        <?= $renderProfilePagination("favorites") ?>

    <?php endif; ?>

</div>

<?php endif; ?>



<!-- TAB: Raporlarım -->

<?php if ($activeTab === "reports"): ?>

<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-flag"></i>Gönderdiğim Raporlar (<?= number_format($profileTotals["reports"]) ?>)</div>

    <div class="profile-report-info">

        <i class="bi bi-bell"></i>

        <span>Rapor durumunuz değiştiğinde bildirim merkezinde ayrıca haber verilir.</span>

    </div>

    <?php if (empty($userReports)): ?>

        <div class="profile-empty profile-empty-action ui-empty">

            <i class="bi bi-flag"></i>

            <p>Henüz rapor göndermediniz.</p>

            <a href="<?= $baseUri ?>/index.php">İçerikleri incele</a>

        </div>

    <?php else: ?>

        <?php

        $reportStatusLabels = [

            "open" => ["Açık", "warning"],

            "reviewing" => ["İnceleniyor", "info"],

            "resolved" => ["Çözüldü", "success"],

            "rejected" => ["Reddedildi", "danger"],

        ];

        $reportReasonLabels = topicReportReasonLabels();

        ?>

        <div class="profile-report-list">

            <?php foreach ($userReports as $report): ?>

                <?php $statusMeta = $reportStatusLabels[$report["status"] ?? "open"] ?? ["Açık", "warning"]; ?>

                <article class="profile-report-card ui-card">

                    <div>

                        <a href="<?= topicUrl((string) ($report["topic_slug"] ?? ""), (int) ($report["topic_id"] ?? 0)) ?>" class="profile-topic-title">

                            <?= htmlspecialchars($report["topic_title"] ?? "Silinmiş konu") ?>

                        </a>

                        <div class="profile-topic-meta">

                            <span><i class="bi bi-folder2"></i> <?= htmlspecialchars($report["category"] ?? "Genel") ?></span>

                            <span><i class="bi bi-calendar3"></i> <?= date("d.m.Y H:i", strtotime((string) ($report["created_at"] ?? "now"))) ?></span>

                            <span><i class="bi bi-flag"></i> <?= htmlspecialchars($reportReasonLabels[$report["reason"]] ?? (string) $report["reason"]) ?></span>

                        </div>

                        <?php if (!empty($report["details"])): ?>

                            <p><?= nl2br(htmlspecialchars((string) $report["details"])) ?></p>

                        <?php endif; ?>

                        <?php if (!empty($report["admin_note"])): ?>

                            <div class="profile-report-note"><strong>Admin notu:</strong> <?= nl2br(htmlspecialchars((string) $report["admin_note"])) ?></div>

                        <?php endif; ?>

                        <small class="profile-report-notify-note"><i class="bi bi-bell"></i> Durum değişirse bildirim merkezine de düşer.</small>

                    </div>

                    <span class="profile-report-status profile-report-status-<?= htmlspecialchars($statusMeta[1]) ?>"><?= htmlspecialchars($statusMeta[0]) ?></span>

                </article>

            <?php endforeach; ?>

        </div>

        <?= $renderProfilePagination("reports") ?>

    <?php endif; ?>

</div>

<?php endif; ?>



<!-- TAB: Aktivite -->

<?php if ($activeTab === "activity"): ?>

<div class="profile-section ui-card ui-section">

    <div class="profile-section-title"><i class="bi bi-clock-history"></i>Aktivite Geçmişi</div>

    <div class="profile-topic-status-filter profile-activity-filter" aria-label="Aktivite filtreleri">

        <?php foreach ($activityFilterOptions as $filterKey => $filterMeta):

            $filterUrl = $profilePageBaseUrl . "?tab=activity";

            if ($filterKey !== "all") {

                $filterUrl .= "&activity_type=" . urlencode((string) $filterKey);

            }

            $isFilterActive = $activityFilter === (string) $filterKey;

            ?>

            <a href="<?= htmlspecialchars($filterUrl) ?>" class="profile-topic-status-filter-link profile-activity-filter-link <?= $isFilterActive ? "active" : "" ?>" <?= $isFilterActive ? 'aria-current="page"' : "" ?>>

                <i class="bi <?= htmlspecialchars((string) ($filterMeta["icon"] ?? "bi-circle")) ?>"></i>

                <span><?= htmlspecialchars((string) ($filterMeta["label"] ?? $filterKey)) ?></span>

                <strong><?= number_format((int) ($activityFilterCounts[$filterKey] ?? 0)) ?></strong>

            </a>

        <?php endforeach; ?>

    </div>

    <?php if (empty($userActivity)): ?>

        <div class="profile-empty profile-empty-action ui-empty"><i class="bi bi-hourglass"></i><p><?= $activityFilter === "all" ? "Henüz aktivite yok." : "Bu filtrede aktivite yok." ?></p><a href="<?= $activityFilter === "all" ? $profilePageBaseUrl . "?tab=settings" : $profilePageBaseUrl . "?tab=activity" ?>"><?= $activityFilter === "all" ? "Profil ayarlarına git" : "Tüm aktiviteler" ?></a></div>

    <?php else: ?>

        <?php $activityTones = [

            "login" => "is-success",

            "create" => "is-primary",

            "update" => "is-warning",

            "delete" => "is-danger",

            "comment" => "is-info",

            "register" => "is-primary",

            "password" => "is-warning",

            "avatar" => "is-danger",

            "profile" => "is-info",

        ]; ?>

        <div class="profile-activity-list">

        <?php foreach ($userActivity as $act):



            $dotTone = "is-muted";

            foreach ($activityTones as $k => $className) {

                if (str_contains($act["action"], $k)) {

                    $dotTone = $className;

                    break;

                }

            }

            $actionLabel = profileActivitySentence($act);

            $activityBadgeLabel = profileActivityTitle($act);

            $detailsLabel = profileActivityDisplayDetail($act);

            $activityUrl = profileActivityTargetUrl($act);

            ?>

            <div class="profile-activity-item">

                <div class="profile-activity-dot <?= $dotTone ?>"></div>

                <div class="profile-activity-main">

                    <div class="profile-activity-title">

                        <?php if ($activityUrl !== ""): ?>

                            <a href="<?= htmlspecialchars($activityUrl) ?>" class="profile-activity-title-link"><?= htmlspecialchars($actionLabel) ?></a>

                        <?php else: ?>

                            <span><?= htmlspecialchars($actionLabel) ?></span>

                        <?php endif; ?>

                        <span class="profile-activity-badge"><i class="bi bi-lightning-charge"></i><?= htmlspecialchars(

                            $activityBadgeLabel,

                        ) ?></span>

                    </div>

                    <?php if ($detailsLabel !== ""): ?>

                    <div class="profile-activity-detail"><?= htmlspecialchars(

                            $detailsLabel,

                        ) ?></div>

                    <?php endif; ?>

                </div>

                <div class="profile-activity-meta">

                    <span><?= date(

                        "d.m.Y",

                        strtotime($act["created_at"]),

                    ) ?></span>

                    <strong><?= date(

                        "H:i",

                        strtotime($act["created_at"]),

                    ) ?></strong>

                </div>

            </div>

        <?php

        endforeach; ?>

        </div>

        <?= $renderProfilePagination("activity") ?>

    <?php endif; ?>

</div>

<?php endif; ?>



<!-- TAB: Ayarlar -->

<?php if ($activeTab === "settings"): ?>

<section class="ui-section profile-settings-section">

<div class="row g-4 mb-4">

    <div class="col-lg-8">

        <section class="ui-card profile-section h-100 mb-0 ui-section">

        <div class="profile-section-title"><i class="bi bi-person-gear"></i>Profil Bilgileri</div>

        <form method="post" action="<?= $profilePageBaseUrl ?>?tab=settings">

            <?= csrf_field() ?>

            <input type="hidden" name="action" value="update_profile">



            <div class="profile-form-row">

                <div class="profile-form-group">

                    <label for="pf_username">Kullanici Adi</label>

                    <input type="text" id="pf_username" name="username" value="<?= htmlspecialchars(

                        (string) ($user["username"] ?? ''),

                    ) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]{3,30}" autocomplete="username">

                </div>

                <div class="profile-form-group">

                    <label>E-posta <span class="profile-form-hint">(değiştirilemez)</span></label>

                    <input type="email" value="<?= htmlspecialchars(

                        $user["email"],

                    ) ?>" disabled class="profile-disabled-input">

                </div>

            </div>



            <div class="profile-form-group">

                <label for="pf_bio">Hakkımda</label>

                <textarea id="pf_bio" name="bio" rows="3" maxlength="500" placeholder="Kendinizi kısaca tanıtın..."><?= htmlspecialchars(

                    $user["bio"] ?? "",

                ) ?></textarea>

            </div>



            <div class="profile-form-group">

                <label for="pf_location"><i class="bi bi-geo-alt me-1"></i>Konum</label>

                <input type="text" id="pf_location" name="location" value="<?= htmlspecialchars(

                    $user["location"] ?? "",

                ) ?>" placeholder="İstanbul, Türkiye" maxlength="255">

            </div>



            <div class="profile-section-title profile-section-title-offset"><i class="bi bi-share"></i>Sosyal Bağlantılar</div>



            <div class="profile-form-row">

                <div class="profile-form-group">

                    <label><i class="bi bi-twitter-x me-1"></i>Twitter / X</label>

                    <div class="profile-social-input">

                        <span class="prefix">x.com/</span>

                        <input type="text" name="social_twitter" value="<?= htmlspecialchars(

                            $user["social_twitter"] ?? "",

                        ) ?>" placeholder="kullanici" maxlength="255">

                    </div>

                </div>

                <div class="profile-form-group">

                    <label><i class="bi bi-discord me-1"></i>Discord</label>

                    <input type="text" name="social_discord" value="<?= htmlspecialchars(

                        $user["social_discord"] ?? "",

                    ) ?>" placeholder="kullanici#0000" maxlength="255">

                </div>

            </div>



            <div class="profile-form-row">

                <div class="profile-form-group">

                    <label><i class="bi bi-globe me-1"></i>Web Sitesi</label>

                    <input type="url" name="website" value="<?= htmlspecialchars(

                        $user["website"] ?? "",

                    ) ?>" placeholder="https://ornek.com" maxlength="255">

                </div>

                <div class="profile-form-group">

                    <label><i class="bi bi-github me-1"></i>GitHub</label>

                    <div class="profile-social-input">

                        <span class="prefix">github.com/</span>

                        <input type="text" name="social_github" value="<?= htmlspecialchars(

                            $user["social_github"] ?? "",

                        ) ?>" placeholder="kullanici" maxlength="255">

                    </div>

                </div>

            </div>



            <div class="profile-section-title profile-section-title-offset"><i class="bi bi-eye"></i>Profil Gizliliği</div>

            <div class="profile-privacy-grid ui-grid">

                <?php

                $privacyOptions = [

                    ["public_profile", "pp_profile", "bi-person-badge", "Profil sayfam yayında olsun", "Kapalıyken public profiliniz ziyaretçilere gösterilmez.", 1],

                    ["public_show_topics", "pp_topics", "bi-collection", "Konularım görünsün", "Public profilde yayınlanmış içerikleriniz listelenir.", 1],

                    ["public_show_comments", "pp_comments", "bi-chat-square-text", "Yorum sayım görünsün", "Profil özetinde yorum aktiviteniz paylaşılır.", 0],

                    ["public_show_socials", "pp_socials", "bi-link-45deg", "Sosyal bağlantılarım görünsün", "Web sitesi ve sosyal hesaplarınız public profilde görünür.", 1],

                ];

                ?>

                <?php foreach ($privacyOptions as [$field, $id, $icon, $title, $description, $defaultValue]): ?>

                    <?php $isChecked = (int) ($user[$field] ?? $defaultValue) === 1; ?>

                    <label class="profile-privacy-card ui-card" for="<?= htmlspecialchars($id) ?>">

                        <span class="profile-privacy-icon"><i class="bi <?= htmlspecialchars($icon) ?>"></i></span>

                        <span class="profile-privacy-copy">

                            <strong><?= htmlspecialchars($title) ?></strong>

                            <small><?= htmlspecialchars($description) ?></small>

                        </span>

                        <span class="profile-privacy-switch">

                            <input type="checkbox" role="switch" name="<?= htmlspecialchars($field) ?>" id="<?= htmlspecialchars($id) ?>" value="1" <?= $isChecked ? "checked" : "" ?>>

                            <span aria-hidden="true"></span>

                        </span>

                    </label>

                <?php endforeach; ?>

            </div>



            <button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold mt-2"><i class="bi bi-check-lg me-1"></i>Kaydet</button>

        </form>

        </section>

    </div>



    <div class="col-lg-4">

        <section class="ui-card profile-section ui-section">

            <div class="profile-section-title"><i class="bi bi-camera"></i>Profil Fotoğrafı</div>

            <form method="post" action="<?= $profilePageBaseUrl ?>?tab=settings" enctype="multipart/form-data" id="profileAvatarForm" class="profile-avatar-form">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="upload_avatar">

                <label class="profile-avatar-upload" for="avatar_input" data-avatar-upload>

                    <div class="profile-avatar-preview default-avatar" data-avatar-preview>

                        <img src="<?= htmlspecialchars($profile_private_avatar) ?>" alt="" data-avatar-img data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($profile_private_avatar_fallback) ?>">
                    </div>

                    <div class="profile-avatar-upload-copy">

                        <div class="profile-upload-title">Fotoğraf Yükle</div>

                        <div class="profile-small-muted">JPG, PNG, WebP · Maks 2 MB</div>
                        <span class="profile-avatar-upload-action"><i class="bi bi-upload"></i><span data-avatar-action-text>Dosya seç</span></span>

                        <span class="profile-avatar-selected" data-avatar-selected>Henüz yeni dosya seçilmedi.</span>

                    </div>

                    <input type="file" id="avatar_input" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="profile-file-input-hidden" data-avatar-input>

                </label>

                <div class="profile-avatar-actions">

                    <button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold" data-avatar-submit disabled><i class="bi bi-check-lg me-1"></i>Kaydet</button>

                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-avatar-reset hidden><i class="bi bi-arrow-counterclockwise me-1"></i>Temizle</button>

                </div>

            </form>

        </section>



        <section class="ui-card profile-section ui-section">

            <div class="profile-section-title"><i class="bi bi-info-circle"></i>Hesap Bilgileri</div>

            <div class="profile-info-list">

                <div><strong>Kayıt:</strong> <?= date(

                    "d.m.Y H:i",

                    strtotime($user["created_at"] ?? "now"),

                ) ?></div>

                <div><strong>Son Güncelleme:</strong> <?= date(

                    "d.m.Y H:i",

                    strtotime($user["updated_at"] ?? "now"),

                ) ?></div>

                <div><strong>Kullanıcı Grubu:</strong> <?= profileGroupBadge(
                    $profile_group_slug,
                    $profile_group_name,
                ) ?></div>
                <div><strong>Durum:</strong>
                    <span class="badge-modern <?= htmlspecialchars($profile_status_badge_class, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($profile_status_label, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div><strong>Konu:</strong> <?= $stats[

                    "topics"

                ] ?> · <strong>Yorum:</strong> <?= $stats["comments"] ?></div>
            </div>

        </section>

    </div>

</div>

</section>

<?php endif; ?>



<!-- TAB: Güvenlik -->

<?php if ($activeTab === "security"): ?>

<section class="ui-section profile-security-section">

<div class="row g-4 mb-4">

    <div class="col-lg-6">

        <section class="ui-card profile-section h-100 mb-0 ui-section">

        <div class="profile-section-title"><i class="bi bi-key"></i>Şifre Değiştir</div>

        <?php if ($pwSuccess): ?>

            <div class="ui-admin-alert ui-admin-alert-success profile-alert-sm ui-alert ui-alert--success"><?= htmlspecialchars(

                $pwSuccess,

            ) ?></div>

        <?php endif; ?>

        <?php if ($pwError): ?>

            <div class="ui-admin-alert ui-admin-alert-danger profile-alert-sm ui-alert ui-alert--error"><?= htmlspecialchars(

                $pwError,

            ) ?></div>

        <?php endif; ?>

        <form method="post" action="<?= $profilePageBaseUrl ?>?tab=security" id="profilePasswordForm">

            <?= csrf_field() ?>

            <input type="hidden" name="action" value="change_password">

            <div class="profile-form-group">

                <label for="pw_current">Mevcut Şifre</label>

                <input type="password" id="pw_current" name="current_password" required autocomplete="current-password">

            </div>

            <div class="profile-form-group">

                <label for="pw_new">Yeni Şifre</label>

                <input type="password" id="pw_new" name="new_password" required minlength="<?= $passwordMinLength ?>" autocomplete="new-password" data-password-strength data-password-confirm="#pw_confirm" data-password-require-uppercase="<?= $passwordPolicy["require_uppercase"] ? "1" : "0" ?>" data-password-require-numbers="<?= $passwordPolicy["require_numbers"] ? "1" : "0" ?>" data-password-require-special="<?= $passwordPolicy["require_special"] ? "1" : "0" ?>">

                <small class="profile-form-hint"><?= htmlspecialchars($passwordPolicyHint) ?></small>

            </div>

            <div class="profile-form-group">

                <label for="pw_confirm">Yeni Şifre (Tekrar)</label>

                <input type="password" id="pw_confirm" name="new_password_confirm" required minlength="<?= $passwordMinLength ?>" autocomplete="new-password">

            </div>

            <button type="submit" class="ui-admin-btn ui-admin-btn-warning fw-bold"><i class="bi bi-shield-check me-1"></i>Şifreyi Güncelle</button>

        </form>

        </section>

    </div>

    <div class="col-lg-6">

        <section class="ui-card profile-section ui-section">

            <div class="profile-section-title"><i class="bi bi-shield-exclamation"></i>Güvenlik Durumu</div>

            <div class="profile-check-list">

                <div class="profile-check-row">

                    <i class="bi bi-check-circle-fill" class="profile-success-icon"></i>

                    <span>Güvenli oturum çerezi</span>

                </div>

                <div class="profile-check-row">

                    <i class="bi bi-check-circle-fill" class="profile-success-icon"></i>

                    <span>Şifre: bcrypt ile hashlenmiş</span>

                </div>

                <div class="profile-check-row">

                    <?php if (!empty($user["email_verified_at"])): ?>

                        <i class="bi bi-check-circle-fill" class="profile-success-icon"></i>

                        <span>E-posta doğrulanmış</span>

                    <?php else: ?>

                        <i class="bi bi-exclamation-circle-fill" class="profile-warning-icon"></i>

                        <span>E-posta doğrulanmamış</span>

                    <?php endif; ?>

                </div>

            </div>

        </section>

        <section class="ui-card profile-section ui-section">

            <div class="profile-section-title"><i class="bi bi-person-check"></i>Aktif Oturum</div>

            <div class="profile-session-card ui-card">

                <div>

                    <strong>Bu cihaz</strong>

                    <span>Başlangıç: <?= !empty($_SESSION["_auth_login_time"]) ? date("d.m.Y H:i", (int) $_SESSION["_auth_login_time"]) : "Bilinmiyor" ?></span>

                </div>

                <div>

                    <strong>Son etkinlik</strong>

                    <span><?= !empty($_SESSION["_auth_last_activity"]) ? date("d.m.Y H:i", (int) $_SESSION["_auth_last_activity"]) : "Şimdi" ?></span>

                </div>

                <div>

                    <strong>Oturum modu</strong>

                    <span><?= !empty($_SESSION["_auth_remember_session"]) ? "Bu cihazda hatırlanıyor" : "Standart süreli oturum" ?></span>

                </div>

            </div>

            <form method="post" action="<?= $profilePageBaseUrl ?>?tab=security" class="profile-session-logout-form"

                  data-app-confirm="Bu hesaba bağlı diğer tüm cihaz ve tarayıcılardaki oturumlar kapatılacak. Bu cihazdaki oturumunuz açık kalır. Devam etmek istiyor musunuz?"

                  data-app-confirm-title="Tüm cihazlardan çıkış yapılsın mı?"

                  data-app-confirm-ok="Evet, hepsinden çıkış yap"

                  data-app-confirm-icon="bi-box-arrow-right">

                <?= csrf_field() ?>

                <input type="hidden" name="action" value="logout_all_devices">

                <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-danger w-100">

                    <i class="bi bi-box-arrow-right me-1"></i>Tüm Cihazlardan Çıkış Yap

                </button>

                <p class="profile-session-logout-hint"><i class="bi bi-info-circle me-1"></i>Hesabınızın açık olduğu diğer tüm cihazlardaki oturumlar sonlandırılır.</p>

            </form>

        </section>

        <section class="ui-card profile-section ui-section">

            <div class="profile-section-title"><i class="bi bi-clock-history"></i>Son Güvenlik Olayları</div>

            <?php

            $secEvents = array_filter(

                $userActivity,

                fn($a) => str_contains($a["action"], "login") ||

                    str_contains($a["action"], "password"),

            );

            $secEvents = array_slice($secEvents, 0, 5);

            if (empty($secEvents)): ?>

                <div class="profile-empty-mini ui-empty">Kayıt yok</div>

            <?php else: ?>

                <?php foreach ($secEvents as $se):

                    $securityDotTone = str_contains($se["action"], "login") ? "is-success" : "is-warning";

                    $securityActivityUrl = profileActivityTargetUrl($se);

                    ?>

                <div class="profile-activity-item">

                    <div class="profile-activity-dot <?= $securityDotTone ?>"></div>

                    <div class="profile-activity-copy">

                        <strong>

                            <?php if ($securityActivityUrl !== ""): ?>

                                <a href="<?= htmlspecialchars($securityActivityUrl) ?>" class="profile-activity-title-link"><?= htmlspecialchars(profileActivitySentence($se)) ?></a>

                            <?php else: ?>

                                <?= htmlspecialchars(profileActivitySentence($se)) ?>

                            <?php endif; ?>

                        </strong>

                        <?php if (profileActivityIsLogin($se)): ?>

                            <span class="profile-muted"><?= htmlspecialchars(profileActivityLoginDetail($se)) ?></span>

                        <?php endif; ?>

                    </div>

                    <span class="profile-date-muted"><?= date(

                        "d.m H:i",

                        strtotime($se["created_at"]),

                    ) ?></span>

                </div>

                <?php endforeach; ?>

            <?php endif;

            ?>

        </section>

    </div>

</div>

</section>

<?php endif; ?>



</div><!-- .profile-single-column -->

<?php endif; ?>



</div>



<script src="<?= asset_url('assets/js/profile-page.js', $baseUri) ?>" defer></script>



<?php require_once $projectRoot . "/includes/public-footer.php"; ?>




