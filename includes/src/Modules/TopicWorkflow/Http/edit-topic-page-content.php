<?php

declare(strict_types=1);

require_once $projectRoot . '/includes/init.php';
require_once $projectRoot . '/admin/helpers.php';

requireAuth();

if ($pdo) {
    ensureAdminSchema($pdo);
}

$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$topicId = max(0, (int)($_GET['id'] ?? 0));
$editTopicUrl = routePublicStaticUrl('edit_topic') . '?id=' . (int) $topicId;
$settings = function_exists('getAdminSettings') && $pdo ? getAdminSettings($pdo) : [];
$categoryOptions = getAdminCategoryOptions($pdo);
$pageTitle = 'Mod Düzenle';
$topic = null;

function editTopicBool(array $settings, string $key, string $default = '0'): bool
{
    return (string)($settings[$key] ?? $default) === '1';
}

function editTopicInt(array $settings, string $key, int $default = 0, int $min = 0): int
{
    return max($min, (int)($settings[$key] ?? $default));
}

function editTopicList(array $settings, string $key, string $default = ''): array
{
    return array_values(array_filter(array_map(static fn($value): string => strtolower(trim((string)$value)), explode(',', (string)($settings[$key] ?? $default)))));
}

function editTopicExistingVideo(array $mediaRecords): string
{
    foreach ($mediaRecords as $record) {
        $path = trim((string)($record['path'] ?? ''));
        if ($path !== '' && ((string)($record['type'] ?? '') === 'video' || preg_match('/(youtube\.com|youtu\.be|vimeo\.com)/i', $path))) {
            return $path;
        }
    }
    return '';
}

function editTopicBuildDownloadLines(array $names, array $urls): string
{
    $lines = [];
    foreach ($urls as $index => $url) {
        $url = trim((string)$url);
        if ($url === '') {
            continue;
        }
        $name = trim((string)($names[$index] ?? ''));
        $lines[] = ($name !== '' ? $name : 'Link') . '|' . $url;
    }
    return implode("\n", $lines);
}

function editTopicApplyContentAlignment(string $content, string $align): string
{
    $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    $content = trim($content);
    if ($content === '') {
        return '';
    }
    if (preg_match('/(?:text-align\s*:\s*(left|center|right)|class\s*=\s*["\'][^"\']*(?:content-align|ql-align)-(?:left|center|right))/i', $content)) {
        return $content;
    }
    return '<div class="content-align-' . $align . '">' . $content . '</div>';
}

