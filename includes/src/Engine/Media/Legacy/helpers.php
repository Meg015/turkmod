<?php
/**
 * Media Module — İş mantığı fonksiyonları
 * Dizin: uploads/konu/, uploads/profil/, uploads/genel/
 */

declare(strict_types=1);

function mediaEnsureDirectories(string $uploadBase): void
{
    foreach (['konu', 'profil', 'genel'] as $d) {
        $dir = $uploadBase . DIRECTORY_SEPARATOR . $d;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}

function mediaNormalizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?: '';
    $parts = [];

    foreach (explode('/', $path) as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

function mediaResolvePath(string $uploadBase, string $relativePath): ?string
{
    $relativePath = mediaNormalizeRelativePath($relativePath);
    $baseReal = realpath($uploadBase) ?: $uploadBase;

    if ($relativePath === '') {
        return $baseReal;
    }

    $candidate = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $candidateReal = realpath($candidate);

    if ($candidateReal !== false && ($candidateReal === $baseReal || str_starts_with($candidateReal, $baseReal . DIRECTORY_SEPARATOR))) {
        return $candidateReal;
    }

    if (file_exists($candidate)) {
        $dirReal = realpath(dirname($candidate));
        if ($dirReal !== false) {
            $rebuilt = $dirReal . DIRECTORY_SEPARATOR . basename($candidate);
            if ($rebuilt === $baseReal || str_starts_with($rebuilt, $baseReal . DIRECTORY_SEPARATOR)) {
                return $rebuilt;
            }
        }
    }

    return null;
}

function mediaRelativeToUploads(string $uploadBase, string $fullPath): string
{
    $baseReal = str_replace('\\', '/', realpath($uploadBase) ?: $uploadBase);
    $fullPath = str_replace('\\', '/', $fullPath);

    if ($fullPath === $baseReal || str_starts_with($fullPath, $baseReal . '/')) {
        return ltrim(substr($fullPath, strlen($baseReal)), '/');
    }

    return mediaNormalizeRelativePath(basename($fullPath));
}

function mediaProjectRoot(string $uploadBase): string
{
    $moduleProjectRoot = realpath(dirname(__DIR__, 4));
    if ($moduleProjectRoot !== false) {
        return $moduleProjectRoot;
    }

    return realpath(dirname($uploadBase)) ?: dirname($uploadBase);
}

function mediaSafeImageExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function mediaSafeFileExtensions(): array
{
    return [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'zip', 'rar', '7z',
        'txt', 'md', 'json', 'csv',
        'docx', 'xlsx', 'pptx',
        'mp4', 'webm', 'mp3', 'wav',
    ];
}

function mediaNormalizeAllowedImageExtensions(array $allowedExt): array
{
    $safe = array_flip(mediaSafeImageExtensions());
    $normalized = [];

    foreach ($allowedExt as $ext) {
        $ext = strtolower(ltrim(trim((string) $ext), '.'));
        if ($ext !== '' && isset($safe[$ext])) {
            $normalized[$ext] = true;
        }
    }

    return array_keys($normalized ?: array_flip(mediaSafeImageExtensions()));
}

function mediaNormalizeAllowedFileExtensions(array $allowedExt): array
{
    $safe = array_flip(mediaSafeFileExtensions());
    $normalized = [];

    foreach ($allowedExt as $ext) {
        $ext = strtolower(ltrim(trim((string) $ext), '.'));
        if ($ext !== '' && isset($safe[$ext])) {
            $normalized[$ext] = true;
        }
    }

    return array_keys($normalized ?: array_flip(mediaSafeFileExtensions()));
}

function mediaDetectMimeType(string $filePath): ?string
{
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($filePath);
    }

    return is_string($mime) && $mime !== '' ? $mime : null;
}

function mediaValidateUploadedImageFile(array $file, array $allowedExt, int $maxUploadMB, string $label): ?string
{
    $allowedExt = mediaNormalizeAllowedImageExtensions($allowedExt);
    $name = basename((string) ($file['name'] ?? $label));
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $sizeBytes = (int) ($file['size'] ?? 0);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        return $name . ': Izin verilmeyen uzanti (' . ($ext ?: 'yok') . ').';
    }

    if ($maxUploadMB > 0 && $sizeBytes > $maxUploadMB * 1024 * 1024) {
        return $name . ': Dosya cok buyuk (maks. ' . $maxUploadMB . ' MB).';
    }

    if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
        return $name . ': Gecici dosya okunamadi.';
    }

    $sample = @file_get_contents($tmpPath, false, null, 0, 8192);
    if (is_string($sample) && preg_match('/<\?(?:php|=)?|<script|javascript:|onload\s*=|eval\s*\(|base64_decode/i', $sample)) {
        return $name . ': Dosya icerigi guvenli degil.';
    }

    $validMimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    $mime = mediaDetectMimeType($tmpPath);
    if ($mime === null || !in_array($mime, $validMimes[$ext] ?? [], true)) {
        return $name . ': Dosya icerigi uzantisiyla eslesmiyor.';
    }

    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        return $name . ': Gecersiz gorsel dosyasi.';
    }

    $webpType = defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18;
    $validImageTypes = [
        'jpg' => [IMAGETYPE_JPEG],
        'jpeg' => [IMAGETYPE_JPEG],
        'png' => [IMAGETYPE_PNG],
        'gif' => [IMAGETYPE_GIF],
        'webp' => [$webpType],
    ];

    if (!in_array((int) $imageInfo[2], $validImageTypes[$ext] ?? [], true)) {
        return $name . ': Gorsel tipi uzantiyla eslesmiyor.';
    }

    return null;
}

