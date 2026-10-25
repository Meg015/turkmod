<?php
declare(strict_types=1);
require_once __DIR__ . '/gif-sanitizer.php';

/** Shared by browser uploads and downloaded bot images. */
function mediaImageSettings(?PDO $pdo = null): array
{
    $settings = function_exists('getAdminSettings')
        ? getAdminSettings($pdo)
        : (class_exists(\App\Core\AppSettings::class) ? \App\Core\AppSettings::instance($pdo)->all() : []);

    return array_replace([
        'webp_enabled' => '1', 'webp_quality' => '82', 'webp_keep_original' => '0',
        'auto_resize_images' => '1', 'image_resize_width' => '1920', 'image_resize_height' => '1080',
        'thumbnail_enabled' => '1', 'thumbnail_width' => '400', 'thumbnail_height' => '300',
        'thumbnail_crop' => '0', 'image_strip_metadata' => '1', 'image_sharpen' => '1',
        'watermark_enabled' => '0',
    ], $settings);
}

function mediaInspectImage(string $path): array
{
    $info = @getimagesize($path);
    $extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_AVIF => 'avif'];
    if (!is_array($info) || !isset($extensions[$info[2]]) || $info[0] < 1 || $info[1] < 1) {
        throw new RuntimeException('Görsel dosyası bozuk veya desteklenmeyen biçimde.');
    }
    $pixels = (float)$info[0] * $info[1];
    if ($pixels > 24000000 || $info[0] > 32768 || $info[1] > 32768 || filesize($path) > 50 * 1024 * 1024) {
        throw new RuntimeException('Görsel çok büyük; en fazla 24 megapiksel ve 50 MB desteklenir.');
    }
    $limit = trim((string)ini_get('memory_limit'));
    if ($limit !== '' && $limit !== '-1') {
        $bytes = (float)$limit * match (strtolower(substr($limit, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
        if (memory_get_usage(true) + $pixels * 12 + 16 * 1024 * 1024 > $bytes) {
            throw new RuntimeException('Bu görseli işlemek için yeterli bellek yok; daha küçük bir görsel yükleyin.');
        }
    }
    $info['extension'] = $extensions[$info[2]];
    return $info;
}

/** Strip metadata and appended content, keeping format, dimensions and GIF animation. */
function mediaSanitizeImage(string $filePath): void
{
    $info = mediaInspectImage($filePath);
    $image = mediaCreateImageFromFile($filePath, $info['extension']);
    if ($image === null) {
        throw new RuntimeException('Görsel dosyası çözümlenemedi.');
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    if ($info[2] === IMAGETYPE_GIF) {
        $clean = mediaSanitizeGifData((string) file_get_contents($filePath), $info[0], $info[1]);
        $temporary = @tempnam(dirname($filePath), '.tm-image-');
        if ($temporary === false || realpath(dirname($temporary)) !== realpath(dirname($filePath))) {
            if (is_string($temporary)) { @unlink($temporary); }
            throw new RuntimeException('Görsel için geçici dosya oluşturulamadı.');
        }
    } else {
        $temporary = mediaEncodeImageTemporary($image, dirname($filePath), $info['extension'], [
            'jpeg_quality' => 90, 'webp_quality' => 90,
        ]);
    }
    try {
        if (isset($clean) && (@file_put_contents($temporary, $clean, LOCK_EX) !== strlen($clean) || !@chmod($temporary, 0644))) {
            throw new RuntimeException('GIF güvenli biçimde kaydedilemedi.');
        }
        if (!@rename($temporary, $filePath)) {
            throw new RuntimeException('Görsel güvenli biçimde kaydedilemedi.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

/** @return array{path:string,name:string,stream:resource} Caller must close the reserved stream. */
function mediaReserveUploadFile(string $directory, string $filename): array
{
    if (basename($filename) !== $filename || strpbrk($filename, '/\\') !== false) {
        throw new RuntimeException('Geçersiz görsel dosya adı.');
    }
    $stem = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 180);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    for ($counter = 1; $counter <= 10000; $counter++) {
        $name = $stem . ($counter > 1 ? '-' . $counter : '') . ($extension !== '' ? '.' . $extension : '');
        $path = rtrim(str_replace('\\', '/', $directory), '/') . '/' . $name;
        $stream = @fopen($path, 'xb');
        if ($stream !== false) { return ['path' => $path, 'name' => $name, 'stream' => $stream]; }
        if (!file_exists($path) && !is_link($path)) { break; }
    }
    throw new RuntimeException('Yeni dosya için güvenli kayıt yolu oluşturulamadı.');
}

/** Encode and validate before making any final filename visible. */
function mediaEncodeImageTemporary(\GdImage $image, string $directory, string $extension, array $settings): string
{
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Görsel klasörü oluşturulamadı.');
    }
    $temporary = @tempnam($directory, '.tm-image-');
    if ($temporary === false || realpath(dirname($temporary)) !== realpath($directory)) {
        if (is_string($temporary)) { @unlink($temporary); }
        throw new RuntimeException('Görsel için geçici dosya oluşturulamadı.');
    }
    try {
        $saved = mediaSaveImage($image, $temporary, $extension, $settings);
        clearstatcache(true, $temporary);
        $info = @getimagesize($temporary);
        $expected = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP, 'avif' => IMAGETYPE_AVIF];
        if (!$saved || !is_array($info) || filesize($temporary) < 1 || $info[2] !== ($expected[$extension] ?? 0)
            || $info[0] !== imagesx($image) || $info[1] !== imagesy($image)) {
            throw new RuntimeException('Görsel dönüştürülemedi; dosya kaydedilmedi.');
        }
        if (!@chmod($temporary, 0644)) {
            throw new RuntimeException('Görsel dosyası izinleri ayarlanamadı.');
        }
        return $temporary;
    } catch (Throwable $e) {
        @unlink($temporary);
        throw $e;
    }
}

function mediaImageNameOccupied(string $candidate, string $input): bool
{
    if ($candidate !== $input && (file_exists($candidate) || is_link($candidate))) {
        return true;
    }
    $stem = pathinfo($candidate, PATHINFO_FILENAME);
    $directory = dirname($candidate);
    foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'] as $extension) {
        $sibling = $directory . '/' . $stem . '.' . $extension;
        if ($sibling !== $input && $sibling !== $candidate && file_exists($sibling)) {
            return true;
        }
        foreach (['thumbnails/', 'thumbnails/800/'] as $prefix) {
            if (file_exists($directory . '/' . $prefix . $stem . '.' . $extension)) {
                return true;
            }
        }
    }
    return false;
}

/** Both environments use same-directory hard links for atomic no-clobber publication. */
function mediaPublishNewImage(string $temporary, string $destination): void
{
    if (!@link($temporary, $destination)) {
        throw new RuntimeException('Görsel güvenli biçimde kaydedilemedi.');
    }
    if (!@unlink($temporary)) {
        @unlink($destination);
        throw new RuntimeException('Görselin geçici dosyası temizlenemedi.');
    }
}

/** @return array<string, string> Destination => staged, validated image. */
function mediaStageImagePreviews(\GdImage $image, string $sourcePath, array $settings): array
{
    $width = imagesx($image);
    $height = imagesy($image);
    $smallW = max(50, min(2000, (int)($settings['thumbnail_width'] ?? 400)));
    $smallH = max(50, min(2000, (int)($settings['thumbnail_height'] ?? 300)));
    $crop = ($settings['thumbnail_crop'] ?? '0') === '1';
    $webp = ($settings['webp_enabled'] ?? '1') === '1';
    $extension = $webp ? 'webp' : strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $stem = pathinfo($sourcePath, PATHINFO_FILENAME);
    $directory = dirname($sourcePath) . '/thumbnails';
    $largeH = max(1, (int)round(800 * $smallH / $smallW));
    $staged = [];
    try {
        foreach ([[$smallW, $smallH, $directory], [800, $largeH, $directory . '/800']] as [$maxW, $maxH, $targetDirectory]) {
            if ($crop) {
                $scale = min(1, $width / $maxW, $height / $maxH);
                $newW = max(1, (int)round($maxW * $scale));
                $newH = max(1, (int)round($maxH * $scale));
                $ratio = max($newW / $width, $newH / $height);
                $srcW = min($width, max(1, (int)round($newW / $ratio)));
                $srcH = min($height, max(1, (int)round($newH / $ratio)));
                $srcX = (int)floor(($width - $srcW) / 2);
                $srcY = (int)floor(($height - $srcH) / 2);
            } else {
                $ratio = min(1, $maxW / $width, $maxH / $height);
                $newW = max(1, (int)round($width * $ratio));
                $newH = max(1, (int)round($height * $ratio));
                $srcX = $srcY = 0;
                $srcW = $width;
                $srcH = $height;
            }
            $preview = imagecreatetruecolor($newW, $newH);
            imagealphablending($preview, false);
            imagesavealpha($preview, true);
            if (!imagecopyresampled($preview, $image, 0, 0, $srcX, $srcY, $newW, $newH, $srcW, $srcH)) {
                throw new RuntimeException('Önizleme oluşturulamadı.');
            }
            $destination = $targetDirectory . '/' . $stem . '.' . $extension;
            $staged[$destination] = mediaEncodeImageTemporary($preview, $targetDirectory, $extension, $settings);
            unset($preview);
        }
        return $staged;
    } catch (Throwable $e) {
        mediaDeleteProcessedFiles(array_values($staged));
        throw $e;
    }
}

function mediaDeleteProcessedFiles(array $paths): void
{
    $previewDirectories = [];
    foreach (array_unique($paths) as $path) {
        if (is_string($path) && (is_file($path) || is_link($path))) {
            @unlink($path);
        }
        if (is_string($path)) {
            $directory = str_replace('\\', '/', dirname($path));
            if (basename($directory) === '800' && basename(dirname($directory)) === 'thumbnails') {
                $previewDirectories[$directory] = true;
                $previewDirectories[dirname($directory)] = true;
            } elseif (basename($directory) === 'thumbnails') {
                $previewDirectories[$directory] = true;
            }
        }
    }
    uksort($previewDirectories, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach (array_keys($previewDirectories) as $directory) {
        @rmdir($directory);
    }
}

/**
 * Process new uploads; callers own the input and remove created_files on a failed DB insert.
 * @return array{path:string,name:string,converted:bool,thumbnail:?string,original_size:int,final_size:int,mime_type:string,created_files:array}
 */
function mediaProcessImage(string $filePath, array $imageSettings): array
{
    $filePath = str_replace('\\', '/', $filePath);
    if (!extension_loaded('gd')) {
        throw new RuntimeException('Görsel işleme için PHP GD desteği etkin olmalıdır.');
    }
    $webp = ($imageSettings['webp_enabled'] ?? '1') === '1';
    if ($webp && (!function_exists('imagewebp') || empty(gd_info()['WebP Support']))) {
        throw new RuntimeException('WebP desteği etkin değil; görsel kaydedilemedi.');
    }
    $info = mediaInspectImage($filePath);
    $originalSize = (int)filesize($filePath);
    $image = mediaCreateImageFromFile($filePath, $info['extension']);
    if ($image === null) {
        throw new RuntimeException('Görsel çözümlenemedi; bu biçim veya animasyon desteklenmiyor.');
    }
    if (!imageistruecolor($image)) { imagepalettetotruecolor($image); }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $maxW = max(1, min(8192, (int)($imageSettings['image_resize_width'] ?? 1920)));
    $maxH = max(1, min(8192, (int)($imageSettings['image_resize_height'] ?? 1080)));
    if (($imageSettings['auto_resize_images'] ?? '0') === '1' && (imagesx($image) > $maxW || imagesy($image) > $maxH)) {
        $image = mediaResizeImage($image, imagesx($image), imagesy($image), $maxW, $maxH);
        if (($imageSettings['image_sharpen'] ?? '1') === '1') { mediaSharpenImage($image); }
    }
    if (($imageSettings['watermark_enabled'] ?? '0') === '1' && trim((string)($imageSettings['watermark_text'] ?? '')) !== '') {
        mediaApplyWatermark($image, trim($imageSettings['watermark_text']), $imageSettings);
    }
    $extension = $webp ? 'webp' : $info['extension'];
    $stem = pathinfo($filePath, PATHINFO_FILENAME);
    $directory = dirname($filePath);
    $target = $directory . '/' . $stem . '.' . $extension;
    $counter = 2;
    while (mediaImageNameOccupied($target, $filePath)) {
        $target = $directory . '/' . $stem . '-' . $counter++ . '.' . $extension;
    }
    $temporary = null;
    $staged = [];
    $created = [];
    try {
        $temporary = mediaEncodeImageTemporary($image, $directory, $extension, $imageSettings);
        if ($target !== $filePath) {
            // A concurrent upload may have chosen this same stem after the first check.
            while (true) {
                if (!mediaImageNameOccupied($target, $filePath) && @link($temporary, $target)) { break; }
                if (!file_exists($target) && !mediaImageNameOccupied($target, $filePath)) {
                    throw new RuntimeException('Görsel güvenli biçimde kaydedilemedi.');
                }
                $target = $directory . '/' . $stem . '-' . $counter++ . '.' . $extension;
            }
            $created[] = $target;
            if (!@unlink($temporary)) { throw new RuntimeException('Görselin geçici dosyası temizlenemedi.'); }
            $temporary = null;
        }
        if (($imageSettings['thumbnail_enabled'] ?? '1') === '1') {
            $staged = mediaStageImagePreviews($image, $target, $imageSettings);
            foreach ($staged as $destination => $previewTemporary) {
                mediaPublishNewImage($previewTemporary, $destination);
                $created[] = $destination;
            }
        }
        if ($target === $filePath) {
            if (!@rename($temporary, $target)) {
                throw new RuntimeException('Görsel dosyası güncellenemedi.');
            }
            $temporary = null;
            $created[] = $target;
        }
        if ($target !== $filePath && ($imageSettings['webp_keep_original'] ?? '0') !== '1' && !@unlink($filePath)) {
            throw new RuntimeException('Görselin geçici orijinali kaldırılamadı.');
        }
        clearstatcache(true, $target);
        return [
            'path' => $target, 'name' => basename($target), 'converted' => $target !== $filePath,
            'thumbnail' => array_key_first($staged), 'original_size' => $originalSize,
            'final_size' => (int)filesize($target), 'mime_type' => (string)getimagesize($target)['mime'],
            'created_files' => array_values(array_unique(array_merge([$filePath], $created))),
        ];
    } catch (Throwable $e) {
        mediaDeleteProcessedFiles(array_merge($created, array_values($staged), $temporary === null ? [] : [$temporary]));
        @rmdir($directory . '/thumbnails/800');
        @rmdir($directory . '/thumbnails');
        throw $e;
    }
}

function mediaCreateThumbnail(string $sourcePath, array $settings): ?string
{
    $image = mediaCreateImageFromFile($sourcePath, strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)));
    if ($image === null) { return null; }
    $staged = mediaStageImagePreviews($image, $sourcePath, $settings);
    try {
        foreach ($staged as $destination => $temporary) {
            if (!@rename($temporary, $destination)) { throw new RuntimeException('Önizleme kaydedilemedi.'); }
        }
        return array_key_first($staged);
    } finally {
        mediaDeleteProcessedFiles(array_values($staged));
    }
}
