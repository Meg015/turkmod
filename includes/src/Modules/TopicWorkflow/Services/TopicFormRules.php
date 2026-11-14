<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Services;

use InvalidArgumentException;

final class TopicFormRules
{
    public static function downloadLines(array $input): string
    {
        if (array_key_exists('dl_url', $input)) {
            if (!is_array($input['dl_url']) || isset($input['dl_name']) && !is_array($input['dl_name'])) {
                throw new InvalidArgumentException('İndirme bağlantıları geçerli değil.');
            }
            $lines = [];
            foreach ($input['dl_url'] as $index => $url) {
                $name = $input['dl_name'][$index] ?? '';
                if (!is_scalar($url) || !is_scalar($name)) {
                    throw new InvalidArgumentException('İndirme bağlantıları geçerli değil.');
                }
                $url = trim((string) $url);
                if ($url === '') continue;
                $name = trim(preg_replace('/[|\r\n]+/u', ' ', (string) $name) ?? '');
                $lines[] = ($name !== '' ? $name : 'Link') . '|' . $url;
            }
            return implode("\n", $lines);
        }

        $raw = $input['download_lines'] ?? $input['topic_download_links'] ?? '';
        if (!is_scalar($raw)) throw new InvalidArgumentException('İndirme bağlantıları geçerli değil.');
        return implode("\n", array_filter(array_map('trim', preg_split('/\R/', (string) $raw) ?: []), static fn(string $line): bool => $line !== ''));
    }

    public static function downloadError(string $lines, bool $required): ?string
    {
        $valid = 0;
        $invalid = false;
        foreach (preg_split('/\R/', trim($lines)) ?: [] as $line) {
            if (trim($line) === '') continue;
            $parts = explode('|', $line, 2);
            if (self::isWebUrl(trim($parts[1] ?? $parts[0]))) $valid++;
            else $invalid = true;
        }
        if ($required && $valid === 0) return 'En az bir geçerli indirme bağlantısı eklemelisiniz.';
        return $invalid ? 'İndirme bağlantıları http:// veya https:// ile başlayan geçerli adresler olmalı.' : null;
    }

    public static function videoAllowed(string $url, array $allowedHosts): bool
    {
        if ($url === '') return true;
        if (!self::isWebUrl($url)) return false;
        if ($allowedHosts === []) return true;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.' . $allowed))) return true;
        }
        return false;
    }

    public static function contentText(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $text) ?? trim($text);
    }

    public static function imageError(array $file, string $label, array $settings, int $maxSizeMb): ?string
    {
        if (self::isEmptyFile($file)) return null;
        $error = self::fileError($file, $label, $maxSizeMb);
        if ($error !== null) return $error;
        $allowed = array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($settings['user_upload_allowed_image_ext'] ?? 'jpg,jpeg,png,webp'))))));
        $allowed = $allowed ?: ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) return $label . ' için izinli uzantılar: ' . implode(', ', $allowed) . '.';
        $path = (string) ($file['tmp_name'] ?? '');
        $info = $path !== '' && is_file($path) ? @getimagesize($path) : false;
        if (!$info) return $label . ' geçerli bir görsel değil.';
        foreach ([['min_width', $info[0], true], ['min_height', $info[1], true], ['max_width', $info[0], false], ['max_height', $info[1], false]] as [$key, $value, $minimum]) {
            $limit = max(0, (int) ($settings['user_upload_image_' . $key] ?? 0));
            if ($limit > 0 && ($minimum ? $value < $limit : $value > $limit)) {
                $dimension = str_ends_with($key, 'width') ? 'genişliği' : 'yüksekliği';
                return $label . ' ' . $dimension . ' ' . ($minimum ? 'minimum ' : 'maksimum ') . $limit . ' px olmalıdır.';
            }
        }
        return null;
    }

    public static function attachmentError(array $file, int $maxSizeMb): ?string
    {
        return self::isEmptyFile($file) ? null : self::fileError($file, 'Mod dosyası', $maxSizeMb);
    }

    public static function galleryFiles(array $file): array
    {
        $normalized = array_fill_keys(['name', 'type', 'tmp_name', 'error', 'size'], []);
        $names = $file['name'] ?? [];
        if (!is_array($names)) throw new InvalidArgumentException('Galeri dosyaları geçerli değil.');
        foreach ($names as $index => $name) {
            $error = $file['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if (!is_scalar($name) || !is_scalar($error)) throw new InvalidArgumentException('Galeri dosyaları geçerli değil.');
            if (trim((string) $name) === '' && (int) $error === UPLOAD_ERR_NO_FILE) continue;
            foreach ($normalized as $key => $_) {
                $value = $file[$key][$index] ?? ($key === 'error' ? UPLOAD_ERR_NO_FILE : ($key === 'size' ? 0 : ''));
                if (!is_scalar($value)) throw new InvalidArgumentException('Galeri dosyaları geçerli değil.');
                $normalized[$key][] = $value;
            }
        }
        return $normalized;
    }

    private static function isWebUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private static function isEmptyFile(array $file): bool
    {
        return ($file['name'] ?? '') === '' && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
    }

    private static function fileError(array $file, string $label, int $maxSizeMb): ?string
    {
        foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
            if (isset($file[$key]) && !is_scalar($file[$key])) return $label . ' dosyası geçerli değil.';
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) return $label . ' sunucunun dosya boyutu sınırını aşıyor.';
        if ($error !== UPLOAD_ERR_OK) return $label . ' yüklenemedi. Dosyayı yeniden seçip tekrar deneyin.';
        if ((int) ($file['size'] ?? 0) > max(1, $maxSizeMb) * 1024 * 1024) return $label . ' boyutu en fazla ' . max(1, $maxSizeMb) . ' MB olabilir.';
        return null;
    }
}
