<?php

declare(strict_types=1);
require_once $projectRoot . "/includes/init.php";
require_once $projectRoot . "/admin/helpers.php";
requireAuth();
if ($pdo) {
    ensureAdminSchema($pdo);
}

$pageTitle = "Mod Yükle";
$categoryOptions = getAdminCategoryOptions($pdo);
$settings = function_exists("getAdminSettings") && $pdo ? getAdminSettings($pdo) : [];
$userUploadEnabled = (string) ($settings["user_upload_enabled"] ?? "1") === "1";
$userUploadRequiresApproval = (string) ($settings["user_upload_require_approval"] ?? "1") === "1";

function uploadTopicIsAjaxRequest(): bool
{
    return strtolower((string) ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "")) === "xmlhttprequest"
        || str_contains(strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? "")), "application/json");
}

function uploadTopicRespond(bool $success, string $message, int $statusCode = 200, array $data = []): void
{
    if (uploadTopicIsAjaxRequest()) {
        http_response_code($statusCode);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(array_merge([
            "success" => $success,
            "message" => $message,
        ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $_SESSION[$success ? "_flash_success" : "_flash_error"] = $message;
    $uploadTopicUrl = function_exists('routePublicStaticUrl')
        ? routePublicStaticUrl('upload_topic')
        : (($GLOBALS["baseUri"] ?? "") . "/upload-topic.php");
    header("Location: " . $uploadTopicUrl);
    exit();
}

function uploadTopicEventDispatcher(string $projectRoot): ?\App\Core\Events\Dispatcher
{
    try {
        return \App\Core\Bootstrap\Boot::container($projectRoot)->get(\App\Core\Events\Dispatcher::class);
    } catch (Throwable $exception) {
        if (function_exists('appLogException')) {
            appLogException($exception, ['source' => 'TopicSubmissionService::dispatcher']);
        } else {
            error_log($exception->getMessage());
        }

        return null;
    }
}

function uploadTopicCenterContent(string $content): string
{
    $content = trim($content);
    if ($content === "") {
        return "";
    }

    if (preg_match('/text-align\s*:\s*center/i', $content)) {
        return $content;
    }

    return '<div class="content-align-center">' . $content . '</div>';
}

function uploadTopicBool(array $settings, string $key, string $default = "0"): bool
{
    return (string) ($settings[$key] ?? $default) === "1";
}

function uploadTopicInt(array $settings, string $key, int $default = 0, int $min = 0): int
{
    return max($min, (int) ($settings[$key] ?? $default));
}

function uploadTopicList(array $settings, string $key, string $default = ""): array
{
    return array_values(array_filter(array_map(static fn($value) => strtolower(trim((string) $value)), explode(",", (string) ($settings[$key] ?? $default)))));
}

function uploadTopicApplyContentAlignment(string $content, string $align): string
{
    $align = in_array($align, ["left", "center", "right"], true) ? $align : "center";
    $content = trim($content);
    if ($content === "") {
        return "";
    }
    if (preg_match('/(?:text-align\s*:\s*(left|center|right)|class\s*=\s*["\'][^"\']*(?:content-align|ql-align)-(?:left|center|right))/i', $content)) {
        return $content;
    }
    return '<div class="content-align-' . $align . '">' . $content . '</div>';
}

function uploadTopicHasDownloadLink(string $links): bool
{
    foreach (preg_split('/\R+/', trim($links)) ?: [] as $line) {
        $parts = array_map("trim", explode("|", $line, 2));
        $url = $parts[1] ?? ($parts[0] ?? "");
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }
    }
    return false;
}

function uploadTopicVideoHostAllowed(string $url, array $allowedHosts): bool
{
    if ($url === "" || empty($allowedHosts)) {
        return true;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === "") {
        return false;
    }
    foreach ($allowedHosts as $allowedHost) {
        if ($host === $allowedHost || str_ends_with($host, "." . $allowedHost)) {
            return true;
        }
    }
    return false;
}

function uploadTopicValidateImageFile(array $file, string $label, array $settings, int $maxSizeMb): ?string
{
    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowedExt = uploadTopicList($settings, "user_upload_allowed_image_ext", "jpg,jpeg,png,webp");
    if (empty($allowedExt)) {
        $allowedExt = ["jpg", "jpeg", "png", "webp"];
    }
    $extension = strtolower(pathinfo((string) ($file["name"] ?? ""), PATHINFO_EXTENSION));
    if ($extension === "" || !in_array($extension, $allowedExt, true)) {
        return $label . " için izinli uzantılar: " . implode(", ", $allowedExt) . ".";
    }

    $maxBytes = max(1, $maxSizeMb) * 1024 * 1024;
    if ((int) ($file["size"] ?? 0) > $maxBytes) {
        return $label . " boyutu en fazla " . max(1, $maxSizeMb) . " MB olabilir.";
    }

    $info = @getimagesize((string) ($file["tmp_name"] ?? ""));
    if (!$info) {
        return $label . " geçerli bir görsel değil.";
    }

    [$width, $height] = $info;
    $minWidth = uploadTopicInt($settings, "user_upload_image_min_width", 0);
    $minHeight = uploadTopicInt($settings, "user_upload_image_min_height", 0);
    $maxWidth = uploadTopicInt($settings, "user_upload_image_max_width", 0);
    $maxHeight = uploadTopicInt($settings, "user_upload_image_max_height", 0);
    if ($minWidth > 0 && $width < $minWidth) {
        return $label . " genişliği minimum " . $minWidth . " px olmalıdır.";
    }
    if ($minHeight > 0 && $height < $minHeight) {
        return $label . " yüksekliği minimum " . $minHeight . " px olmalıdır.";
    }
    if ($maxWidth > 0 && $width > $maxWidth) {
        return $label . " genişliği maksimum " . $maxWidth . " px olmalıdır.";
    }
    if ($maxHeight > 0 && $height > $maxHeight) {
        return $label . " yüksekliği maksimum " . $maxHeight . " px olmalıdır.";
    }

    return null;
}

if (!$userUploadEnabled) {
    http_response_code(403);
    require_once $projectRoot . "/includes/public-header.php";
    echo '<div class="container py-5 ui-container"><div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">Kullanıcı mod yükleme özelliği şu anda kapalı.</div></div>';
    require_once $projectRoot . "/includes/public-footer.php";
    exit();
}

if ($pdo && function_exists('userHasPermission') && !userHasPermission($pdo, (int)$_SESSION['_auth_user_id'], 'topics.create')) {
    http_response_code(403);
    require_once $projectRoot . "/includes/public-header.php";
    echo '<div class="container py-5 ui-container"><div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">Konu oluşturmak için gerekli izin hesabınıza tanımlanmamış.</div></div>';
    require_once $projectRoot . "/includes/public-footer.php";
    exit();
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    if (!verify_csrf_token($_POST["_token"] ?? "")) {
        uploadTopicRespond(false, "Güvenlik hatası. Sayfayı yenileyip tekrar deneyin.", 403);
    }

    $submitToken = (string) ($_POST["upload_submit_token"] ?? "");
    $sessionSubmitToken = (string) ($_SESSION["upload_topic_submit_token"] ?? "");
    if ($submitToken === "" || $sessionSubmitToken === "" || !hash_equals($sessionSubmitToken, $submitToken)) {
        uploadTopicRespond(false, "Bu gönderim zaten işlendi veya oturum süresi doldu. Lütfen sayfayı yenileyin.", 409);
    }

    $title = trim($_POST["title"] ?? "");
    $content = uploadTopicApplyContentAlignment((string) ($_POST["content"] ?? ""), (string) ($settings["user_upload_default_content_align"] ?? "center"));
    $authorTopic = trim($_POST["author_topic"] ?? "");
    $topicVersion = trim($_POST["topic_version"] ?? "");
    $categoryId = (int) ($_POST["category_id"] ?? 0);
    $defaultStatus = (string) ($settings["user_upload_default_status"] ?? "published");
    $status = $userUploadRequiresApproval ? "draft" : (in_array($defaultStatus, ["published", "draft"], true) ? $defaultStatus : "published");
    $topicDownloadLinks = trim($_POST["topic_download_links"] ?? "");
    $videoUrl = trim($_POST["topic_video_url"] ?? "");

    $requireCover =
        (string) ($settings["user_upload_require_cover"] ?? "1") === "1";
    $requireGallery =
        (string) ($settings["user_upload_require_gallery"] ?? "1") === "1";
    $requireAuthor = uploadTopicBool($settings, "user_upload_require_author");
    $requireVersion = uploadTopicBool($settings, "user_upload_require_version");
    $requireDownloadLink = uploadTopicBool($settings, "user_upload_require_download_link");
    $allowVideoUrl = uploadTopicBool($settings, "user_upload_allow_video_url", "1");
    $maxImages = uploadTopicInt($settings, "user_upload_max_images", 10, 1);
    $coverMaxSizeMb = uploadTopicInt($settings, "user_upload_cover_max_size_mb", 10, 1);
    $galleryMaxSizeMb = uploadTopicInt($settings, "user_upload_gallery_max_size_mb", 10, 1);
    $attachmentMaxSizeMb = uploadTopicInt($settings, "user_upload_max_size_mb", 50, 1);
    $minTitleLength = uploadTopicInt($settings, "topic_min_title_length", uploadTopicInt($settings, "user_upload_min_title_length", 3, 0), 0);
    $maxTitleLength = uploadTopicInt($settings, "user_upload_max_title_length", 150, 1);
        $minContentLength = uploadTopicInt($settings, "user_upload_min_content_length", 10, 0);
    $requireExcerpt = uploadTopicBool($settings, "topic_require_excerpt", "1");
    if ($requireExcerpt && $minContentLength <= 0) {
        $minContentLength = 10;
    }
    $authorId = (int) ($_SESSION["_auth_user_id"] ?? 0);

    if ($minTitleLength > 0 && mb_strlen($title, "UTF-8") < $minTitleLength) {
        uploadTopicRespond(false, "Mod başlığı en az " . $minTitleLength . " karakter olmalıdır.", 422);
    }
    if ($maxTitleLength > 0 && mb_strlen($title, "UTF-8") > $maxTitleLength) {
        uploadTopicRespond(false, "Mod başlığı en fazla " . $maxTitleLength . " karakter olabilir.", 422);
    }
    if ($minContentLength > 0 && mb_strlen(trim(strip_tags($content)), "UTF-8") < $minContentLength) {
        uploadTopicRespond(false, "Mod açıklaması en az " . $minContentLength . " karakter olmalıdır.", 422);
    }
    if ($requireAuthor && $authorTopic === "") {
        uploadTopicRespond(false, "Yapımcı alanı zorunludur.", 422);
    }
    if ($requireVersion && $topicVersion === "") {
        uploadTopicRespond(false, "Oyun sürümü alanı zorunludur.", 422);
    }
    if ($requireDownloadLink && !uploadTopicHasDownloadLink($topicDownloadLinks)) {
        uploadTopicRespond(false, "En az bir geçerli indirme bağlantısı eklemelisiniz.", 422);
    }
    if (!$allowVideoUrl) {
        $videoUrl = "";
    } elseif ($videoUrl !== "" && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        uploadTopicRespond(false, "Video URL geçerli değil.", 422);
    } elseif ($videoUrl !== "" && !uploadTopicVideoHostAllowed($videoUrl, uploadTopicList($settings, "user_upload_allowed_video_hosts", "youtube.com,youtu.be,vimeo.com"))) {
        uploadTopicRespond(false, "Video URL izinli sağlayıcılardan biri olmalıdır.", 422);
    }

    if ($requireCover && empty($_FILES["topic_first_image_file"]["name"])) {
        uploadTopicRespond(false, "Kapak görseli yüklemek zorunludur.", 422);
    }
    if ($requireGallery && empty($_FILES["topic_images_files"]["name"][0])) {
        uploadTopicRespond(false, "En az 1 adet galeri resmi yüklemek zorunludur.", 422);
    }

    if (!empty($_FILES["topic_first_image_file"]["name"])) {
        $imageError = uploadTopicValidateImageFile($_FILES["topic_first_image_file"], "Kapak görseli", $settings, $coverMaxSizeMb);
        if ($imageError !== null) {
            uploadTopicRespond(false, $imageError, 422);
        }
    }
    $galleryNames = $_FILES["topic_images_files"]["name"] ?? [];
    $galleryCount = is_array($galleryNames) ? count(array_filter($galleryNames, static fn($name) => (string) $name !== "")) : 0;
    if ($galleryCount > $maxImages) {
        uploadTopicRespond(false, "En fazla " . $maxImages . " adet galeri görseli yükleyebilirsiniz.", 422);
    }
    for ($i = 0; $i < $galleryCount; $i++) {
        $singleGalleryFile = [
            "name" => $_FILES["topic_images_files"]["name"][$i] ?? "",
            "type" => $_FILES["topic_images_files"]["type"][$i] ?? "",
            "tmp_name" => $_FILES["topic_images_files"]["tmp_name"][$i] ?? "",
            "error" => $_FILES["topic_images_files"]["error"][$i] ?? UPLOAD_ERR_NO_FILE,
            "size" => $_FILES["topic_images_files"]["size"][$i] ?? 0,
        ];
        $imageError = uploadTopicValidateImageFile($singleGalleryFile, "Galeri görseli", $settings, $galleryMaxSizeMb);
        if ($imageError !== null) {
            uploadTopicRespond(false, $imageError, 422);
        }
    }

    if ($pdo && $authorId > 0) {
        $hourlyLimit = uploadTopicInt($settings, "user_upload_hourly_limit", 0);
        if ($hourlyLimit > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$authorId]);
            if ((int) $stmt->fetchColumn() >= $hourlyLimit) {
                uploadTopicRespond(false, "Saatlik mod gönderim limitine ulaştınız.", 429);
            }
        }
        $dailyLimit = uploadTopicInt($settings, "user_upload_daily_limit", 0);
        if ($dailyLimit > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute([$authorId]);
            if ((int) $stmt->fetchColumn() >= $dailyLimit) {
                uploadTopicRespond(false, "Günlük mod gönderim limitine ulaştınız.", 429);
            }
        }
        if (uploadTopicBool($settings, "user_upload_block_duplicate_titles", "1") && $title !== "") {
            $stmt = $pdo->prepare("SELECT id FROM topics WHERE author_id = ? AND LOWER(title) = LOWER(?) LIMIT 1");
            $stmt->execute([$authorId, $title]);
            if ($stmt->fetchColumn()) {
                uploadTopicRespond(false, "Aynı başlıkla daha önce konu göndermişsiniz.", 409);
            }
        }
    }

    if (!empty($_FILES["attachment"]["name"]) && (int) ($_FILES["attachment"]["size"] ?? 0) > ($attachmentMaxSizeMb * 1024 * 1024)) {
        uploadTopicRespond(false, "Mod dosyası en fazla " . $attachmentMaxSizeMb . " MB olabilir.", 422);
    }

    if ($pdo && $categoryId <= 0 && !empty($categoryOptions)) {
        $categoryId = (int) $categoryOptions[0]["id"];
    }

    $moderationDecision = function_exists("adminContentModerationDecision")
        ? adminContentModerationDecision($settings, $title, $content)
        : ["matched" => false, "action" => "none", "message" => "", "flags" => null];
    if (!empty($moderationDecision["matched"]) && ($moderationDecision["action"] ?? "") === "reject") {
        uploadTopicRespond(false, (string) ($moderationDecision["message"] ?? "İçerik moderasyonu nedeniyle gönderim reddedildi."), 422);
    }
    $moderationFlagsJson = null;
    if (!empty($moderationDecision["flags"])) {
        $encodedModerationFlags = json_encode($moderationDecision["flags"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $moderationFlagsJson = is_string($encodedModerationFlags) ? $encodedModerationFlags : null;
    }
    if (!empty($moderationDecision["matched"]) && ($moderationDecision["action"] ?? "") === "draft") {
        $status = "draft";
    }

    if ($pdo && $title !== "" && $content !== "" && $categoryId > 0) {
        try {
            $authorId = (int) $_SESSION["_auth_user_id"];
            $submissionService = new \App\Modules\TopicWorkflow\Services\TopicSubmissionService(
                uploadTopicEventDispatcher($projectRoot),
                $projectRoot,
            );
            $submissionResult = $submissionService->submit($pdo, [
                "category_id" => $categoryId,
                "author_id" => $authorId,
                "title" => $title,
                "author_topic" => $authorTopic,
                "topic_version" => $topicVersion,
                "content" => $content,
                "status" => $status,
                "moderation_flags_json" => $moderationFlagsJson,
                "topic_download_links" => $topicDownloadLinks,
                "video_url" => $videoUrl,
                "max_images" => $maxImages,
            ], [
                "attachment" => $_FILES["attachment"] ?? null,
                "cover" => $_FILES["topic_first_image_file"] ?? null,
                "gallery" => $_FILES["topic_images_files"] ?? null,
            ]);

            $topicId = (int) ($submissionResult["topic_id"] ?? 0);
            $status = (string) ($submissionResult["status"] ?? $status);

            unset($_SESSION["upload_topic_submit_token"]);
            $uploadRedirectStatus = $status === "draft" ? "draft" : "published";
            $uploadSuccessMessage = $status === "draft"
                ? "Modunuz taslak olarak kaydedildi. Profil > Konularım ekranından takip edebilir, revizyon istenirse aynı yerden düzenleyebilirsiniz."
                : "Modunuz yayına alındı.";
            uploadTopicRespond(true, $uploadSuccessMessage, 200, [
                "redirect" => $baseUri . "/profile.php?tab=topics&topic_status=" . $uploadRedirectStatus . "&submitted=1",
                "topic_id" => $topicId,
            ]);
        } catch (Throwable $e) {
            uploadTopicRespond(false, safeErrorMessage(
                $e,
                "Mod yüklenirken bir hata oluştu.",
            ), 500);
        }
    } else {
        uploadTopicRespond(false, "Başlık, kategori ve içerik alanları zorunludur.", 422);
    }
}

$errorMsg = $_SESSION["_flash_error"] ?? "";
$successMsg = $_SESSION["_flash_success"] ?? "";
unset($_SESSION["_flash_error"], $_SESSION["_flash_success"]);

$settings =
    function_exists("getAdminSettings") && $pdo ? getAdminSettings($pdo) : [];
$requireCover =
    (string) ($settings["user_upload_require_cover"] ?? "1") === "1";
$requireGallery =
    (string) ($settings["user_upload_require_gallery"] ?? "1") === "1";
$requireAuthor = uploadTopicBool($settings, "user_upload_require_author");
$requireVersion = uploadTopicBool($settings, "user_upload_require_version");
$requireDownloadLink = uploadTopicBool($settings, "user_upload_require_download_link");
$allowVideoUrl = uploadTopicBool($settings, "user_upload_allow_video_url", "1");
$allowedVideoHosts = uploadTopicList($settings, "user_upload_allowed_video_hosts", "youtube.com,youtu.be,vimeo.com");
$maxImages = uploadTopicInt($settings, "user_upload_max_images", 10, 1);
$attachmentMaxSizeMb = uploadTopicInt($settings, "user_upload_max_size_mb", 50, 1);
$coverMaxSizeMb = uploadTopicInt($settings, "user_upload_cover_max_size_mb", 10, 1);
$galleryMaxSizeMb = uploadTopicInt($settings, "user_upload_gallery_max_size_mb", 10, 1);
$imageMinWidth = uploadTopicInt($settings, "user_upload_image_min_width", 0);
$imageMinHeight = uploadTopicInt($settings, "user_upload_image_min_height", 0);
$imageMaxWidth = uploadTopicInt($settings, "user_upload_image_max_width", 0);
$imageMaxHeight = uploadTopicInt($settings, "user_upload_image_max_height", 0);
$minTitleLength = uploadTopicInt($settings, "topic_min_title_length", uploadTopicInt($settings, "user_upload_min_title_length", 3, 0), 0);
$maxTitleLength = uploadTopicInt($settings, "user_upload_max_title_length", 150, 1);
    $minContentLength = uploadTopicInt($settings, "user_upload_min_content_length", 10, 0);
    $requireExcerpt = uploadTopicBool($settings, "topic_require_excerpt", "1");
    if ($requireExcerpt && $minContentLength <= 0) {
        $minContentLength = 10;
    }
$allowedImageExt = uploadTopicList($settings, "user_upload_allowed_image_ext", "jpg,jpeg,png,webp");
if (empty($allowedImageExt)) {
    $allowedImageExt = ["jpg", "jpeg", "png", "webp"];
}
$acceptImageAttr = "." . implode(",.", $allowedImageExt);
$imageDimensionRuleParts = [];
if ($imageMinWidth > 0 || $imageMaxWidth > 0) {
    $imageDimensionRuleParts[] = "Genislik: " . ($imageMinWidth > 0 ? "min " . $imageMinWidth . " px" : "min yok") . " / " . ($imageMaxWidth > 0 ? "max " . $imageMaxWidth . " px" : "max yok");
}
if ($imageMinHeight > 0 || $imageMaxHeight > 0) {
    $imageDimensionRuleParts[] = "Yukseklik: " . ($imageMinHeight > 0 ? "min " . $imageMinHeight . " px" : "min yok") . " / " . ($imageMaxHeight > 0 ? "max " . $imageMaxHeight . " px" : "max yok");
}
$imageDimensionRuleText = $imageDimensionRuleParts ? implode(" | ", $imageDimensionRuleParts) : "Pixel siniri yok";
$allowedImageExtText = strtoupper(implode(", ", $allowedImageExt));
$wizardEnabled = uploadTopicBool($settings, "user_upload_wizard_enabled", "1");
$allowStepSkip = uploadTopicBool($settings, "user_upload_allow_step_skip");
$showProfileFollowup = uploadTopicBool($settings, "user_upload_show_profile_followup", "1");
$showProfileButton = uploadTopicBool($settings, "user_upload_show_profile_button", "1");
$lockAfterSubmit = uploadTopicBool($settings, "user_upload_lock_after_submit", "1");
$hourlyLimit = uploadTopicInt($settings, "user_upload_hourly_limit", 0);
$dailyLimit = uploadTopicInt($settings, "user_upload_daily_limit", 0);
$blockDuplicateTitles = uploadTopicBool($settings, "user_upload_block_duplicate_titles", "1");
$currentUploadUserId = (int) ($_SESSION["_auth_user_id"] ?? 0);
$usedHourlyUploads = null;
$usedDailyUploads = null;
$remainingHourlyUploads = null;
$remainingDailyUploads = null;
if ($pdo && $currentUploadUserId > 0) {
    if ($hourlyLimit > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$currentUploadUserId]);
        $usedHourlyUploads = (int) $stmt->fetchColumn();
        $remainingHourlyUploads = max(0, $hourlyLimit - $usedHourlyUploads);
    }
    if ($dailyLimit > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([$currentUploadUserId]);
        $usedDailyUploads = (int) $stmt->fetchColumn();
        $remainingDailyUploads = max(0, $dailyLimit - $usedDailyUploads);
    }
}
$submissionNotice = trim((string) ($settings["user_upload_submission_notice"] ?? "Onay durumunu Profil > Konularım menüsünden takip edebilirsiniz."));
$defaultContentAlign = (string) ($settings["user_upload_default_content_align"] ?? "center");
$defaultContentAlign = in_array($defaultContentAlign, ["left", "center", "right"], true) ? $defaultContentAlign : "center";

if (empty($_SESSION["upload_topic_submit_token"])) {
    $_SESSION["upload_topic_submit_token"] = bin2hex(random_bytes(24));
}
$uploadSubmitToken = (string) $_SESSION["upload_topic_submit_token"];

$pageCssFiles = array_values(array_unique(array_merge(
    $pageCssFiles ?? [],
    ["assets/css/public-upload.css"],
)));

$upload_mode = 'create';
$upload_form_action = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('upload_topic')
    : ($baseUri . '/upload-topic.php');
$upload_csrf_token = csrf_token();
$upload_submit_token = $uploadSubmitToken;
$upload_categories = [];
foreach ($categoryOptions as $category) {
    if (!is_array($category)) {
        continue;
    }
    $upload_categories[] = [
        'id' => (string) (int) ($category['id'] ?? 0),
        'label' => str_repeat('-- ', (int) ($category['depth'] ?? 0)) . (string) ($category['name'] ?? ''),
        'selected' => '',
    ];
}
$upload_min_title_length = (string) (int) $minTitleLength;
$upload_max_title_length = (string) (int) $maxTitleLength;
$upload_min_content_length = (string) (int) $minContentLength;
$upload_accept_image_attr = $acceptImageAttr;
$upload_allowed_image_ext_text = $allowedImageExtText;
$upload_image_dimension_rule_text = $imageDimensionRuleText;
$upload_cover_required = $requireCover ? 'required' : '';
$upload_gallery_required = $requireGallery ? 'required' : '';
$upload_author_required = $requireAuthor ? 'required' : '';
$upload_version_required = $requireVersion ? 'required' : '';
$upload_download_required = $requireDownloadLink ? 'required' : '';
$upload_video_allowed = $allowVideoUrl;
$upload_attachment_accept = '.zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp';
$upload_notice = $submissionNotice;
$upload_default_content_align = $defaultContentAlign;
$upload_form_data = [
    'lock_after_submit' => $lockAfterSubmit ? '1' : '0',
    'max_images' => (string) (int) $maxImages,
    'cover_max_size_mb' => (string) (int) $coverMaxSizeMb,
    'gallery_max_size_mb' => (string) (int) $galleryMaxSizeMb,
    'attachment_max_size_mb' => (string) (int) $attachmentMaxSizeMb,
    'allowed_image_ext' => implode(',', $allowedImageExt),
    'image_min_width' => (string) (int) $imageMinWidth,
    'image_min_height' => (string) (int) $imageMinHeight,
    'image_max_width' => (string) (int) $imageMaxWidth,
    'image_max_height' => (string) (int) $imageMaxHeight,
    'require_author' => $requireAuthor ? '1' : '0',
    'require_version' => $requireVersion ? '1' : '0',
    'require_download_link' => $requireDownloadLink ? '1' : '0',
    'allow_video_url' => $allowVideoUrl ? '1' : '0',
];

require_once $projectRoot . "/includes/public-header.php";
?>









<div class="ui-container container pb-5 upload-topic-form-container">
    <section class="ui-section public-upload-shell">
        <div class="ui-panel public-upload-card">


            <div class="public-upload-body ui-panel__body">
                <form id="uploadForm" method="post" action="<?= htmlspecialchars($upload_form_action, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" data-upload-topic-standalone="1" data-ui-progress-form="1" data-loading-label="Gönderiliyor..." data-lock-after-submit="<?= $lockAfterSubmit ? "1" : "0" ?>" data-max-images="<?= (int) $maxImages ?>" data-cover-max-size-mb="<?= (int) $coverMaxSizeMb ?>" data-gallery-max-size-mb="<?= (int) $galleryMaxSizeMb ?>" data-attachment-max-size-mb="<?= (int) $attachmentMaxSizeMb ?>" data-allowed-image-ext="<?= htmlspecialchars(implode(",", $allowedImageExt), ENT_QUOTES, "UTF-8") ?>" data-image-min-width="<?= (int) $imageMinWidth ?>" data-image-min-height="<?= (int) $imageMinHeight ?>" data-image-max-width="<?= (int) $imageMaxWidth ?>" data-image-max-height="<?= (int) $imageMaxHeight ?>" data-min-title-length="<?= (int) $minTitleLength ?>" data-max-title-length="<?= (int) $maxTitleLength ?>" data-min-content-length="<?= (int) $minContentLength ?>" data-require-cover="<?= $requireCover ? "1" : "0" ?>" data-require-gallery="<?= $requireGallery ? "1" : "0" ?>" data-require-author="<?= $requireAuthor ? "1" : "0" ?>" data-require-version="<?= $requireVersion ? "1" : "0" ?>" data-require-download-link="<?= $requireDownloadLink ? "1" : "0" ?>" data-allow-video-url="<?= $allowVideoUrl ? "1" : "0" ?>" data-allowed-video-hosts="<?= htmlspecialchars(implode(",", $allowedVideoHosts), ENT_QUOTES, "UTF-8") ?>" data-hourly-limit="<?= (int) $hourlyLimit ?>" data-daily-limit="<?= (int) $dailyLimit ?>" data-block-duplicate-titles="<?= $blockDuplicateTitles ? "1" : "0" ?>" data-wizard-enabled="<?= $wizardEnabled ? "1" : "0" ?>" data-allow-step-skip="<?= $allowStepSkip ? "1" : "0" ?>" data-profile-topics-url="<?= htmlspecialchars($baseUri . '/profile.php?tab=topics', ENT_QUOTES, 'UTF-8') ?>" data-profile-draft-url="<?= htmlspecialchars($baseUri . '/profile.php?tab=topics&topic_status=draft', ENT_QUOTES, 'UTF-8') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="upload_submit_token" value="<?= htmlspecialchars($uploadSubmitToken, ENT_QUOTES, "UTF-8") ?>">

                    <div class="upload-composer-layout upload-composer-layout--single ui-section">
                        <div class="upload-form-fields">
                    <div class="upload-wizard-progress <?= $wizardEnabled ? "" : "is-hidden" ?>" aria-label="Mod yükleme adımları">
                        <button type="button" class="upload-wizard-step is-active" data-step-target="1"><span>1</span><strong>Temel Bilgiler</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="2"><span>2</span><strong>Kapak Görseli</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="3"><span>3</span><strong>Açıklama</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="4"><span>4</span><strong>Galeri ve Video</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="5"><span>5</span><strong>Yapımcı / Sürüm</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="6"><span>6</span><strong>İndirme Kaynakları</strong></button>
                        <button type="button" class="upload-wizard-step" data-step-target="7"><span>7</span><strong>Kontrol ve Onay</strong></button>
                    </div>

                    <section class="ui-panel upload-wizard-panel is-active" data-step="1">
                        <div class="upload-step-eyebrow">1. Adım</div>
                        <h2 class="upload-step-title">Temel Bilgiler</h2>
                        <p class="upload-step-copy">Mod başlığını net yazın ve içeriğin görüneceği kategoriyi seçin.</p>
                    <div class="row mb-4">
                        <div class="col-md-8 mb-3 mb-md-0">
                            <label class="form-label">Mod Başlığı <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="ui-admin-form-control" required minlength="<?= (int) $minTitleLength ?>" maxlength="<?= (int) $maxTitleLength ?>" placeholder="Harika bir başlık düşünün...">
                            <div class="upload-field-rules">
                                <span><i class="bi bi-type"></i> <?= (int) $minTitleLength ?>-<?= (int) $maxTitleLength ?> karakter</span>
                                <?php if ($blockDuplicateTitles): ?><span><i class="bi bi-shield-check"></i> Aynı başlık tekrar edilemez</span><?php endif; ?>
                            </div>
                            <div class="upload-live-hint" data-live-hint="title" aria-live="polite"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach (
                                    $categoryOptions
                                    as $category
                                ): ?>
                                    <option value="<?= (int) $category[
                                        "id"
                                    ] ?>"><?= str_repeat(
    "— ",
    (int) ($category["depth"] ?? 0),
) . htmlspecialchars((string) $category["name"]) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="upload-field-rules">
                                <span><i class="bi bi-asterisk"></i> Zorunlu secim</span>
                            </div>
                        </div>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="2"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">2. Adım</div>
                        <h2 class="upload-step-title">Kapak Görseli</h2>
                        <p class="upload-step-copy">Liste ve konu kartında ilk görünecek ana görseli yükleyin.</p>
                    <div class="public-upload-grid mb-4 ui-grid">
                        <article class="ui-surface public-media-card">
                            <div class="public-media-head ui-panel__head">
                                <div>
                                    <h3><i class="bi bi-image"></i> Kapak Görseli</h3>
                                    <p><?= $requireCover
                                        ? "Bu alan zorunludur."
                                        : "Bu alan isteğe bağlıdır." ?> En dikkat çekici görselinizi kapak yapın.</p>
                                </div>
                                <div class="public-pill"><i class="bi bi-star-fill"></i> Ana Görsel</div>
                            </div>
                            <div class="public-dropzone" data-uploader="cover">
                                <input type="file" name="topic_first_image_file" id="publicCoverInput" class="d-none" accept="<?= htmlspecialchars($acceptImageAttr) ?>" <?= $requireCover
                                    ? "required"
                                    : "" ?>>
                                <div class="public-dropzone-trigger" data-open-input="publicCoverInput">
                                    <i class="bi bi-cloud-arrow-up"></i>
                                    <strong>Kapak görselini buraya sürükleyin</strong>
                                    <span>veya seçmek için tıklayın (PNG, JPG, WEBP)</span>
                                </div>
                                <div class="upload-image-rules" aria-live="polite">
                                    <span><i class="bi bi-filetype-jpg"></i> <?= htmlspecialchars($allowedImageExtText, ENT_QUOTES, "UTF-8") ?></span>
                                    <span><i class="bi bi-hdd"></i> Maks. <?= (int) $coverMaxSizeMb ?> MB</span>
                                    <span><i class="bi bi-aspect-ratio"></i> <?= htmlspecialchars($imageDimensionRuleText, ENT_QUOTES, "UTF-8") ?></span>
                                </div>
                                <div class="public-preview-grid ui-grid" id="publicCoverPreview"></div>
                            </div>
                        </article>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="3"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">3. Adım</div>
                        <h2 class="upload-step-title">Açıklama</h2>
                        <p class="upload-step-copy">Özellikler, kurulum, uyumluluk ve dikkat edilmesi gerekenleri kısa ama yeterli anlatın.</p>
                    <div class="mb-4">
                        <label class="form-label">Mod Açıklaması <span class="text-danger">*</span></label>
                        <textarea name="content" rows="8" class="ui-admin-form-control rich-editor" data-default-align="<?= htmlspecialchars($defaultContentAlign) ?>" data-min-length="<?= (int) $minContentLength ?>" placeholder="Modunuz hakkında tüm detayları buraya yazabilirsiniz..."></textarea>
                        <div class="upload-field-rules">
                            <span><i class="bi bi-card-text"></i> En az <?= (int) $minContentLength ?> karakter</span>
                            <span><i class="bi bi-text-<?= htmlspecialchars($defaultContentAlign, ENT_QUOTES, "UTF-8") ?>"></i> Varsayilan hizalama: <?= htmlspecialchars($defaultContentAlign, ENT_QUOTES, "UTF-8") ?></span>
                        </div>
                        <div class="upload-live-hint" data-live-hint="content" aria-live="polite"></div>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="5"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">5. Adım</div>
                        <h2 class="upload-step-title">Yapımcı ve Oyun Sürümü</h2>
                        <p class="upload-step-copy">Yapımcı bilgisini ve modun hangi oyun sürümüyle uyumlu olduğunu belirtin.</p>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label">Mod Yapımcısı</label>
                            <input type="text" name="author_topic" class="ui-admin-form-control" placeholder="Örn: SCS Software" <?= $requireAuthor ? "required" : "" ?>>
                            <div class="upload-field-rules">
                                <span><i class="bi bi-person-badge"></i> <?= $requireAuthor ? "Zorunlu" : "Istege bagli" ?></span>
                            </div>
                            <div class="upload-live-hint" data-live-hint="author" aria-live="polite"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gerekli Oyun Sürümü</label>
                            <input type="text" name="topic_version" class="ui-admin-form-control" placeholder="Örn: 1.50" <?= $requireVersion ? "required" : "" ?>>
                            <div class="upload-field-rules">
                                <span><i class="bi bi-controller"></i> <?= $requireVersion ? "Zorunlu" : "Istege bagli" ?></span>
                            </div>
                            <div class="upload-live-hint" data-live-hint="version" aria-live="polite"></div>
                        </div>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="4"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">4. Adım</div>
                        <h2 class="upload-step-title">Galeri ve Video</h2>
                        <p class="upload-step-copy">En fazla 10 görsel yükleyin; varsa tanıtım videosu bağlantısını ekleyin.</p>
                    <div class="public-upload-grid mb-4 ui-grid">
                        <article class="ui-surface public-media-card">
                            <div class="public-media-head ui-panel__head">
                                <div>
                                    <h3><i class="bi bi-images"></i> Mod Galerisi</h3>
                                    <p><?= $requireGallery
                                        ? "En az 1 mod resmi gereklidir."
                                        : "Mod resimleri isteğe bağlıdır." ?> Oyuncular modunuzu daha yakından tanısın.</p>
                                </div>
                                <div class="public-pill"><i class="bi bi-collection"></i> Maks. <?= (int) $maxImages ?> Görsel</div>
                            </div>
                            <div class="public-dropzone" data-uploader="gallery">
                                <input type="file" name="topic_images_files[]" id="publicGalleryInput" class="d-none" accept="<?= htmlspecialchars($acceptImageAttr) ?>" multiple <?= $requireGallery
                                    ? "required"
                                    : "" ?>>
                                <div class="public-dropzone-trigger" data-open-input="publicGalleryInput">
                                    <i class="bi bi-images"></i>
                                    <strong>Galeri resimlerini toplu yükleyin</strong>
                                    <span>Birden çok görseli seçebilir veya sürükleyebilirsiniz</span>
                                </div>
                                <div class="upload-image-rules" aria-live="polite">
                                    <span><i class="bi bi-filetype-jpg"></i> <?= htmlspecialchars($allowedImageExtText, ENT_QUOTES, "UTF-8") ?></span>
                                    <span><i class="bi bi-hdd"></i> Maks. <?= (int) $galleryMaxSizeMb ?> MB</span>
                                    <span><i class="bi bi-aspect-ratio"></i> <?= htmlspecialchars($imageDimensionRuleText, ENT_QUOTES, "UTF-8") ?></span>
                                    <span><i class="bi bi-collection"></i> Maks. <?= (int) $maxImages ?> görsel</span>
                                </div>
                                <div class="public-preview-grid ui-grid" id="publicGalleryPreview"></div>
                            </div>

                            <?php if ($allowVideoUrl): ?>
                            <div class="mt-4 pt-4 upload-section-divider ui-section">
                                <label class="form-label"><i class="bi bi-youtube text-danger"></i> Video URL</label>
                                <input type="url" name="topic_video_url" class="ui-admin-form-control" placeholder="Örn: https://www.youtube.com/watch?v=...">
                                <div class="upload-field-rules">
                                    <span><i class="bi bi-camera-video"></i> <?= $allowVideoUrl ? "Video URL aktif" : "Video URL kapali" ?></span>
                                    <span><i class="bi bi-globe2"></i> <?= $allowedVideoHosts ? htmlspecialchars(implode(", ", $allowedVideoHosts), ENT_QUOTES, "UTF-8") : "Tüm sağlayıcılar" ?></span>
                                </div>
                                <div class="upload-live-hint" data-live-hint="video" aria-live="polite"></div>
                                <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Tanıtım videonuz varsa buraya ekleyin (YouTube, Vimeo vb.)</div>
                            </div>
                            <?php endif; ?>
                        </article>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="6"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">6. Adım</div>
                        <h2 class="upload-step-title">İndirme Kaynakları</h2>
                        <p class="upload-step-copy">Oyuncuların modu indireceği kaynakları ekleyin. Birden fazla ayna link kullanabilirsiniz.</p>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-link-45deg"></i> İndirme Bağlantıları</label>
                        <div class="form-text mb-3">Modu indirebilecekleri kaynakları ekleyin (Örn: Google Drive).</div>
                        <div id="dlRows">
                            <div class="dl-row">
                                <input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak Adı">
                                <input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://..." <?= $requireDownloadLink ? "required" : "" ?>>
                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-trash3"></i></button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-link mt-2" data-ui-action="addDlRow"><i class="bi bi-plus-circle"></i> Yeni Bağlantı Ekle</button>
                        <input type="hidden" name="topic_download_links" id="dlHidden">
                        <div class="upload-field-rules">
                            <span><i class="bi bi-link-45deg"></i> <?= $requireDownloadLink ? "En az 1 geçerli link zorunlu" : "Link isteğe bağlı" ?></span>
                        </div>
                        <div class="upload-live-hint" data-live-hint="download" aria-live="polite"></div>
                    </div>

                    <div class="mb-4 d-none">
                        <label class="form-label">Mod Dosyası (Opsiyonel)</label>
                        <input type="file" name="attachment" class="ui-admin-form-control" accept=".zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp">
                        <div class="upload-field-rules">
                            <span><i class="bi bi-archive"></i> Maks. <?= (int) $attachmentMaxSizeMb ?> MB</span>
                        </div>
                        <div class="upload-live-hint" data-live-hint="attachment" aria-live="polite"></div>
                    </div>
                    </section>

                    <section class="ui-panel upload-wizard-panel" data-step="7"<?= $wizardEnabled ? " hidden" : "" ?>>
                        <div class="upload-step-eyebrow">7. Adım</div>
                        <h2 class="upload-step-title">Kontrol ve Onaya Gönder</h2>
                        <p class="upload-step-copy">Son kontrolden sonra içeriğiniz moderatör onayına iletilir.</p>
                    <div class="public-alert-note ui-alert">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Güvenli İçerik Politikası</strong><br>
                            Yüklediğiniz içerikler moderatör onayından geçtikten sonra yayına alınır. Lütfen telif haklarına ve site kurallarına uyunuz.
                        </div>
                    </div>
                    <div class="upload-review-list" aria-label="Onay öncesi hatırlatmalar">
                        <div><i class="bi bi-send-check"></i><span>İçerik moderatör onayına gönderilecek.</span></div>
                        <div><i class="bi bi-asterisk"></i><span>Zorunlu alanları, kapak ve galeri kurallarını kontrol edin.</span></div>
                        <div><i class="bi bi-shield-lock"></i><span>Telif hakları, güvenli içerik ve site politikasına uygunluk sizin sorumluluğunuzdadır.</span></div>
                        <?php if ($hourlyLimit > 0): ?><div><i class="bi bi-clock-history"></i><span>Saatlik gönderim limiti: <?= (int) $hourlyLimit ?> mod.</span></div><?php endif; ?>
                        <?php if ($dailyLimit > 0): ?><div><i class="bi bi-calendar-day"></i><span>Günlük gönderim limiti: <?= (int) $dailyLimit ?> mod.</span></div><?php endif; ?>
                        <?php if ($blockDuplicateTitles): ?><div><i class="bi bi-copy"></i><span>Aynı başlıkla tekrar gönderim sunucuda engellenir.</span></div><?php endif; ?>
                    </div>
                    <?php if ($hourlyLimit > 0 || $dailyLimit > 0): ?>
                    <div class="upload-limit-summary" aria-live="polite">
                        <i class="bi bi-speedometer2"></i>
                        <div>
                            <strong>Gönderim hakkı</strong>
                            <?php if ($hourlyLimit > 0): ?>
                                <span>Saatlik: <?= $remainingHourlyUploads === null ? 'kontrol edilecek' : (int) $remainingHourlyUploads . ' / ' . (int) $hourlyLimit . ' kaldı' ?></span>
                            <?php endif; ?>
                            <?php if ($dailyLimit > 0): ?>
                                <span>Günlük: <?= $remainingDailyUploads === null ? 'kontrol edilecek' : (int) $remainingDailyUploads . ' / ' . (int) $dailyLimit . ' kaldı' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($showProfileFollowup): ?>
                    <div class="upload-profile-followup">
                        <i class="bi bi-person-lines-fill"></i>
                        <div>
                            <strong>Gönderdiğiniz konuyu takip edin</strong>
                            <span><?= htmlspecialchars($submissionNotice) ?></span>
                        </div>
                        <?php if ($showProfileButton): ?>
                        <a href="<?= $baseUri ?>/profile.php?tab=topics" class="upload-profile-followup-link">Konularıma Git</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="public-actions upload-final-actions">
                        <button type="submit" class="btn-submit-mod" data-loading-label="Gönderiliyor..."><i class="bi bi-send-check"></i> Onaya Gönder</button>
                        <a href="<?= $baseUri ?>/index.php" class="btn-cancel-mod">Geri Dön / İptal</a>
                    </div>
                    </section>

                    <div class="upload-wizard-controls <?= $wizardEnabled ? "" : "is-hidden" ?>">
                        <button type="button" class="btn-cancel-mod" data-wizard-prev><i class="bi bi-arrow-left"></i> Geri Dön</button>
                        <button type="button" class="btn-submit-mod" data-wizard-next>Devam Et <i class="bi bi-arrow-right"></i></button>
                    </div>

                        </div>

                    </div>

                    <div class="public-actions">
                        <button type="submit" class="btn-submit-mod"><i class="bi bi-send-check"></i> Modu Onaya Sun</button>
                        <a href="<?= $baseUri ?>/index.php" class="btn-cancel-mod">İptal Et</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>



<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet" integrity="sha384-cPa8kzsYWhqpAfWOLWYIw3V0BhPi/m3lrd8tBTPxr2NrYCHRVZ7xy1cEoRGOM/03" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous" async></script>
<script src="<?= asset_url('assets/js/upload-topic-rich-editor.js', $baseUri) ?>" defer></script>
<script src="<?= asset_url('assets/js/upload-topic-form.js', $baseUri) ?>" defer></script>


<?php require_once $projectRoot . "/includes/public-footer.php"; ?>