function mediaValidateUploadedFile(array $file, array $allowedExt, int $maxUploadMB, string $label): ?string
{
    $allowedExt = mediaNormalizeAllowedFileExtensions($allowedExt);
    $name = basename((string) ($file['name'] ?? $label));
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $sizeBytes = (int) ($file['size'] ?? 0);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext === '' || !in_array($ext, $allowedExt, true)) {
        return $name . ': Izin verilmeyen uzanti (' . ($ext ?: 'yok') . ').';
    }

    if ($maxUploadMB > 0 && $sizeBytes > $maxUploadMB * 1024 * 1024) {
        return $name . ': Dosya cok buyuk (maks. ' . $maxUploadMB . ' MB).';
    }

    if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
        return $name . ': Gecici dosya okunamadi.';
    }

    if (in_array($ext, mediaSafeImageExtensions(), true)) {
        $imageAllowedExt = array_values(array_intersect($allowedExt, mediaSafeImageExtensions()));
        return mediaValidateUploadedImageFile($file, $imageAllowedExt, $maxUploadMB, $label);
    }

    $sample = @file_get_contents($tmpPath, false, null, 0, 8192);
    if (is_string($sample) && preg_match('/<\?(?:php|=)?|<script|javascript:|onload\s*=|eval\s*\(|base64_decode/i', $sample)) {
        return $name . ': Dosya icerigi guvenli degil.';
    }

    return null;
}

