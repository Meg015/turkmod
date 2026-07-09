<?php

declare(strict_types=1);
/**
 * Ileri duzey loglama sistemi (PSR-3 uyumlu yuzey).
 *
 * Tum log kayitlari, prosedurel `appFileLog()` yardimcisi ile ayni JSON-satir
 * bicimini kullanir (bkz. includes/lib/logger.php). Boylece hem bu sinif hem
 * `appFileLog()` / `appLogException()` hem de `App\Core\Support\Logger` ayni
 * `storage/logs/app-YYYY-MM-DD.log` dosyasina tutarli bicimde yazar.
 *
 * Kanal ozel loglari (`security()`, `access()`, `performance()`) yine ayri
 * dosyalara yazilir ama ayni JSON-satir serilestirmesini kullanir.
 *
 * Not: Yeni OOP kodu icin tercih `App\Core\Support\Logger` veya prosedurel
 * `appFileLog()/appLogException()` yardimcilari olmali; bu sinif mevcut cagri
 * yerleri (`Logger::getInstance()->...`) icin geriye donuk uyumlu facade olarak
 * tutulmaktadir.
 */

class Logger {
    private static $instance = null;
    private $logPath;
    private $logLevel;

    const EMERGENCY = 'EMERGENCY';
    const ALERT = 'ALERT';
    const CRITICAL = 'CRITICAL';
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const NOTICE = 'NOTICE';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    private $levels = [
        'EMERGENCY' => 0,
        'ALERT' => 1,
        'CRITICAL' => 2,
        'ERROR' => 3,
        'WARNING' => 4,
        'NOTICE' => 5,
        'INFO' => 6,
        'DEBUG' => 7,
    ];

    private function __construct() {
        $this->logPath = __DIR__ . '/../storage/logs/';
        $envLevel = strtoupper(trim((string) ($_ENV['LOG_LEVEL'] ?? $_ENV['APP_LOG_LEVEL'] ?? 'INFO')));
        $this->logLevel = array_key_exists($envLevel, $this->levels) ? $envLevel : 'INFO';

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * JSON-satir biciminde log kaydi yazar.
     */
    private function log($level, $message, $context = []) {
        if ($this->levels[$level] > $this->levels[$this->logLevel]) {
            return;
        }

        $message = $this->interpolate($message, $context);
        $entry = $this->buildEntry(strtolower($level), $message, $context);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $logFile = $this->logPath . 'app-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        if (in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'], true)) {
            $errorLog = $this->logPath . 'error-' . date('Y-m-d') . '.log';
            file_put_contents($errorLog, $line, FILE_APPEND | LOCK_EX);
        }

        $this->cleanOldLogs();
    }

    /**
     * `appFileLog()` ile uyumlu JSON-satir serilestirmesi yazar.
     */
    private function buildEntry(string $level, string $message, array $context): array {
        return [
            'ts' => date('c'),
            'level' => $level,
            'msg' => $message,
            'ctx' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        ];
    }

    private function interpolate($message, array $context = []) {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    private function cleanOldLogs() {
        $cleanFile = $this->logPath . '.last_clean';
        if (file_exists($cleanFile) && (time() - filemtime($cleanFile)) < 86400) {
            return;
        }

        $files = glob($this->logPath . '*.log') ?: [];
        $cutoff = time() - (30 * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (is_file($file)) {
                unlink($file);
            }
            }
        }

        touch($cleanFile);
    }

    private function writeChannel(string $channel, string $message, array $context = []): void {
        $entry = $this->buildEntry($channel, $message, $context);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $logFile = $this->logPath . $channel . '-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    // PSR-3 metodlarý
    public function emergency($message, array $context = []) {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []) {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []) {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = []) {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = []) {
        $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Özel log tipleri
     */
    public function security($message, array $context = []) {
        $this->writeChannel('security', $message, $context);
    }

    public function access($message, array $context = []) {
        $this->writeChannel('access', $message, $context);
    }

    public function performance($message, $duration, array $context = []) {
        $context['__duration'] = $duration;
        $this->writeChannel('performance', $message, $context);
    }
}

// Global helper fonksiyonlar
function logger() {
    return Logger::getInstance();
}

function log_info($message, $context = []) {
    Logger::getInstance()->info($message, $context);
}

function log_error($message, $context = []) {
    Logger::getInstance()->error($message, $context);
}

function log_security($message, $context = []) {
    Logger::getInstance()->security($message, $context);
}
