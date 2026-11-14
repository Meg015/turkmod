<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/TemplateRenderer.php';
require_once __DIR__ . '/fixtures/topic-form-validation.php';

use App\Modules\TopicWorkflow\Services\TopicFormRules;

$checks = 0;
function formRuleAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    $GLOBALS['checks']++;
}

$root = dirname(__DIR__);
$renderer = new TemplateRenderer();
$renderer->setTemplateResolver(static fn(string $path): string => file_get_contents($root . '/themes/turkmod/' . $path));
foreach ([false, true] as $edit) {
    $html = $renderer->renderFile($root . '/themes/turkmod/upload-form.tpl', ['upload' => [
        'is_edit' => $edit, 'download_links' => [['name' => 'Resmî kaynak', 'url' => 'https://example.test/mod.zip']],
    ]]);
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $input = (new DOMXPath($document))->query('//*[@id="dlHidden"]')->item(0);
    $post = [$input->getAttribute('name') => 'Resmî kaynak|https://example.test/mod.zip'];
    formRuleAssert(TopicFormRules::downloadLines($post) === 'Resmî kaynak|https://example.test/mod.zip', 'Rendered download payload is lost');
    formRuleAssert($input->getAttribute('name') === 'download_lines', 'Template does not use the server download field name');
    $request = topicFormFixtureRequest($edit ? 'edit' : 'upload', $post, [], ['user_upload_require_download_link'=>'1']);
    formRuleAssert($request['status'] === 200 && $request['download_lines'] === 'Resmî kaynak|https://example.test/mod.zip', 'Actual POST validation loses rendered download links');
}

foreach (['download_lines', 'topic_download_links'] as $field) {
    formRuleAssert(TopicFormRules::downloadLines([$field => ' Drive|https://example.test/mod ']) === 'Drive|https://example.test/mod', 'Legacy payload is not accepted');
}
$rows = ['dl_name' => ['Drive', '', ''], 'dl_url' => ['https://example.test/mod', '', 'https://mirror.test/mod']];
formRuleAssert(TopicFormRules::downloadLines($rows) === "Drive|https://example.test/mod\nLink|https://mirror.test/mod", 'Download rows depend on JavaScript');
formRuleAssert(TopicFormRules::downloadLines($rows + ['download_lines' => 'Old|https://old.test/mod']) === "Drive|https://example.test/mod\nLink|https://mirror.test/mod", 'Hidden download text overrides current rows');
formRuleAssert(TopicFormRules::downloadLines(['dl_name'=>[2=>'Name|with separator'], 'dl_url'=>[2=>'https://example.test/mod']]) === 'Name with separator|https://example.test/mod', 'Source label corrupts serialized links');
foreach (['', "\n \n"] as $links) {
    formRuleAssert(TopicFormRules::downloadError($links, true) !== null, 'Missing required download accepted');
    formRuleAssert(TopicFormRules::downloadError($links, false) === null, 'Missing optional download rejected');
}
foreach (['Link|not-a-url', 'Link|ftp://example.test/mod', 'Link|javascript:alert(1)', "OK|https://example.test/mod\nBad|invalid", 'Bad|https://'] as $links) {
    foreach ([false, true] as $required) formRuleAssert(TopicFormRules::downloadError($links, $required) !== null, 'Invalid download accepted');
}
formRuleAssert(TopicFormRules::downloadError("One|https://example.test/mod\nTwo|http://mirror.test/mod", true) === null, 'Valid HTTP(S) downloads rejected');
foreach (['https://youtube.com/watch?v=1', 'https://www.youtube.com/watch?v=1'] as $url) formRuleAssert(TopicFormRules::videoAllowed($url, ['youtube.com']), 'Allowed video rejected');
foreach (['https://youtube.com.evil.test/watch', 'ftp://youtube.com/watch', 'javascript:alert(1)', 'invalid'] as $url) formRuleAssert(!TopicFormRules::videoAllowed($url, ['youtube.com']), 'Invalid video/provider accepted');
formRuleAssert(TopicFormRules::videoAllowed('https://example.test/video', []), 'Empty provider list rejects valid web video');
formRuleAssert(!TopicFormRules::videoAllowed('ftp://example.test/video', []), 'Empty provider list bypasses protocol validation');
foreach (['<p><br></p>', '<p>&nbsp;&#160;&#x2003;</p>', '   '] as $content) formRuleAssert(TopicFormRules::contentText($content) === '', 'Invisible HTML counts as description');
formRuleAssert(TopicFormRules::contentText('<p>Türkçe &amp; &lt;mod&gt;</p>') === 'Türkçe & <mod>', 'Visible content/entities changed');
formRuleAssert(TopicFormRules::contentText('<p>A   B</p>') === 'A   B', 'Internal description spacing changed');