function mediaUploadFiles(
    PDO $pdo,
    array $files,
    string $uploadBase,
    string $targetDir,
    string $subFolder,
    array $allowedExt,
    int $maxUploadMB,
    ?int $userId = null,
    array $imageSettings = []
): array {
    $uploadedCount = 0;
    $errors = [];
    $allowedExt = mediaNormalizeAllowedFileExtensions($allowedExt);

    if (!in_array($targetDir, ['konu', 'profil', 'genel'], true)) {
        $targetDir = 'genel';
    }

    if ($subFolder !== '') {
        $subFolder = str_replace('\\', '/', trim($subFolder));
        $parts = explode('/', $subFolder);
        $cleanParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $cleanPart = preg_replace('/[^a-z0-9\-_]/i', '-', strtolower($part));
            $cleanPart = preg_replace('/-+/', '-', trim($cleanPart, '-'));
            if ($cleanPart !== '') {
                $cleanParts[] = $cleanPart;
            }
        }
        $subFolder = implode(DIRECTORY_SEPARATOR, $cleanParts);
    }

    $destDir = $uploadBase . DIRECTORY_SEPARATOR . $targetDir;
    if ($subFolder !== '') {
        $destDir .= DIRECTORY_SEPARATOR . $subFolder;
    }
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    if (empty($files['name'][0])) {
        return ['uploaded' => 0, 'errors' => ['Dosya seçilmedi.']];
    }

    $projectRoot = mediaProjectRoot($uploadBase);

    $fileCount = count($files['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $files['name'][$i] . ': Yükleme hatası.';
            continue;
        }

        $origName = basename($files['name'][$i]);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = $origName . ': İzin verilmeyen uzantı (' . $ext . ').';
            continue;
        }

        $sizeBytes = $files['size'][$i];
        $validationError = mediaValidateUploadedFile([
            'name' => $origName,
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'size' => $sizeBytes,
            'error' => $files['error'][$i],
        ], $allowedExt, $maxUploadMB, $origName);
        if ($validationError !== null) {
            $errors[] = $validationError;
            continue;
        }
        if ($maxUploadMB > 0 && $sizeBytes > $maxUploadMB * 1024 * 1024) {
            $errors[] = $origName . ': Dosya çok büyük (maks. ' . $maxUploadMB . ' MB).';
            continue;
        }

        $safeName = preg_replace('/[^a-z0-9\-_\.]/i', '-', pathinfo($origName, PATHINFO_FILENAME));
        $safeName = preg_replace('/-+/', '-', trim($safeName, '-'));
        $fileName = $safeName . '-' . substr(uniqid(), -6) . '.' . $ext;
        $destPath = $destDir . DIRECTORY_SEPARATOR . $fileName;

        if (move_uploaded_file($files['tmp_name'][$i], $destPath)) {
            // Resim işleme pipeline (WebP dönüşümü, boyutlandırma, thumbnail vb.)
            $imageExts = mediaSafeImageExtensions();
            if (in_array($ext, $imageExts, true) && !empty($imageSettings)) {
                try {
                    $processResult = mediaProcessImage($destPath, $imageSettings);
                    $destPath = $processResult['path'];
                    $fileName = $processResult['name'];
                    $sizeBytes = $processResult['final_size'];
                } catch (Throwable $e) {
                    // İşleme başarısız olursa orijinal dosyayı kullan
                }
            }

            $uploadedCount++;
            try {
                $relPath = str_replace(
                    [$projectRoot . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
                    ['', '/'],
                    realpath($destPath) ?: $destPath
                );
                $mime = mediaDetectMimeType($destPath) ?: 'application/octet-stream';
                $stmt = $pdo->prepare("INSERT INTO media_files (topic_id, user_id, type, disk, path, original_name, mime_type, size, created_at, updated_at)
                                       VALUES (NULL, :uid, :type, 'local', :path, :orig, :mime, :size, NOW(), NOW())");
                $stmt->execute([
                    'uid' => $userId,
                    'type' => $mime,
                    'path' => $relPath,
                    'orig' => $origName,
                    'mime' => $mime,
                    'size' => $sizeBytes,
                ]);
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        } else {
            $errors[] = $origName . ': Dosya taşınamadı.';
        }
    }

    return ['uploaded' => $uploadedCount, 'errors' => $errors];
}

function mediaDeleteFile(PDO $pdo, string $uploadBase, string $filePath): bool
{
    $fullPath = mediaResolvePath($uploadBase, $filePath);

    if ($fullPath && is_file($fullPath)) {
        @unlink($fullPath);
        $projectRoot = mediaProjectRoot($uploadBase);
        try {
            $relUploadsPath = mediaRelativeToUploads($uploadBase, $fullPath);
            $relPath = str_replace([$projectRoot . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $fullPath);
            $pdo->prepare("DELETE FROM media_files WHERE path = :path OR path = :upload_path")->execute([
                'path' => $relPath,
                'upload_path' => 'uploads/' . ltrim($relUploadsPath, '/'),
            ]);
        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        return true;
    }

    return false;
}

function mediaScanDir(string $basePath, string $webBase, string $relPrefix = ''): array
{
    $items = [];
    if (!is_dir($basePath)) {
        return $items;
    }

    $entries = @scandir($basePath) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep' || $entry === '.htaccess' || $entry === 'index.php') {
            continue;
        }

        $fullPath = $basePath . DIRECTORY_SEPARATOR . $entry;
        $rel = mediaNormalizeRelativePath($relPrefix !== '' ? $relPrefix . '/' . $entry : $entry);

        if (is_dir($fullPath)) {
            $dirStats = mediaGetGlobalStats($fullPath, false);

            $items[] = [
                'type' => 'dir',
                'name' => $entry,
                'path' => $rel,
                'url' => $webBase . '/' . $rel,
                'count' => $dirStats['files'],
                'dir_count' => $dirStats['dirs'] ?? 0, // We added 'dirs' to global stats earlier
                'size' => $dirStats['size'],
                'images' => $dirStats['images'],
                'modified' => filemtime($fullPath) ?: time(),
            ];
            continue;
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        $isImage = in_array($ext, mediaSafeImageExtensions(), true);
        $items[] = [
            'type' => 'file',
            'name' => $entry,
            'path' => $rel,
            'url' => $webBase . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel),
            'size' => filesize($fullPath) ?: 0,
            'modified' => filemtime($fullPath) ?: time(),
            'ext' => $ext,
            'is_image' => $isImage,
        ];
    }

    usort($items, function ($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    return $items;
}

function mediaFormatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}

function mediaGetStats(array $items): array
{
    $stats = ['files' => 0, 'images' => 0, 'dirs' => 0, 'size' => 0];
    foreach ($items as $item) {
        if ($item['type'] === 'dir') { $stats['dirs']++; }
        else { $stats['files']++; $stats['size'] += $item['size'] ?? 0; if ($item['is_image'] ?? false) $stats['images']++; }
    }
    return $stats;
}

function mediaGetGlobalStats(string $basePath, bool $recursive = true): array
{
    $stats = ['files' => 0, 'images' => 0, 'dirs' => 0, 'size' => 0];
    if (!is_dir($basePath)) return $stats;

    $imageExts = mediaSafeImageExtensions();
    $stack = [$basePath];

    while (!empty($stack)) {
        $currentDir = array_pop($stack);
        $entries = @scandir($currentDir);
        
        if ($entries === false) {
            continue; // Klasör okunamıyorsa atla, diğerlerini işlemeye devam et
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $currentDir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($fullPath)) {
                $stats['dirs']++;
                if ($recursive) {
                    $stack[] = $fullPath; // Alt klasörü yığına ekle
                }
            } else {
                $stats['files']++;
                $stats['size'] += @filesize($fullPath) ?: 0;
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExts, true)) {
                    $stats['images']++;
                }
            }
        }
    }

    return $stats;
}

