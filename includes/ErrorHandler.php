<?php

declare(strict_types=1);
/**
 * Gelişmiş Error Handler
 * Tüm hataları yakalar, loglar ve güvenli mesajlar gösterir
 */

class ErrorHandler {
    private static $instance = null;
    private $logPath;
    private $isProduction;

    private function __construct() {
        $this->logPath = __DIR__ . '/../storage/logs/';
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0775, true);
        }

        $envValue = (string) ($_ENV['APP_ENV'] ?? '');
        if ($envValue === '' && class_exists(\App\Core\Database::class) && method_exists(\App\Core\Database::class, 'getEnvConfig')) {
            $env = \App\Core\Database::getEnvConfig();
            $envValue = (string) ($env['APP_ENV'] ?? '');
        }

        $this->isProduction = strtolower($envValue !== '' ? $envValue : 'production') === 'production';

        // Error handler'ı kur
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * PHP hatalarını yakala
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        // Error reporting seviyesine göre kontrol et
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = $this->getErrorType($errno);

        // Log'a yaz
        $this->log($errorType, $errstr, $errfile, $errline);

        // Production'da kullanıcıya güvenli mesaj göster
        if ($this->isProduction && $this->isFatalError($errno)) {
            $this->showErrorPage();
            exit;
        }

        return true;
    }

    /**
     * Exception'ları yakala
     */
    public function handleException($exception) {
        $this->log(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Production'da güvenli mesaj
        if ($this->isProduction) {
            $this->showErrorPage();
        } else {
            // Development'ta detaylı hata
            echo '<link rel="stylesheet" href="' . htmlspecialchars($this->fallbackCssHref(), ENT_QUOTES, "UTF-8") . '">';
            echo '<div class="dev-error-box">';
            echo "<h2>🚨 Exception Caught</h2>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . ":" . $exception->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            echo "</div>";
        }

        exit;
    }

    /**
     * Fatal error'ları yakala
     */
    public function handleShutdown() {
        $error = error_get_last();

        if ($error !== null && $this->isFatalError($error['type'])) {
            $this->log(
                'FATAL',
                $error['message'],
                $error['file'],
                $error['line']
            );

            if ($this->isProduction) {
                $this->showErrorPage();
            }
        }
    }

    /**
     * Hata tipini belirle
     */
    private function getErrorType($errno) {
        $types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];

        return $types[$errno] ?? 'UNKNOWN';
    }

    /**
     * Fatal error kontrolü
     */
    private function isFatalError($errno) {
        $fatalErrors = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR
        ];

        return in_array($errno, $fatalErrors);
    }

    private function isCriticalLogType(string $type): bool
    {
        return in_array($type, ['ERROR', 'PARSE', 'CORE_ERROR', 'COMPILE_ERROR', 'USER_ERROR', 'FATAL', 'EXCEPTION'], true);
    }

    /**
     * Hatayı log'a yaz
     */
    private function fallbackCssHref(): string {
        $baseUri = (string)($GLOBALS['baseUri'] ?? '');
        if ($baseUri === '') {
            $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
            $baseUri = ($scriptDir === '/' || $scriptDir === '.') ? '' : $scriptDir;
            if (str_ends_with($baseUri, '/admin')) {
                $baseUri = substr($baseUri, 0, -6);
            }
        }

        $cssPath = __DIR__ . '/../assets/css/system-fallback.css';
        $version = is_file($cssPath) ? (string) filemtime($cssPath) : '1';

        return rtrim($baseUri, '/') . '/assets/css/system-fallback.css?v=' . $version;
    }

    private function log($type, $message, $file, $line, $trace = null) {
        $date = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $url = $_SERVER['REQUEST_URI'] ?? 'N/A';

        $logMessage = sprintf(
            "[%s] [%s] %s in %s:%d | IP: %s | URL: %s\n",
            $date,
            $type,
            $message,
            $file,
            $line,
            $ip,
            $url
        );

        if ($trace) {
            $logMessage .= "Stack Trace:\n" . $trace . "\n";
        }

        $logMessage .= str_repeat('-', 80) . "\n";

        // Log dosyasına yaz
        $logFile = $this->logPath . 'error-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Kritik hatalar için ayrı log
        if ($this->isCriticalLogType((string) $type)) {
            $criticalLog = $this->logPath . 'critical-' . date('Y-m-d') . '.log';
            file_put_contents($criticalLog, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Kullanıcıya güvenli hata sayfası göster
     */
    private function showErrorPage() {
        http_response_code(500);

        if (file_exists(__DIR__ . '/public-500.php')) {
            include __DIR__ . '/public-500.php';
        } else {
            echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bir Hata Oluştu</title>
    <link rel="stylesheet" href="' . htmlspecialchars($this->fallbackCssHref(), ENT_QUOTES, "UTF-8") . '">
</head>
<body class="error-fallback-page">
    <div class="error-container">
        <h1>🚨 Bir Hata Oluştu</h1>
        <p>Üzgünüz, bir şeyler yanlış gitti. Lütfen daha sonra tekrar deneyin.</p>
        <p>Sorun devam ederse, lütfen site yöneticisiyle iletişime geçin.</p>
        <p><a href="/">Ana Sayfaya Dön</a></p>
    </div>
</body>
</html>';
        }
    }

    /**
     * Manuel log fonksiyonu
     */
    public static function logMessage($message, $type = 'INFO') {
        $instance = self::getInstance();
        $instance->log($type, $message, __FILE__, __LINE__);
    }
}

// Error handler'ı başlat
ErrorHandler::getInstance();
