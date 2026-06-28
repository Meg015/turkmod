<?php

declare(strict_types=1);

namespace App\Core\Support;

use RuntimeException;
use Stringable;

final class Logger
{
    public function __construct(
        private string $logFile = '',
        private string $channel = 'app',
    ) {
        if ($this->logFile === '') {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'turkmod.log';
        }

        $directory = dirname($this->logFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Log directory could not be created: ' . $directory);
        }
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, Stringable|string $message, array $context = []): void
    {
        $line = sprintf(
            '[%s] [%s] [%s] %s%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            $this->channel,
            strtolower($level),
            $this->interpolate((string) $message, $context),
            $context === [] ? '' : ' ' . $this->stringifyContext($context),
        );

        $written = file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log($line);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = $this->stringifyValue($value);
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function stringifyContext(array $context): string
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[$key] = $this->stringifyValue($value);
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[unserializable]';
    }
}