$imagePath = tempnam(sys_get_temp_dir(), 'topic-image-');
file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$image = ['name'=>'mod.png', 'type'=>'image/png', 'tmp_name'=>$imagePath, 'error'=>UPLOAD_ERR_OK, 'size'=>filesize($imagePath)];
try {
    formRuleAssert(TopicFormRules::imageError($image, 'Kapak', [], 10) === null, 'Valid PNG rejected');
    formRuleAssert(TopicFormRules::imageError($image, 'Kapak', ['user_upload_image_min_width'=>'2'], 10) !== null, 'Image minimum dimensions ignored');
    formRuleAssert(TopicFormRules::imageError($image, 'Kapak', ['user_upload_allowed_image_ext'=>'jpg'], 10) !== null, 'Image extension settings ignored');
    formRuleAssert(TopicFormRules::imageError(array_replace($image, ['size'=>11*1024*1024]), 'Kapak', [], 10) !== null, 'Image size limit ignored');
    foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION] as $error) {
        formRuleAssert(TopicFormRules::imageError(array_replace($image, ['error'=>$error]), 'Galeri', [], 10) !== null, 'Failed upload satisfies required media');
    }
    formRuleAssert(TopicFormRules::imageError(['name'=>'', 'error'=>UPLOAD_ERR_NO_FILE], 'Galeri', [], 10) === null, 'Empty optional file rejected');
    formRuleAssert(TopicFormRules::imageError(array_replace($image, ['tmp_name'=>'']), 'Kapak', [], 10) !== null, 'Missing temporary file accepted');
    formRuleAssert(TopicFormRules::attachmentError(array_replace($image, ['error'=>UPLOAD_ERR_PARTIAL]), 10) !== null, 'Incomplete attachment silently accepted');
    formRuleAssert(TopicFormRules::attachmentError(array_replace($image, ['size'=>11*1024*1024]), 10) !== null, 'Attachment limit ignored');
    $sparse = [];
    foreach ($image as $key=>$value) $sparse[$key] = [2=>$value];
    $gallery = TopicFormRules::galleryFiles($sparse);
    formRuleAssert($gallery['name'] === ['mod.png'] && $gallery['tmp_name'] === [$imagePath], 'Sparse gallery skips validation/storage');
    formRuleAssert(TopicFormRules::galleryFiles(['name'=>[''], 'error'=>[UPLOAD_ERR_NO_FILE]])['name'] === [], 'Empty gallery placeholder counts as image');
    foreach (['upload', 'edit'] as $mode) {
        formRuleAssert(topicFormFixtureRequest($mode, $rows, [], ['user_upload_require_download_link'=>'1'])['status'] === 200, 'Actual request loses download rows without JavaScript');
        formRuleAssert(topicFormFixtureRequest($mode, ['download_lines'=>'Bad|ftp://example.test/mod'])['status'] === 422, 'Actual request accepts invalid downloads');
        formRuleAssert(topicFormFixtureRequest($mode, ['topic_video_url'=>'https://disallowed.test/video'])['status'] === 422, 'Actual request ignores video provider settings');
        formRuleAssert(topicFormFixtureRequest($mode, ['topic_video_url'=>'ftp://youtube.com/video'])['status'] === 422, 'Actual request accepts non-web video');
        formRuleAssert(topicFormFixtureRequest($mode, ['content'=>'<p>&nbsp;&nbsp;</p>'], [], ['user_upload_min_content_length'=>'0', 'topic_require_excerpt'=>'0'])['status'] === 422, 'Actual request accepts invisible description with minimum disabled');
        formRuleAssert(topicFormFixtureRequest($mode, [], ['topic_first_image_file'=>array_replace($image, ['error'=>UPLOAD_ERR_INI_SIZE])])['status'] === 422, 'Actual request silently accepts a failed cover upload');
        formRuleAssert(topicFormFixtureRequest($mode, [], ['topic_images_files'=>$sparse], ['user_upload_image_min_width'=>'2'])['status'] === 422, 'Actual request skips sparse gallery validation');
        formRuleAssert(topicFormFixtureRequest($mode, [], ['attachment'=>array_replace($image, ['error'=>UPLOAD_ERR_PARTIAL])])['status'] === 422, 'Actual request ignores failed attachment');
    }
    formRuleAssert(topicFormFixtureRequest('edit', [], [], ['user_upload_require_cover'=>'1', 'user_upload_require_gallery'=>'1'])['status'] === 200, 'Actual edit request rejects retained required media');
    formRuleAssert(topicFormFixtureRequest('edit', ['keep_media'=>[]], [], ['user_upload_require_cover'=>'1', 'user_upload_require_gallery'=>'1'])['status'] === 422, 'Actual edit request accepts removal of all required media');
} finally { unlink($imagePath); }

echo "Topic form rules: {$checks} checks passed.\n";