/**
 * Resim işleme pipeline: boyutlandırma + WebP dönüşümü + metadata temizleme + netleştirme + thumbnail + filigran
 * @return array{path: string, name: string, converted: bool, thumbnail: string|null, original_size: int, final_size: int}
 */
function mediaProcessImage(string $filePath, array $imageSettings): array
{
    $result = [
        'path' => $filePath,
        'name' => basename($filePath),
        'converted' => false,
        'thumbnail' => null,
        'original_size' => filesize($filePath),
        'final_size' => filesize($filePath),
    ];

    if (!extension_loaded('gd')) {
        return $result;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $imageExts = mediaSafeImageExtensions();
    if (!in_array($ext, $imageExts, true)) {
        return $result;
    }

    $image = mediaCreateImageFromFile($filePath, $ext);
    if (!$image) {
        return $result;
    }

    $origWidth = imagesx($image);
    $origHeight = imagesy($image);

    // 1) Boyutlandırma
    $autoResize = ($imageSettings['auto_resize_images'] ?? '0') === '1';
    $maxW = (int)($imageSettings['image_resize_width'] ?? 1920);
    $maxH = (int)($imageSettings['image_resize_height'] ?? 1080);
    if ($autoResize && ($origWidth > $maxW || $origHeight > $maxH)) {
        $image = mediaResizeImage($image, $origWidth, $origHeight, $maxW, $maxH);
    }

    // 2) EXIF/Metadata temizleme (strip = create clean copy)
    $stripMeta = ($imageSettings['image_strip_metadata'] ?? '1') === '1';

    // 3) Netleştirme
    $sharpen = ($imageSettings['image_sharpen'] ?? '1') === '1';
    if ($sharpen && ($autoResize && ($origWidth > $maxW || $origHeight > $maxH))) {
        mediaSharpenImage($image);
    }

    // 4) Filigran
    $watermarkEnabled = ($imageSettings['watermark_enabled'] ?? '0') === '1';
    $watermarkText = trim($imageSettings['watermark_text'] ?? '');
    if ($watermarkEnabled && $watermarkText !== '') {
        mediaApplyWatermark($image, $watermarkText, $imageSettings);
    }

    // 5) WebP dönüşümü
    $webpEnabled = ($imageSettings['webp_enabled'] ?? '1') === '1';
    $webpQuality = max(1, min(100, (int)($imageSettings['webp_quality'] ?? 82)));
    $keepOriginal = ($imageSettings['webp_keep_original'] ?? '0') === '1';
    $gdInfo = gd_info();
    $canWebp = !empty($gdInfo['WebP Support']);

    if ($webpEnabled && $canWebp && $ext !== 'webp' && $ext !== 'gif') {
        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $filePath);
        if (imagewebp($image, $webpPath, $webpQuality)) {
            $result['converted'] = true;
            $result['name'] = basename($webpPath);

            if (!$keepOriginal) {
                @unlink($filePath);
            }

            $result['path'] = $webpPath;
            $filePath = $webpPath;
        }
    } else {
        // Orijinal formatta kaydet (kalite ayarlı)
        mediaSaveImage($image, $filePath, $ext, $imageSettings);
    }

    $result['final_size'] = filesize($filePath);

    imagedestroy($image);

    // 6) Thumbnail (kayıtlı dosyadan oluştur)
    $thumbEnabled = ($imageSettings['thumbnail_enabled'] ?? '1') === '1';
    if ($thumbEnabled) {
        $thumbPath = mediaCreateThumbnail($filePath, $imageSettings);
        if ($thumbPath) {
            $result['thumbnail'] = $thumbPath;
        }
    }

    return $result;
}

