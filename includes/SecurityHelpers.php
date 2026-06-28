<?php

declare(strict_types=1);
/**
 * Security Helper Functions
 * XSS Protection, Input Validation, and Sanitization
 */

if (!function_exists("sanitizeHtml")) {
    /**
     * Sanitize HTML content using HTMLPurifier
     * Protects against XSS attacks while allowing safe HTML
     */
    function sanitizeHtml(string $html): string
    {
        static $purifier = null;

        if ($purifier === null) {
            // HTMLPurifier kurulu değilse basit sanitizasyon
            if (!class_exists("HTMLPurifier")) {
                // Güvenli HTML tagları
                $allowed =
                    "<p><br><strong><em><u><a><img><ul><ol><li><h2><h3><h4><blockquote><code><pre>";
                $html = strip_tags($html, $allowed);

                // Tehlikeli attribute'ları temizle
                $html = preg_replace(
                    "/<script\b[^>]*>(.*?)<\/script>/is",
                    "",
                    $html,
                );
                $html = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', "", $html);
                $html = preg_replace("/javascript:/i", "", $html);

                return $html;
            }

            $configClass = "HTMLPurifier_Config";
            $purifierClass = "HTMLPurifier";
            $autoLoader =
                __DIR__ .
                "/../vendor/htmlpurifier/library/HTMLPurifier.auto.php";
            if (!class_exists($configClass) && file_exists($autoLoader)) {
                require_once $autoLoader;
            }
            if (!class_exists($configClass) || !class_exists($purifierClass)) {
                return $html;
            }

            $config = $configClass::createDefault();
            $config->set(
                "HTML.Allowed",
                "p,br,strong,em,u,a[href|title|target],img[src|alt|width|height],ul,ol,li,h2,h3,h4,blockquote,code,pre",
            );
            $config->set(
                "HTML.AllowedAttributes",
                "a.href,a.title,a.target,img.src,img.alt,img.width,img.height",
            );
            $config->set("AutoFormat.RemoveEmpty", true);
            $config->set("AutoFormat.AutoParagraph", true);
            $config->set("HTML.TargetBlank", true);
            $config->set("URI.AllowedSchemes", [
                "http" => true,
                "https" => true,
                "mailto" => true,
            ]);

            $purifier = new $purifierClass($config);
        }

        return $purifier->purify($html);
    }
}

if (!function_exists("validateSlug")) {
    /**
     * Validate and sanitize URL slug
     * Prevents SQL injection and path traversal
     */
    function validateSlug(?string $slug): ?string
    {
        if ($slug === null || $slug === "") {
            return null;
        }

        // Sadece küçük harf, rakam ve tire
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        // Maksimum uzunluk kontrolü
        if (strlen($slug) > 200) {
            return null;
        }

        return $slug;
    }
}

if (!function_exists("validateEmail")) {
    /**
     * Validate email address
     */
    function validateEmail(?string $email): ?string
    {
        if ($email === null || $email === "") {
            return null;
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}

if (!function_exists("validateUsername")) {
    /**
     * Validate username
     * Alphanumeric, underscore, hyphen only
     */
    function validateUsername(?string $username): ?string
    {
        if ($username === null || $username === "") {
            return null;
        }

        // 3-30 karakter, alfanumerik + _ ve -
        if (!preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $username)) {
            return null;
        }

        return $username;
    }
}

if (!function_exists("validateInteger")) {
    /**
     * Validate and sanitize integer input
     */
    function validateInteger($value, int $min = null, int $max = null): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false) {
            return null;
        }

        if ($min !== null && $value < $min) {
            return null;
        }

        if ($max !== null && $value > $max) {
            return null;
        }

        return $value;
    }
}

if (!function_exists("validateUrl")) {
    /**
     * Validate URL
     */
    function validateUrl(?string $url): ?string
    {
        if ($url === null || $url === "") {
            return null;
        }

        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Sadece http ve https protokollerine izin ver
        $parsed = parse_url($url);
        if (
            !isset($parsed["scheme"]) ||
            !in_array($parsed["scheme"], ["http", "https"])
        ) {
            return null;
        }

        return $url;
    }
}

if (!function_exists("sanitizeFilename")) {
    /**
     * Sanitize filename to prevent directory traversal
     */
    function sanitizeFilename(string $filename): string
    {
        // Path traversal karakterlerini temizle
        $filename = str_replace(["..", "/", "\\", "\0"], "", $filename);

        // Sadece güvenli karakterler
        $filename = preg_replace("/[^a-zA-Z0-9._-]/", "_", $filename);

        // Maksimum uzunluk
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 250);
            $filename = $name . "." . $ext;
        }

        return $filename;
    }
}

if (!function_exists("preventXSS")) {
    /**
     * Quick XSS prevention for output
     * Use this for simple text output
     */
    function preventXSS(?string $value): string
    {
        if ($value === null) {
            return "";
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, "UTF-8");
    }
}

if (!function_exists("sanitizeSearchQuery")) {
    /**
     * Sanitize search query input
     */
    function sanitizeSearchQuery(?string $query): string
    {
        if ($query === null || $query === "") {
            return "";
        }

        // Tehlikeli karakterleri temizle
        $query = strip_tags($query);
        $query = preg_replace('/[<>"\']/', "", $query);

        // Maksimum uzunluk
        if (strlen($query) > 200) {
            $query = substr($query, 0, 200);
        }

        return trim($query);
    }
}

if (!function_exists("generateSecureToken")) {
    /**
     * Generate cryptographically secure random token
     */
    function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists("hashPassword")) {
    /**
     * Hash password securely using bcrypt
     */
    function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    }
}

if (!function_exists("verifyPassword")) {
    /**
     * Verify password against hash
     */
    function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists("isValidCategoryId")) {
    /**
     * Validate category ID exists in database
     */
    function isValidCategoryId(PDO $pdo, int $categoryId): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn() > 0;
    }
}

if (!function_exists("isValidTopicId")) {
    /**
     * Validate topic ID exists in database
     */
    function isValidTopicId(PDO $pdo, int $topicId): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE id = ?");
        $stmt->execute([$topicId]);
        return $stmt->fetchColumn() > 0;
    }
}

if (!function_exists("preventSQLInjection")) {
    /**
     * Helper to remind developers to use prepared statements
     * This function doesn't actually prevent SQL injection - use prepared statements!
     */
    function preventSQLInjection(): void
    {
        // Bu fonksiyon sadece hatırlatma amaçlıdır
        // GERÇEK KORUMA: Her zaman prepared statements kullanın!
        // YANLIŞ: $pdo->query("SELECT * FROM users WHERE id = $id")
        // DOĞRU: $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$id]);
    }
}

if (!function_exists("getRealIp")) {
    function isTrustedProxyAddress(string $ip): bool
    {
        $trusted = array_filter(array_map('trim', explode(',', (string)($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: ''))));
        return $ip !== '' && in_array($ip, $trusted, true);
    }

    /**
     * Get the real IP address of the client, handling Cloudflare and common proxies safely.
     * Prevents spoofing by falling back to REMOTE_ADDR if headers are missing or invalid.
     */
    function getRealIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!isTrustedProxyAddress($ip)) {
            return $ip;
        }

        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Forwarded For
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $firstIp = trim($ips[0]);
            if (filter_var($firstIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $firstIp;
            }
        }
        
        // Real IP
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $ip;
    }
}