function editTopicIsAjaxRequest(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function editTopicRespond(bool $success, string $message, int $statusCode = 200, array $data = []): void
{
    global $baseUri, $topicId;

    if (editTopicIsAjaxRequest()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    flash($success ? 'success' : 'error', $message);
    $target = $success
        ? (string) ($data['redirect'] ?? ($baseUri . '/profile.php?tab=topics&topic_status=draft'))
        : $editTopicUrl;
    header('Location: ' . $target);
    exit;
}

function editTopicEventDispatcher(string $projectRoot): ?\App\Core\Events\Dispatcher
{
    try {
        return \App\Core\Bootstrap\Boot::container($projectRoot)->get(\App\Core\Events\Dispatcher::class);
    } catch (Throwable $exception) {
        if (function_exists('appLogException')) {
            appLogException($exception, ['source' => 'TopicEditService::dispatcher']);
        } else {
            error_log($exception->getMessage());
        }

        return null;
    }
}

function editTopicMediaUrl(?string $path, string $baseUri): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    return str_starts_with($path, 'http') ? $path : $baseUri . '/' . ltrim($path, '/');
}

if ($pdo && $topicId > 0) {
    $stmt = $pdo->prepare("SELECT t.*, cat.name AS category
                           FROM topics t
                           LEFT JOIN categories cat ON cat.id = t.category_id
                           WHERE t.id = :id
                             AND t.author_id = :uid
                             AND t.deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute(['id' => $topicId, 'uid' => $userId]);
    $topic = $stmt->fetch();
}

if (!$topic) {
    flash('error', 'Düzenleyebileceğiniz mod bulunamadı.');
    header('Location: ' . $baseUri . '/profile.php?tab=topics');
    exit;
}

if ($pdo && function_exists('usersHasRestriction') && (usersHasRestriction($pdo, $userId, 'all') || usersHasRestriction($pdo, $userId, 'topic') || usersHasRestriction($pdo, $userId, 'upload'))) {
    flash('error', 'Hesabınızda mod düzenleme kısıtlaması bulunduğu için bu işlem yapılamaz.');
    header('Location: ' . $baseUri . '/profile.php?tab=topics');
    exit;
}

$allowedImageExt = editTopicList($settings, 'user_upload_allowed_image_ext', 'jpg,jpeg,png,webp');
$allowedImageExt = $allowedImageExt ?: ['jpg', 'jpeg', 'png', 'webp'];
$allowedImageExtText = strtoupper(implode(', ', $allowedImageExt));
$acceptImageAttr = implode(',', array_map(static fn(string $ext): string => '.' . ltrim($ext, '.'), $allowedImageExt));
$maxImages = editTopicInt($settings, 'user_upload_max_images', 10, 1);
$coverMaxSizeMb = editTopicInt($settings, 'user_upload_cover_max_size_mb', 10, 1);
$galleryMaxSizeMb = editTopicInt($settings, 'user_upload_gallery_max_size_mb', 10, 1);
$attachmentMaxSizeMb = editTopicInt($settings, 'user_upload_max_size_mb', 50, 1);
$minTitleLength = editTopicInt($settings, 'user_upload_min_title_length', 3, 0);
$maxTitleLength = editTopicInt($settings, 'user_upload_max_title_length', 150, 1);
$minContentLength = editTopicInt($settings, 'user_upload_min_content_length', 10, 0);
$requireAuthor = editTopicBool($settings, 'user_upload_require_author');
$requireVersion = editTopicBool($settings, 'user_upload_require_version');
$requireDownloadLink = editTopicBool($settings, 'user_upload_require_download_link');
$allowVideoUrl = editTopicBool($settings, 'user_upload_allow_video_url', '1');
$allowedVideoHosts = editTopicList($settings, 'user_upload_allowed_video_hosts', 'youtube.com,youtu.be,vimeo.com');
$defaultContentAlign = (string)($settings['user_upload_default_content_align'] ?? 'center');
$defaultContentAlign = in_array($defaultContentAlign, ['left', 'center', 'right'], true) ? $defaultContentAlign : 'center';
$userEditRequiresApproval = editTopicBool($settings, 'topic_user_edit_requires_approval', '1');
$topicStatusAfterUserEdit = $userEditRequiresApproval ? 'draft' : (string)($topic['status'] ?? 'published');
if (!in_array($topicStatusAfterUserEdit, ['draft', 'published', 'approved'], true)) {
    $topicStatusAfterUserEdit = 'published';
}
$imageMinWidth = editTopicInt($settings, 'user_upload_image_min_width', 0);
$imageMinHeight = editTopicInt($settings, 'user_upload_image_min_height', 0);
$imageMaxWidth = editTopicInt($settings, 'user_upload_image_max_width', 0);
$imageMaxHeight = editTopicInt($settings, 'user_upload_image_max_height', 0);
$imageDimensionRuleText = 'Önerilen ölçü';
if ($imageMinWidth > 0 || $imageMinHeight > 0 || $imageMaxWidth > 0 || $imageMaxHeight > 0) {
    $imageDimensionRuleText = trim(
        ($imageMinWidth > 0 || $imageMinHeight > 0 ? 'Min. ' . (int)$imageMinWidth . 'x' . (int)$imageMinHeight . ' px ' : '') .
        ($imageMaxWidth > 0 || $imageMaxHeight > 0 ? 'Maks. ' . (int)$imageMaxWidth . 'x' . (int)$imageMaxHeight . ' px' : '')
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        editTopicRespond(false, 'Güvenlik hatası. Sayfayı yenileyip tekrar deneyin.', 403);
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $content = editTopicApplyContentAlignment((string)($_POST['content'] ?? ''), $defaultContentAlign);
    $authorTopic = trim((string)($_POST['author_topic'] ?? ''));
    $topicVersion = trim((string)($_POST['topic_version'] ?? ''));
    $videoUrl = $allowVideoUrl ? trim((string)($_POST['topic_video_url'] ?? '')) : '';
    $downloadLines = editTopicBuildDownloadLines((array)($_POST['dl_name'] ?? []), (array)($_POST['dl_url'] ?? []));
    $keepMediaIds = array_values(array_filter(array_map('intval', (array)($_POST['keep_media'] ?? [])), static fn(int $id): bool => $id > 0));

    if ($categoryId <= 0 && !empty($categoryOptions)) {
        $categoryId = (int)$categoryOptions[0]['id'];
    }

    if ($title === '' || $content === '' || $categoryId <= 0) {
        editTopicRespond(false, 'Başlık, kategori ve açıklama alanları zorunludur.', 422);
    }
    if ($minTitleLength > 0 && mb_strlen($title, 'UTF-8') < $minTitleLength) {
        editTopicRespond(false, 'Mod başlığı en az ' . $minTitleLength . ' karakter olmalıdır.', 422);
    }
    if ($maxTitleLength > 0 && mb_strlen($title, 'UTF-8') > $maxTitleLength) {
        editTopicRespond(false, 'Mod başlığı en fazla ' . $maxTitleLength . ' karakter olabilir.', 422);
    }
    if ($minContentLength > 0 && mb_strlen(trim(strip_tags($content)), 'UTF-8') < $minContentLength) {
        editTopicRespond(false, 'Mod açıklaması en az ' . $minContentLength . ' karakter olmalıdır.', 422);
    }
    if ($requireAuthor && $authorTopic === '') {
        editTopicRespond(false, 'Yapımcı alanı zorunludur.', 422);
    }
    if ($requireVersion && $topicVersion === '') {
        editTopicRespond(false, 'Oyun sürümü alanı zorunludur.', 422);
    }
    if ($requireDownloadLink && trim($downloadLines) === '') {
        editTopicRespond(false, 'En az bir geçerli indirme bağlantısı eklemelisiniz.', 422);
    }
    if ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        editTopicRespond(false, 'Video URL geçerli değil.', 422);
    }

    $moderationDecision = function_exists('adminContentModerationDecision')
        ? adminContentModerationDecision($settings, $title, $content)
        : ['matched' => false, 'action' => 'none', 'message' => '', 'flags' => null];
    if (!empty($moderationDecision['matched']) && ($moderationDecision['action'] ?? '') === 'reject') {
        editTopicRespond(false, (string)($moderationDecision['message'] ?? 'İçerik moderasyonu nedeniyle değişiklik reddedildi.'), 422);
    }
    $moderationFlagsJson = null;
    if (!empty($moderationDecision['flags'])) {
        $encodedModerationFlags = json_encode($moderationDecision['flags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $moderationFlagsJson = is_string($encodedModerationFlags) ? $encodedModerationFlags : null;
    }
    if (!empty($moderationDecision['matched']) && ($moderationDecision['action'] ?? '') === 'draft') {
        $topicStatusAfterUserEdit = 'draft';
    }

    try {
        $editService = new \App\Modules\TopicWorkflow\Services\TopicEditService(
            editTopicEventDispatcher($projectRoot),
        );
        $editResult = $editService->update($pdo, [
            'topic_id' => $topicId,
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $title,
            'author_topic' => $authorTopic,
            'topic_version' => $topicVersion,
            'content' => $content,
            'status' => $topicStatusAfterUserEdit,
            'moderation_flags_json' => $moderationFlagsJson,
            'video_url' => $videoUrl,
            'download_lines' => $downloadLines,
            'max_images' => $maxImages,
            'topic' => $topic,
            'keep_media_ids' => $keepMediaIds,
        ], [
            'cover' => $_FILES['topic_first_image_file'] ?? null,
            'gallery' => $_FILES['topic_images_files'] ?? null,
            'attachment' => $_FILES['attachment'] ?? null,
        ]);

        $topicId = (int) ($editResult['topic_id'] ?? $topicId);
        $slug = (string) ($editResult['slug'] ?? '');
        $topicStatusAfterUserEdit = (string) ($editResult['status'] ?? $topicStatusAfterUserEdit);
        $editNeedsDraftReview = $topicStatusAfterUserEdit === 'draft';
        $editSuccessMessage = $editNeedsDraftReview
            ? 'Değişiklikler taslak olarak kaydedildi. Yayına alınana kadar konu profilinizde Taslak olarak görünecek.'
            : 'Değişiklikler kaydedildi ve konu yayında kalmaya devam ediyor.';
        $editRedirect = $editNeedsDraftReview
            ? $baseUri . '/profile.php?tab=topics&topic_status=draft&edited=1'
            : topicUrl($slug, $topicId);
        editTopicRespond(true, $editSuccessMessage, 200, [
            'redirect' => $editRedirect,
            'topic_id' => $topicId,
        ]);
    } catch (Throwable $e) {
        editTopicRespond(false, safeErrorMessage($e, 'Mod güncellenirken bir hata oluştu.'), 500);
    }
}

$mediaRecords = getTopicMediaRecords($pdo, $topicId, true);
$primaryMediaId = (int)($topic['primary_media_file_id'] ?? 0);
$primaryMediaPath = (string)(getTopicPrimaryMediaPath($topic) ?? '');
$galleryImages = [];
foreach ($mediaRecords as $record) {
    $mediaId = (int)($record['id'] ?? 0);
    $path = trim((string)($record['path'] ?? ''));
    $type = (string)($record['type'] ?? '');
    $mime = (string)($record['mime_type'] ?? '');
    if ($path !== '' && ($mediaId === $primaryMediaId || ((int)($record['is_primary'] ?? 0) === 1 && $primaryMediaPath === ''))) {
        $primaryMediaId = $mediaId;
        $primaryMediaPath = $path;
    }
    if ($path === '' || $mediaId === $primaryMediaId || $type === 'video') {
        continue;
    }
    if ($type === 'image' || str_starts_with($mime, 'image/')) {
        $galleryImages[] = ['id' => $mediaId, 'path' => $path];
    }
}
$videoUrl = editTopicExistingVideo($mediaRecords);
$downloadLinks = getTopicDownloadLinks($pdo, $topicId, (string)($topic['topic_download_links'] ?? ''));
$moderationFlags = !empty($topic['moderation_flags']) ? json_decode((string)$topic['moderation_flags'], true) : [];
$moderationNote = is_array($moderationFlags) ? trim((string)($moderationFlags['note'] ?? '')) : '';
$statusLabel = match ((string)($topic['status'] ?? '')) {
    'published' => 'Yayında',
    'revision' => 'Revizyon istendi',
    'rejected' => 'Reddedildi',
    default => 'Taslak',
};

$pageCssFiles = array_values(array_unique(array_merge(
    $pageCssFiles ?? [],
    ['assets/css/public-upload.css'],
)));

$upload_mode = 'edit';
$upload_form_action = $editTopicUrl;
$upload_csrf_token = csrf_token();
$upload_categories = [];
foreach ($categoryOptions as $category) {
    if (!is_array($category)) {
        continue;
    }
    $upload_categories[] = [
        'id' => (string) (int) ($category['id'] ?? 0),
        'label' => str_repeat('-- ', (int) ($category['depth'] ?? 0)) . (string) ($category['name'] ?? ''),
        'selected' => (int) ($category['id'] ?? 0) === (int) ($topic['category_id'] ?? 0) ? 'selected' : '',
    ];
}
$upload_min_title_length = (string) (int) $minTitleLength;
$upload_max_title_length = (string) (int) $maxTitleLength;
$upload_min_content_length = (string) (int) $minContentLength;
$upload_accept_image_attr = $acceptImageAttr;
$upload_allowed_image_ext_text = strtoupper(implode(', ', $allowedImageExt));
$upload_image_dimension_rule_text = $imageDimensionRuleText;
$upload_cover_required = '';
$upload_gallery_required = '';
$upload_author_required = $requireAuthor ? 'required' : '';
$upload_version_required = $requireVersion ? 'required' : '';
$upload_download_required = $requireDownloadLink ? 'required' : '';
$upload_video_allowed = $allowVideoUrl;
$upload_attachment_accept = '.zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp';
$upload_default_content_align = $defaultContentAlign;
$edit_status_label = $statusLabel;
$edit_moderation_note = $moderationNote;
$edit_has_moderation_note = $moderationNote !== '';
$edit_title_value = (string) ($topic['title'] ?? '');
$edit_content_value = (string) ($topic['topic_descriptions'] ?? '');
$edit_author_value = (string) ($topic['author_topic'] ?? '');
$edit_version_value = (string) ($topic['topic_version'] ?? '');
$edit_video_url = $videoUrl;
$edit_existing_media = [];
if ($primaryMediaId > 0 && $primaryMediaPath !== '') {
    $edit_existing_media[] = [
        'id' => (string) $primaryMediaId,
        'url' => $baseUri . '/' . ltrim($primaryMediaPath, '/'),
        'label' => 'Kapak gorseli',
    ];
}
foreach ($galleryImages as $image) {
    if (!is_array($image)) {
        continue;
    }
    $edit_existing_media[] = [
        'id' => (string) (int) ($image['id'] ?? 0),
        'url' => $baseUri . '/' . ltrim((string) ($image['path'] ?? ''), '/'),
        'label' => 'Galeri gorseli',
    ];
}
$edit_has_existing_media = $edit_existing_media !== [];
$edit_download_links = [];
foreach ($downloadLinks as $link) {
    if (!is_array($link)) {
        continue;
    }
    $edit_download_links[] = [
        'name' => (string) ($link['name'] ?? ''),
        'url' => (string) ($link['url'] ?? ''),
    ];
}
if ($edit_download_links === []) {
    $edit_download_links[] = ['name' => '', 'url' => ''];
}
$upload_form_data = [
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

require_once $projectRoot . '/includes/public-header.php';
?>



<div class="ui-container container pb-5 upload-topic-form-container topic-edit-page topic-edit-upload-page">
    <section class="ui-section public-upload-shell">
        <div class="ui-panel public-upload-card">

            <div class="public-alert-note public-alert-note-strong ui-alert">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    <strong>Yeniden onay gerekir</strong><br>
                    Kaydettiğiniz değişiklikler yayına doğrudan çıkmaz; konu onay bekleyenler listesine taşınır.
                </div>
            </div>

            <div class="public-upload-body ui-panel__body">
                <form id="uploadForm" method="post" action="<?= htmlspecialchars($upload_form_action, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" data-upload-topic-standalone="1" data-topic-edit-wizard data-lock-after-submit="0" data-max-images="<?= (int)$maxImages ?>" data-cover-max-size-mb="<?= (int)$coverMaxSizeMb ?>" data-gallery-max-size-mb="<?= (int)$galleryMaxSizeMb ?>" data-attachment-max-size-mb="<?= (int)$attachmentMaxSizeMb ?>" data-allowed-image-ext="<?= htmlspecialchars(implode(',', $allowedImageExt), ENT_QUOTES, 'UTF-8') ?>" data-image-min-width="<?= (int)$imageMinWidth ?>" data-image-min-height="<?= (int)$imageMinHeight ?>" data-image-max-width="<?= (int)$imageMaxWidth ?>" data-image-max-height="<?= (int)$imageMaxHeight ?>" data-min-title-length="<?= (int)$minTitleLength ?>" data-max-title-length="<?= (int)$maxTitleLength ?>" data-min-content-length="<?= (int)$minContentLength ?>" data-require-cover="0" data-require-gallery="0" data-require-author="<?= $requireAuthor ? '1' : '0' ?>" data-require-version="<?= $requireVersion ? '1' : '0' ?>" data-require-download-link="<?= $requireDownloadLink ? '1' : '0' ?>" data-allow-video-url="<?= $allowVideoUrl ? '1' : '0' ?>" data-allowed-video-hosts="<?= htmlspecialchars(implode(',', $allowedVideoHosts), ENT_QUOTES, 'UTF-8') ?>" data-wizard-enabled="1" data-allow-step-skip="1">
                    <?= csrf_field() ?>

                    <div class="upload-composer-layout upload-composer-layout--single ui-section">
                        <div class="upload-form-fields">
                            <div class="upload-wizard-progress" aria-label="Mod düzenleme adımları">
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
                                <p class="upload-step-copy">Mod başlığını ve kategorisini güncelleyin.</p>
                                <div class="row mb-4">
                                    <div class="col-md-8 mb-3 mb-md-0">
                                        <label class="form-label">Mod Başlığı <span class="text-danger">*</span></label>
                                        <input type="text" name="title" class="ui-admin-form-control" required minlength="<?= (int)$minTitleLength ?>" maxlength="<?= (int)$maxTitleLength ?>" value="<?= htmlspecialchars((string)$topic['title'], ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="upload-field-rules"><span><i class="bi bi-type"></i> <?= (int)$minTitleLength ?>-<?= (int)$maxTitleLength ?> karakter</span></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                                        <select name="category_id" class="form-select" required>
                                            <?php foreach ($categoryOptions as $category): ?>
                                                <option value="<?= (int)$category['id'] ?>" <?= (int)$topic['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                                    <?= str_repeat('— ', (int)($category['depth'] ?? 0)) . htmlspecialchars((string)$category['name'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="upload-field-rules"><span><i class="bi bi-asterisk"></i> Zorunlu seçim</span></div>
                                    </div>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="2" hidden>
                                <div class="upload-step-eyebrow">2. Adım</div>
                                <h2 class="upload-step-title">Kapak Görseli</h2>
                                <p class="upload-step-copy">Mevcut kapak görselini koruyabilir veya yenisiyle değiştirebilirsiniz.</p>
                                <div class="public-upload-grid mb-4 ui-grid">
                                    <article class="ui-surface public-media-card">
                                        <div class="public-media-head ui-panel__head">
                                            <div>
                                                <h3><i class="bi bi-image"></i> Kapak Görseli</h3>
                                                <p>Liste ve konu kartında ilk görünecek ana görsel.</p>
                                            </div>
                                            <div class="public-pill"><i class="bi bi-star-fill"></i> Ana Görsel</div>
                                        </div>
                                        <?php if ($primaryMediaPath !== ''): ?>
                                            <div class="topic-edit-existing-media">
                                                <div class="public-preview-item">
                                                    <img src="<?= htmlspecialchars(editTopicMediaUrl($primaryMediaPath, $baseUri), ENT_QUOTES, 'UTF-8') ?>" alt="" width="128" height="96">
                                                    <label class="topic-edit-keep-toggle"><input type="checkbox" name="keep_media[]" value="<?= (int)$primaryMediaId ?>" checked> Koru</label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="public-dropzone" data-uploader="cover">
                                            <input type="file" name="topic_first_image_file" id="publicCoverInput" class="d-none" accept="<?= htmlspecialchars($acceptImageAttr, ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="public-dropzone-trigger" data-open-input="publicCoverInput">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                                <strong>Yeni kapak görseli seçin</strong>
                                                <span>Değiştirmek istemiyorsanız mevcut görseli korulu bırakın</span>
                                            </div>
                                            <div class="upload-image-rules">
                                                <span><i class="bi bi-filetype-jpg"></i> <?= htmlspecialchars($allowedImageExtText, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span><i class="bi bi-hdd"></i> Maks. <?= (int)$coverMaxSizeMb ?> MB</span>
                                                <span><i class="bi bi-aspect-ratio"></i> <?= htmlspecialchars($imageDimensionRuleText, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <div class="public-preview-grid ui-grid" id="publicCoverPreview"></div>
                                        </div>
                                    </article>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="3" hidden>
                                <div class="upload-step-eyebrow">3. Adım</div>
                                <h2 class="upload-step-title">Açıklama</h2>
                                <p class="upload-step-copy">Özellikler, kurulum, uyumluluk ve değişiklikleri net anlatın.</p>
                                <?php if ($moderationNote !== ''): ?>
                                    <div class="public-alert-note topic-edit-moderation-note ui-alert">
                                        <i class="bi bi-chat-left-text"></i>
                                        <div><strong>Moderasyon notu</strong><br><?= htmlspecialchars($moderationNote, ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-4">
                                    <label class="form-label">Mod Açıklaması <span class="text-danger">*</span></label>
                                    <textarea name="content" rows="8" class="ui-admin-form-control rich-editor" data-default-align="<?= htmlspecialchars($defaultContentAlign, ENT_QUOTES, 'UTF-8') ?>" data-min-length="<?= (int)$minContentLength ?>" required><?= htmlspecialchars((string)($topic['topic_descriptions'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="upload-field-rules">
                                        <span><i class="bi bi-card-text"></i> En az <?= (int)$minContentLength ?> karakter</span>
                                        <span><i class="bi bi-text-<?= htmlspecialchars($defaultContentAlign, ENT_QUOTES, 'UTF-8') ?>"></i> Varsayılan hizalama: <?= htmlspecialchars($defaultContentAlign, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="4" hidden>
                                <div class="upload-step-eyebrow">4. Adım</div>
                                <h2 class="upload-step-title">Galeri ve Video</h2>
                                <p class="upload-step-copy">Mevcut galeri görsellerini koruyabilir, kaldırabilir ve yeni görseller ekleyebilirsiniz.</p>
                                <div class="public-upload-grid mb-4 ui-grid">
                                    <article class="ui-surface public-media-card">
                                        <div class="public-media-head ui-panel__head">
                                            <div>
                                                <h3><i class="bi bi-images"></i> Mod Galerisi</h3>
                                                <p>Oyuncular modunuzu daha yakından tanısın.</p>
                                            </div>
                                            <div class="public-pill"><i class="bi bi-collection"></i> Maks. <?= (int)$maxImages ?> Görsel</div>
                                        </div>
                                        <?php if (!empty($galleryImages)): ?>
                                            <div class="topic-edit-existing-media topic-edit-existing-gallery">
                                                <?php foreach ($galleryImages as $image): ?>
                                                    <div class="public-preview-item">
                                                        <img src="<?= htmlspecialchars(editTopicMediaUrl((string)$image['path'], $baseUri), ENT_QUOTES, 'UTF-8') ?>" alt="" width="128" height="96">
                                                        <label class="topic-edit-keep-toggle"><input type="checkbox" name="keep_media[]" value="<?= (int)$image['id'] ?>" checked> Koru</label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="public-dropzone" data-uploader="gallery">
                                            <input type="file" name="topic_images_files[]" id="publicGalleryInput" class="d-none" accept="<?= htmlspecialchars($acceptImageAttr, ENT_QUOTES, 'UTF-8') ?>" multiple>
                                            <div class="public-dropzone-trigger" data-open-input="publicGalleryInput">
                                                <i class="bi bi-images"></i>
                                                <strong>Yeni galeri görselleri seçin</strong>
                                                <span>Birden çok görsel seçebilir veya sürükleyebilirsiniz</span>
                                            </div>
                                            <div class="upload-image-rules">
                                                <span><i class="bi bi-filetype-jpg"></i> <?= htmlspecialchars($allowedImageExtText, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span><i class="bi bi-hdd"></i> Maks. <?= (int)$galleryMaxSizeMb ?> MB</span>
                                                <span><i class="bi bi-aspect-ratio"></i> <?= htmlspecialchars($imageDimensionRuleText, ENT_QUOTES, 'UTF-8') ?></span>
                                                <span><i class="bi bi-collection"></i> Maks. <?= (int)$maxImages ?> görsel</span>
                                            </div>
                                            <div class="public-preview-grid ui-grid" id="publicGalleryPreview"></div>
                                        </div>

                                        <?php if ($allowVideoUrl): ?>
                                            <div class="mt-4 pt-4 topic-edit-video-row">
                                                <label class="form-label"><i class="bi bi-youtube text-danger"></i> Video URL</label>
                                                <input type="url" name="topic_video_url" class="ui-admin-form-control" value="<?= htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="Örn: https://www.youtube.com/watch?v=...">
                                                <div class="upload-field-rules">
                                                    <span><i class="bi bi-camera-video"></i> Video URL aktif</span>
                                                    <span><i class="bi bi-globe2"></i> <?= $allowedVideoHosts ? htmlspecialchars(implode(', ', $allowedVideoHosts), ENT_QUOTES, 'UTF-8') : 'Tüm sağlayıcılar' ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="5" hidden>
                                <div class="upload-step-eyebrow">5. Adım</div>
                                <h2 class="upload-step-title">Yapımcı ve Oyun Sürümü</h2>
                                <p class="upload-step-copy">Yapımcı bilgisini ve modun hangi oyun sürümüyle uyumlu olduğunu belirtin.</p>
                                <div class="row mb-4">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label class="form-label">Mod Yapımcısı</label>
                                        <input type="text" name="author_topic" class="ui-admin-form-control" value="<?= htmlspecialchars((string)($topic['author_topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $requireAuthor ? 'required' : '' ?>>
                                        <div class="upload-field-rules"><span><i class="bi bi-person-badge"></i> <?= $requireAuthor ? 'Zorunlu' : 'İsteğe bağlı' ?></span></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gerekli Oyun Sürümü</label>
                                        <input type="text" name="topic_version" class="ui-admin-form-control" value="<?= htmlspecialchars((string)($topic['topic_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $requireVersion ? 'required' : '' ?>>
                                        <div class="upload-field-rules"><span><i class="bi bi-controller"></i> <?= $requireVersion ? 'Zorunlu' : 'İsteğe bağlı' ?></span></div>
                                    </div>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="6" hidden>
                                <div class="upload-step-eyebrow">6. Adım</div>
                                <h2 class="upload-step-title">İndirme Kaynakları</h2>
                                <p class="upload-step-copy">Oyuncuların modu indireceği kaynakları güncelleyin.</p>
                                <div class="mb-4">
                                    <label class="form-label"><i class="bi bi-link-45deg"></i> İndirme Bağlantıları</label>
                                    <div class="form-text mb-3">Modu indirebilecekleri kaynakları ekleyin.</div>
                                    <div id="dlRows">
                                        <?php $rows = !empty($downloadLinks) ? $downloadLinks : [['name' => '', 'url' => '']]; ?>
                                        <?php foreach ($rows as $link): ?>
                                            <div class="dl-row">
                                                <input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak Adı" value="<?= htmlspecialchars((string)($link['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://..." value="<?= htmlspecialchars((string)($link['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $requireDownloadLink ? 'required' : '' ?>>
                                                <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-trash3"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn-add-link mt-2" data-ui-action="addDlRow"><i class="bi bi-plus-circle"></i> Yeni Bağlantı Ekle</button>
                                    <div class="upload-field-rules"><span><i class="bi bi-link-45deg"></i> <?= $requireDownloadLink ? 'En az 1 geçerli link zorunlu' : 'Link isteğe bağlı' ?></span></div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Mod Dosyası (Opsiyonel)</label>
                                    <input type="file" name="attachment" class="ui-admin-form-control" accept=".zip,.rar,.7z,.pdf,.png,.jpg,.jpeg,.webp">
                                    <div class="upload-field-rules"><span><i class="bi bi-archive"></i> Maks. <?= (int)$attachmentMaxSizeMb ?> MB</span></div>
                                </div>
                            </section>

                            <section class="ui-panel upload-wizard-panel" data-step="7" hidden>
                                <div class="upload-step-eyebrow">7. Adım</div>
                                <h2 class="upload-step-title">Kontrol ve Onaya Gönder</h2>
                                <p class="upload-step-copy">Son kontrolden sonra değişiklikler moderatör onayına iletilir.</p>
                                <div class="public-alert-note ui-alert">
                                    <i class="bi bi-shield-check"></i>
                                    <div>
                                        <strong>Revizyon onay akışı</strong><br>
                                        Kaydettiğinizde mod durumu otomatik olarak <strong>Taslak</strong> yapılır. Admin onayından sonra yeniden yayına alınır.
                                    </div>
                                </div>
                                <div class="upload-review-list">
                                    <div><i class="bi bi-pencil-square"></i><span>Bu işlem mevcut modunuzu günceller.</span></div>
                                    <div><i class="bi bi-hourglass-split"></i><span>Güncel durum: <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>. Kaydedince tekrar onaya düşer.</span></div>
                                    <div><i class="bi bi-images"></i><span>İşaretli mevcut görseller korunur, işareti kaldırılan medya silinir.</span></div>
                                    <div><i class="bi bi-link-45deg"></i><span>İndirme linklerinin çalıştığından emin olun.</span></div>
                                </div>
                                <div class="public-actions upload-final-actions">
                                    <button type="submit" class="btn-submit-mod"><i class="bi bi-send-check"></i> Değişiklikleri Onaya Gönder</button>
                                    <a href="<?= $baseUri ?>/profile.php?tab=topics" class="btn-cancel-mod">İptal Et</a>
                                </div>
                            </section>

                            <div class="upload-wizard-controls">
                                <button type="button" class="btn-cancel-mod" data-wizard-prev><i class="bi bi-arrow-left"></i> Geri Dön</button>
                                <button type="button" class="btn-submit-mod" data-wizard-next>Devam Et <i class="bi bi-arrow-right"></i></button>
                            </div>
                        </div>

                    </div>

                    <div class="public-actions">
                        <button type="submit" class="btn-submit-mod"><i class="bi bi-send-check"></i> Değişiklikleri Onaya Gönder</button>
                        <a href="<?= $baseUri ?>/profile.php?tab=topics" class="btn-cancel-mod">İptal Et</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>





<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet" integrity="sha384-cPa8kzsYWhqpAfWOLWYIw3V0BhPi/m3lrd8tBTPxr2NrYCHRVZ7xy1cEoRGOM/03" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" integrity="sha384-QUJ+ckWz1M+a7w0UfG1sEn4pPrbQwSxGm/1TIPyioqXBrwuT9l4f9gdHWLDLbVWI" crossorigin="anonymous" async></script>
<script src="<?= asset_url('assets/js/edit-topic-rich-editor.js', $baseUri) ?>" defer></script>
<script src="<?= asset_url('assets/js/edit-topic-form.js', $baseUri) ?>" defer></script>


<?php require_once $projectRoot . '/includes/public-footer.php'; ?>