function mediaCreateImageFromFile(string $path, string $ext): ?\GdImage
{
    return match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path) ?: null,
        'png' => @imagecreatefrompng($path) ?: null,
        'gif' => @imagecreatefromgif($path) ?: null,
        'bmp' => @imagecreatefrombmp($path) ?: null,
        'webp' => @imagecreatefromwebp($path) ?: null,
        default => null,
    };
}

function mediaResizeImage(\GdImage $image, int $origW, int $origH, int $maxW, int $maxH): \GdImage
{
    $ratio = min($maxW / $origW, $maxH / $origH);
    $newW = (int)round($origW * $ratio);
    $newH = (int)round($origH * $ratio);

    $resized = imagecreatetruecolor($newW, $newH);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($image);

    return $resized;
}

function mediaSharpenImage(\GdImage &$image): void
{
    $sharpen = [
        [-1, -1, -1],
        [-1, 16, -1],
        [-1, -1, -1],
    ];
    $divisor = array_sum(array_map('array_sum', $sharpen));
    if ($divisor > 0) {
        imageconvolution($image, $sharpen, $divisor, 0);
    }
}

function mediaApplyWatermark(\GdImage &$image, string $text, array $settings): void
{
    $position = $settings['watermark_position'] ?? 'bottom-right';
    $opacity = max(0, min(100, (int)($settings['watermark_opacity'] ?? 30)));
    $fontSize = max(8, min(72, (int)($settings['watermark_font_size'] ?? 16)));

    $imgW = imagesx($image);
    $imgH = imagesy($image);

    $alpha = (int)(127 - ($opacity / 100 * 127));
    $color = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
    $shadowAlpha = min(127, $alpha + 20);
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, $shadowAlpha);

    $gdFontSize = max(1, min(5, (int)($fontSize / 6)));
    $fontW = imagefontwidth($gdFontSize);
    $fontH = imagefontheight($gdFontSize);
    $textW = $fontW * strlen($text);
    $textH = $fontH;

    $padding = 12;
    switch ($position) {
        case 'top-left':
            $x = $padding; $y = $padding + $textH; break;
        case 'top-right':
            $x = $imgW - $textW - $padding; $y = $padding + $textH; break;
        case 'bottom-left':
            $x = $padding; $y = $imgH - $padding; break;
        case 'center':
            $x = ($imgW - $textW) / 2; $y = ($imgH + $textH) / 2; break;
        case 'bottom-right':
        default:
            $x = $imgW - $textW - $padding; $y = $imgH - $padding; break;
    }

    imagestring($image, $gdFontSize, (int)$x + 1, (int)$y - $textH + 1, $text, $shadow);
    imagestring($image, $gdFontSize, (int)$x, (int)$y - $textH, $text, $color);
}

