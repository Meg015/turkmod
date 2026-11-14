<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__, 2);
$output = $root . '/output/playwright';
if (!is_dir($output)) mkdir($output, 0775, true);
require $root . '/includes/TemplateRenderer.php';
$renderer = new TemplateRenderer();
$renderer->setTemplateResolver(static fn (string $path): string => file_get_contents($root . '/themes/turkmod/' . $path));
$upload = [
    'is_edit' => false, 'form_action' => '/fixture-send', 'csrf_token' => 'fixture-csrf', 'has_submit_token' => true, 'submit_token' => 'fixture-submit',
    'categories' => [['id' => '', 'label' => 'Kategori seçin', 'selected' => true], ['id' => 1, 'label' => 'ETS 2', 'selected' => false]],
    'download_links' => [['name' => '', 'url' => '']], 'wizard_enabled' => true, 'allow_step_skip' => true, 'hide_inactive_panels' => true,
    'min_title_length' => 1, 'max_title_length' => 200, 'min_content_length' => 1, 'max_images' => 10, 'video_allowed' => true,
    'lock_after_submit' => 1, 'allowed_image_ext' => 'jpg,png,webp', 'attachment_accept' => '.zip',
    'cover_max_size_mb' => 10, 'gallery_max_size_mb' => 10, 'attachment_max_size_mb' => 10,
];
$html = $renderer->renderFile($root . '/themes/turkmod/upload-form.tpl', ['upload' => $upload]);
file_put_contents($output . '/upload-form-fixture.html', $html);
$editUpload = array_replace($upload, [
    'is_edit' => true, 'has_submit_token' => false,
    'title_value' => 'ETS2 - Scania S Özel Kabin Paketi',
    'content_value' => '<p>Scania S için yenilenmiş kabin detayları, aydınlatmalar ve uyumlu aksesuarlar. Kurulum dosyasını mod klasörüne ekleyip oyun içinden etkinleştirin.</p>',
    'author_value' => 'Türk Mod Topluluğu', 'version_value' => '1.62',
    'categories' => [['id' => '', 'label' => 'Kategori seçin', 'selected' => false], ['id' => 1, 'label' => 'ETS 2', 'selected' => true]],
    'download_links' => [['name' => 'Resmî kaynak', 'url' => 'https://example.test/scania-s']],
    'has_cover_media' => true, 'has_gallery_media' => true,
    'cover_media' => [['id' => 11, 'url' => '/turkmod/themes/turkmod/images/preview.png']],
    'gallery_media' => [['id' => 12, 'url' => '/turkmod/themes/turkmod/images/preview.png']],
]);
file_put_contents($output . '/edit-form-fixture.html', $renderer->renderFile($root . '/themes/turkmod/upload-form.tpl', ['upload' => $editUpload]));
$topics = file_get_contents($root . '/admin/topics.php');
$start = strpos($topics, '<div class="moderation-note-modal ui-admin-modal-overlay" id="moderationActionNoteModal"');
$end = strpos($topics, '<script src=', $start);
file_put_contents($output . '/revision-modal-fixture.html', substr($topics, $start, $end - $start));
echo "Fixtures rendered.\n";
