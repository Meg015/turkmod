<?php
declare(strict_types=1);

// Exercise the real POST validation blocks up to the persistence boundary.
// No bootstrap, authentication, database query or file storage is run here.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

final class TopicFormFixtureResponse extends RuntimeException
{
    public function __construct(public readonly int $status, string $message) { parent::__construct($message); }
}
function uploadTopicRespond(bool $success, string $message, int $statusCode = 200, array $data = []): void { throw new TopicFormFixtureResponse($statusCode, $message); }
function editTopicRespond(bool $success, string $message, int $statusCode = 200, array $data = []): void { throw new TopicFormFixtureResponse($statusCode, $message); }
function verify_csrf_token(mixed $token): bool { return $token === 'fixture-csrf'; }

function topicFormFixtureSource(string $mode): string
{
    static $sources = [];
    if (isset($sources[$mode])) return $sources[$mode];
    $name = $mode === 'upload' ? 'create' : 'edit';
    $source = file_get_contents(dirname(__DIR__, 2) . '/includes/src/Modules/TopicWorkflow/Http/' . $name . '-topic-page-content.php');
    $helpers = $mode === 'upload'
        ? ['uploadTopicBool', 'uploadTopicInt', 'uploadTopicList', 'uploadTopicSubmissionRateConfig', 'uploadTopicApplyContentAlignment', 'uploadTopicVideoHostAllowed', 'uploadTopicValidateImageFile']
        : ['editTopicApplyContentAlignment'];
    foreach ($helpers as $helper) {
        if (!preg_match('/^function ' . $helper . '\([\s\S]*?^\}/m', $source, $match)) throw new RuntimeException('Missing request helper ' . $helper);
        eval('use App\\Modules\\TopicWorkflow\\Services\\TopicFormRules; ' . $match[0]);
    }
    $start = strpos($source, $mode === 'upload' ? 'if (($_SERVER["REQUEST_METHOD"]' : "if ((\$_SERVER['REQUEST_METHOD']");
    $end = strpos($source, '    $moderationDecision =', $start);
    if ($start === false || $end === false) throw new RuntimeException('Missing POST validation boundary');
    return $sources[$mode] = 'use App\\Modules\\TopicWorkflow\\Services\\TopicFormRules; ' . substr($source, $start, $end - $start) . "\n}";
}

function topicFormFixtureRequest(string $mode, array $input, array $files = [], array $settings = []): array
{
    $source = topicFormFixtureSource($mode);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $input + ['_token'=>'fixture-csrf', 'upload_submit_token'=>'fixture-submit', 'title'=>'A valid example mod', 'content'=>'<p>A sufficiently long description.</p>', 'category_id'=>'1', 'keep_media'=>[11, 12]];
    $_FILES = $files;
    $_SESSION = ['_auth_user_id'=>1, 'upload_topic_submit_token'=>'fixture-submit'];
    $pdo = null;
    $categoryOptions = [['id'=>1]];
    $userUploadRequiresApproval = true;
    $defaultContentAlign = 'center';
    $primaryMediaId = 11;
    $galleryImages = [['id'=>12]];
    $minTitleLength = 3;
    $maxTitleLength = 150;
    $minContentLength = (int) ($settings['user_upload_min_content_length'] ?? 10);
    $requireAuthor = ($settings['user_upload_require_author'] ?? '0') === '1';
    $requireVersion = ($settings['user_upload_require_version'] ?? '0') === '1';
    $requireDownloadLink = ($settings['user_upload_require_download_link'] ?? '0') === '1';
    $requireCover = ($settings['user_upload_require_cover'] ?? '0') === '1';
    $requireGallery = ($settings['user_upload_require_gallery'] ?? '0') === '1';
    $allowVideoUrl = ($settings['user_upload_allow_video_url'] ?? '1') === '1';
    $allowedVideoHosts = array_filter(explode(',', $settings['user_upload_allowed_video_hosts'] ?? 'youtube.com,youtu.be,vimeo.com'));
    $maxImages = (int) ($settings['user_upload_max_images'] ?? 10);
    $coverMaxSizeMb = $galleryMaxSizeMb = $attachmentMaxSizeMb = 10;
    // These fixture cases do not require media unless explicitly requested.
    $settings += ['user_upload_require_cover'=>'0', 'user_upload_require_gallery'=>'0'];
    try {
        eval($source);
        return ['status'=>200, 'download_lines'=>$downloadLines, 'video_url'=>$videoUrl, 'gallery'=>$_FILES['topic_images_files']];
    } catch (TopicFormFixtureResponse $response) {
        return ['status'=>$response->status, 'message'=>$response->getMessage()];
    }
}