function mediaSaveImage(\GdImage $image, string $path, string $ext, array $settings): void
{
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $quality = max(1, min(100, (int)($settings['jpeg_quality'] ?? 85)));
            imagejpeg($image, $path, $quality);
            break;
        case 'png':
            $compression = max(0, min(9, (int)($settings['png_compression'] ?? 6)));
            imagepng($image, $path, $compression);
            break;
        case 'gif':
            imagegif($image, $path);
            break;
        case 'webp':
            $quality = max(1, min(100, (int)($settings['webp_quality'] ?? 82)));
            imagewebp($image, $path, $quality);
            break;
        case 'bmp':
            imagebmp($image, $path);
            break;
    }
}

function mediaCreateThumbnail(string $sourcePath, array $settings): ?string
{
    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $image = mediaCreateImageFromFile($sourcePath, $ext);
    if (!$image) return null;

    $origW = imagesx($image);
    $origH = imagesy($image);
    $thumbW = max(50, (int)($settings['thumbnail_width'] ?? 400));
    $thumbH = max(50, (int)($settings['thumbnail_height'] ?? 300));
    $crop = ($settings['thumbnail_crop'] ?? '1') === '1';

    if ($crop) {
        $ratio = max($thumbW / $origW, $thumbH / $origH);
        $srcW = (int)round($thumbW / $ratio);
        $srcH = (int)round($thumbH / $ratio);
        $srcX = (int)round(($origW - $srcW) / 2);
        $srcY = (int)round(($origH - $srcH) / 2);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $thumbW, $thumbH, $srcW, $srcH);
    } else {
        $ratio = min($thumbW / $origW, $thumbH / $origH);
        $newW = (int)round($origW * $ratio);
        $newH = (int)round($origH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    }

    $dir = dirname($sourcePath);
    $name = pathinfo($sourcePath, PATHINFO_FILENAME);
    $thumbDir = $dir . DIRECTORY_SEPARATOR . 'thumbnails';
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);

    $webpEnabled = ($settings['webp_enabled'] ?? '1') === '1';
    $gdInfo = gd_info();
    $canWebp = !empty($gdInfo['WebP Support']);

    if ($webpEnabled && $canWebp) {
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $name . '.webp';
        $quality = max(1, min(100, (int)($settings['webp_quality'] ?? 82)));
        imagewebp($thumb, $thumbPath, $quality);
    } else {
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $name . '.' . $ext;
        mediaSaveImage($thumb, $thumbPath, $ext, $settings);
    }

    imagedestroy($image);
    imagedestroy($thumb);

    return $thumbPath;
}
