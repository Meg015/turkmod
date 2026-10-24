<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/src/Engine/Media/Support/helpers.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Scraper/Support/ScraperEngine.php';
require_once dirname(__DIR__) . '/includes/src/Engine/Scraper/Support/helpers.php';

function mediaTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function mediaTestRemoveTree(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir() && !$file->isLink()) {
            mediaTestRemoveTree($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($directory);
}

function mediaTestFixture(string $path, string $format, int $width = 1600, int $height = 900): void
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 35, 130, 200, 65));
    imagefilledrectangle($image, (int)($width / 3), 0, $width - 1, $height - 1, imagecolorallocate($image, 220, 90, 40));
    match ($format) {
        'jpg' => imagejpeg($image, $path, 90),
        'png' => imagepng($image, $path),
        'gif' => imagegif($image, $path),
        'webp' => imagewebp($image, $path, 90),
        'avif' => imageavif($image, $path, 60),
    };
}

function mediaTestWebp(string $path, ?array $dimensions = null): void
{
    $info = @getimagesize($path);
    mediaTestAssert(is_array($info) && $info[2] === IMAGETYPE_WEBP, 'Invalid WebP: ' . $path);
    mediaTestAssert(str_ends_with($path, '.webp') && filesize($path) > 0, 'Invalid filename/size');
    mediaTestAssert(imagecreatefromwebp($path) !== false, 'WebP cannot be decoded');
    if ($dimensions !== null) {
        mediaTestAssert([$info[0], $info[1]] === $dimensions, 'Unexpected dimensions: ' . json_encode([$info[0], $info[1]]));
    }
}

final class MediaFixtureScraper extends \App\Engine\Scraper\Support\ScraperEngine
{
    public string $payload = '';

    protected function fetchBinary(string $url, array $customHeaders = []): ?string
    {
        return $this->payload;
    }
}

$settings = [
    'webp_enabled' => '1', 'webp_quality' => '82', 'webp_keep_original' => '0',
    'auto_resize_images' => '1', 'image_resize_width' => '1920', 'image_resize_height' => '1080',
    'thumbnail_enabled' => '1', 'thumbnail_width' => '400', 'thumbnail_height' => '300',
    'thumbnail_crop' => '0', 'image_sharpen' => '0', 'watermark_enabled' => '0',
];
$testId = bin2hex(random_bytes(8));
$directory = str_replace('\\', '/', dirname(__DIR__)) . '/storage/media-regression-' . $testId;
$botRelative = 'uploads/konu/media-regression-' . $testId;
$botDirectory = dirname(__DIR__) . '/' . $botRelative;
mkdir($directory, 0755, true);
$checks = 0;

