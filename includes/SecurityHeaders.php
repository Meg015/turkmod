<?php

declare(strict_types=1);
/**
 * Security Headers Manager
 * Modern güvenlik başlıklarını yönetir
 */

class SecurityHeaders {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->config = [
            'csp' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://cdn.quilljs.com"],
                'style-src' => ["'self'", "'unsafe-inline'", "https://cdn.jsdelivr.net", "https://cdn.quilljs.com"],
                'style-src-attr' => ["'unsafe-inline'"],
                'img-src' => ["'self'", "data:", "https:"],
                'font-src' => ["'self'", "data:", "https://cdn.jsdelivr.net"],
                'connect-src' => ["'self'", "https://cdn.jsdelivr.net", "https://cdn.quilljs.com", "https:"],
                'frame-ancestors' => ["'self'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'object-src' => ["'none'"],
                'media-src' => ["'self'", "https:"],
                'frame-src' => ["'self'", "https://www.youtube.com", "https://www.youtube-nocookie.com", "https://player.vimeo.com"],
            ],
            'hsts' => [
                'max-age' => 31536000,
                'includeSubDomains' => true,
                'preload' => true
            ],
            'permissions_policy' => [
                'geolocation' => [],
                'microphone' => [],
                'camera' => [],
                'payment' => [],
                'usb' => [],
                'magnetometer' => [],
                'gyroscope' => [],
                'accelerometer' => []
            ]
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Tüm güvenlik başlıklarını ayarla
     */
    public function setHeaders() {
        // Zaten gönderilmişse tekrar gönderme
        if (headers_sent()) {
            return;
        }

        if (function_exists('buildSecurityHeaders')) {
            $envConfig = class_exists(\App\Core\Database::class)
                ? \App\Core\Database::getEnvConfig()
                : [];
            $appDebug = $this->envBool($envConfig, 'APP_DEBUG', false);
            $forceHttps = $this->envBool($envConfig, 'APP_FORCE_HTTPS', false);

            foreach (buildSecurityHeaders($appDebug, $forceHttps, $envConfig) as $headerValue) {
                header((string) $headerValue);
            }

            return;
        }
        
        // Content Security Policy
        $this->setCSP();
        
        // HTTP Strict Transport Security
        $this->setHSTS();
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        $this->setPermissionsPolicy();
        
        // Cross-Origin Policies - Strict security
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        
        // Remove server signature
        header_remove('X-Powered-By');
        header('Server: WebServer');
    }
    
    /**
     * Content Security Policy ayarla
     */
    private function setCSP() {
        $cspParts = [];
        
        foreach ($this->config['csp'] as $directive => $sources) {
            if (!empty($sources)) {
                $cspParts[] = $directive . ' ' . implode(' ', $sources);
            }
        }
        
        $csp = implode('; ', $cspParts);
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * HSTS ayarla
     */
    private function setHSTS() {
        // Sadece HTTPS bağlantılarda HSTS gönder
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $hsts = 'max-age=' . $this->config['hsts']['max-age'];
            
            if ($this->config['hsts']['includeSubDomains']) {
                $hsts .= '; includeSubDomains';
            }
            
            if ($this->config['hsts']['preload']) {
                $hsts .= '; preload';
            }
            
            header("Strict-Transport-Security: $hsts");
        }
    }
    
    /**
     * Permissions Policy ayarla
     */
    private function setPermissionsPolicy() {
        $policies = [];
        
        foreach ($this->config['permissions_policy'] as $feature => $allowlist) {
            if (empty($allowlist)) {
                $policies[] = "$feature=()";
            } else {
                $policies[] = "$feature=(" . implode(' ', $allowlist) . ")";
            }
        }
        
        if (!empty($policies)) {
            header('Permissions-Policy: ' . implode(', ', $policies));
        }
    }
    
    /**
     * CSP'ye yeni kaynak ekle
     */
    public function addCSPSource($directive, $source) {
        if (isset($this->config['csp'][$directive])) {
            if (!in_array($source, $this->config['csp'][$directive])) {
                $this->config['csp'][$directive][] = $source;
            }
        }
    }
    
    /**
     * CORS başlıklarını ayarla - Whitelist ile
     */
    public function setCORS($allowedOrigins = [], $allowedMethods = ['GET', 'POST'], $allowedHeaders = ['Content-Type']) {
        if (headers_sent() || empty($allowedOrigins)) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
            header('Access-Control-Max-Age: 86400');
            header('Access-Control-Allow-Credentials: true');
        }
    }
    
    /**
     * API için güvenlik başlıkları
     */
    public function setAPIHeaders() {
        if (headers_sent()) {
            return;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    private function envBool(array $envConfig, string $key, bool $default = false): bool
    {
        if (function_exists('appEnvBool')) {
            return appEnvBool($envConfig, $key, $default);
        }

        if (!array_key_exists($key, $envConfig)) {
            return $default;
        }

        return in_array(strtolower(trim((string) $envConfig[$key])), ['1', 'true', 'yes', 'on'], true);
    }
}

// Otomatik başlatma (API istekleri hariç)
if (!defined('SECURITY_HEADERS_MANUAL') && !defined('API_REQUEST')) {
    SecurityHeaders::getInstance()->setHeaders();
}