try {
    mediaTestAssert(realpath(mediaProjectRoot(dirname(__DIR__) . '/uploads')) === realpath(dirname(__DIR__)), 'Media project root points outside application');
    $checks++;
    $firstReservation = mediaReserveUploadFile($directory, 'reserved.jpg');
    $secondReservation = mediaReserveUploadFile($directory, 'reserved.jpg');
    fclose($firstReservation['stream']);
    fclose($secondReservation['stream']);
    mediaTestAssert($firstReservation['path'] !== $secondReservation['path'], 'Upload reservation collision');
    unlink($firstReservation['path']);
    unlink($secondReservation['path']);
    $checks++;
    foreach (['jpg', 'png', 'gif', 'webp'] as $format) {
        $input = $directory . '/format-' . $format . '.' . $format;
        mediaTestFixture($input, $format);
        $result = mediaProcessImage($input, $settings);
        mediaTestWebp($result['path'], [1600, 900]);
        mediaTestWebp((string)$result['thumbnail'], [400, 225]);
        mediaTestWebp(dirname($result['thumbnail']) . '/800/' . basename($result['thumbnail']), [800, 450]);
        mediaTestAssert($result['final_size'] === filesize($result['path']), 'Wrong final byte count');
        mediaTestAssert($result['mime_type'] === 'image/webp', 'Wrong final MIME');
        mediaTestAssert($format === 'webp' || !file_exists($input), 'Original was not removed');
        if ($format === 'png') {
            $decoded = imagecreatefromwebp($result['path']);
            mediaTestAssert(((imagecolorat($decoded, 10, 10) >> 24) & 127) > 40, 'Transparency lost');
        }
        $checks++;
    }

    $mislabeled = $directory . '/mislabeled.jpg';
    mediaTestFixture($mislabeled, 'png');
    mediaTestWebp(mediaProcessImage($mislabeled, $settings)['path']);
    $checks++;

    $first = $directory . '/collision.jpg';
    mediaTestFixture($first, 'jpg');
    $firstResult = mediaProcessImage($first, $settings);
    $originalHash = hash_file('sha256', $firstResult['path']);
    $previewHash = hash_file('sha256', $firstResult['thumbnail']);
    $second = $directory . '/collision.png';
    mediaTestFixture($second, 'png', 1200, 1200);
    $secondResult = mediaProcessImage($second, $settings);
    mediaTestAssert($firstResult['path'] !== $secondResult['path'], 'Conversion overwrote an existing image');
    mediaTestAssert(hash_file('sha256', $firstResult['path']) === $originalHash, 'First source changed');
    mediaTestAssert(hash_file('sha256', $firstResult['thumbnail']) === $previewHash, 'First preview changed');
    $checks++;

    $small = $directory . '/small.png';
    mediaTestFixture($small, 'png', 40, 20);
    $smallResult = mediaProcessImage($small, $settings);
    mediaTestWebp($smallResult['thumbnail'], [40, 20]);
    mediaTestWebp(dirname($smallResult['thumbnail']) . '/800/' . basename($smallResult['thumbnail']), [40, 20]);
    $checks++;

    $large = $directory . '/large.jpg';
    mediaTestFixture($large, 'jpg', 2400, 1600);
    $largeResult = mediaProcessImage($large, $settings);
    mediaTestWebp($largeResult['path'], [1620, 1080]);
    $checks++;

    $cropped = $directory . '/cropped.png';
    mediaTestFixture($cropped, 'png');
    $cropResult = mediaProcessImage($cropped, array_replace($settings, ['thumbnail_crop' => '1']));
    mediaTestWebp($cropResult['thumbnail'], [400, 300]);
    mediaTestWebp(dirname($cropResult['thumbnail']) . '/800/' . basename($cropResult['thumbnail']), [800, 600]);
    $checks++;

    $retained = $directory . '/retained.jpg';
    mediaTestFixture($retained, 'jpg');
    $retainedResult = mediaProcessImage($retained, array_replace($settings, ['webp_keep_original' => '1']));
    mediaTestAssert(is_file($retained) && is_file($retainedResult['path']), 'Keep-original setting ignored');
    $checks++;

    $disabled = $directory . '/disabled.png';
    mediaTestFixture($disabled, 'png');
    $disabledResult = mediaProcessImage($disabled, array_replace($settings, ['webp_enabled' => '0', 'thumbnail_enabled' => '0']));
    mediaTestAssert($disabledResult['path'] === $disabled && getimagesize($disabled)[2] === IMAGETYPE_PNG, 'Disabled conversion ignored');
    mediaTestAssert($disabledResult['thumbnail'] === null, 'Disabled thumbnail ignored');
    $checks++;

    $bad = $directory . '/corrupt.jpg';
    file_put_contents($bad, 'not an image');
    $rejected = false;
    try { mediaProcessImage($bad, $settings); } catch (RuntimeException $e) { $rejected = true; }
    mediaTestAssert($rejected && file_get_contents($bad) === 'not an image', 'Invalid image accepted or modified');
    $checks++;

    $oversized = $directory . '/oversized.png';
    $header = "\x89PNG\r\n\x1a\n";
    $chunk = 'IHDR' . pack('NNCCCCC', 50000, 50000, 8, 2, 0, 0, 0);
    file_put_contents($oversized, $header . pack('N', 13) . $chunk . pack('N', crc32($chunk)));
    $rejected = false;
    try { mediaProcessImage($oversized, $settings); } catch (RuntimeException $e) { $rejected = true; }
    mediaTestAssert($rejected, 'Oversized image accepted');
    $checks++;

    $failureDir = $directory . '/failure';
    mkdir($failureDir);
    file_put_contents($failureDir . '/thumbnails', 'blocked directory');
    $failureInput = $failureDir . '/image.png';
    mediaTestFixture($failureInput, 'png');
    $before = hash_file('sha256', $failureInput);
    $rejected = false;
    try { mediaProcessImage($failureInput, $settings); } catch (RuntimeException $e) { $rejected = true; }
    mediaTestAssert($rejected && hash_file('sha256', $failureInput) === $before, 'Failure did not preserve input');
    mediaTestAssert(count(glob($failureDir . '/*')) === 2, 'Failed conversion left residue');
    $checks++;

    $engine = new MediaFixtureScraper(['bot_image_save_path' => $botRelative], $settings);
    $engine->payload = file_get_contents($directory . '/retained.jpg');
    $saved = $engine->downloadImage('https://example.invalid/cover.png', 'fixture', 'WebP Cover');
    mediaTestAssert(is_string($saved) && str_starts_with($saved, $botRelative . '/'), 'Bot failed to return local path');
    mediaTestWebp(dirname(__DIR__) . '/' . $saved);
    $metadata = scraperLocalImageMetadata($saved);
    mediaTestAssert($metadata['mime_type'] === 'image/webp' && $metadata['size'] === filesize(dirname(__DIR__) . '/' . $saved), 'Bot publish metadata mismatch');
    mediaTestAssert(scraperLocalImageMetadata('../composer.json') === null, 'Bot metadata path escaped uploads');
    $secondSaved = $engine->downloadImage('https://example.invalid/cover.jpg', 'fixture', 'WebP Cover');
    mediaTestAssert($secondSaved !== $saved, 'Bot reused existing filename');
    mediaTestWebp(dirname(__DIR__) . '/' . $secondSaved);
    $engine->payload = 'not an image';
    mediaTestAssert($engine->downloadImage('https://example.invalid/bad.jpg', 'fixture', 'Bad') === null, 'Bot accepted corrupt data');
    $engine->payload = str_repeat('x', 101);
    $limited = new MediaFixtureScraper(['bot_image_save_path' => $botRelative, 'bot_max_image_bytes' => 100], $settings);
    $limited->payload = $engine->payload;
    mediaTestAssert($limited->downloadImage('https://example.invalid/big.jpg', 'fixture', 'Too big') === null, 'Bot byte limit ignored');
    $checks++;

    mediaDeleteProcessedFiles($secondResult['created_files']);
    foreach ($secondResult['created_files'] as $path) {
        mediaTestAssert(!is_file($path), 'Failed-save cleanup left an image derivative');
    }
    mediaTestAssert(is_file($firstResult['path']), 'Cleanup removed an unrelated image');
    $checks++;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        mediaTestAssert(!str_starts_with($file->getFilename(), '.tm-image-'), 'Temporary encoder file leaked');
    }
    echo 'PASS: ' . $checks . ' image/bot regression scenarios on PHP ' . PHP_VERSION . PHP_EOL;
} finally {
    mediaTestRemoveTree($directory);
    mediaTestRemoveTree($botDirectory);
}
