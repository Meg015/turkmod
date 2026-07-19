<?php

declare(strict_types=1);

namespace App\Engine\Seo\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;

final class CspReportEndpoint implements Handler
{
    public function __construct(
        private string $logDir = '',
    ) {
        if ($this->logDir === '') {
            $this->logDir = dirname(__DIR__, 5) . '/storage/logs';
        }
    }

    public function handle(Request $request): Response
    {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT'], true)) {
            return new Response('', 405, ['Allow' => 'POST, PUT']);
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        $body = (string) $request->getBody();

        if ($body === '') {
            return new Response('', 400);
        }
        if (strlen($body) > 65536) {
            return new Response('', 413);
        }

        $report = $this->parseReportBody($body, $contentType);

        $this->logReport($report);

        return new Response('', 204);
    }

    private function parseReportBody(string $body, string $contentType): array
    {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $data = [];
            parse_str($body, $data);

            if (is_array($data) && $data !== []) {
                return $data;
            }
        }

        return ['raw' => substr($body, 0, 8192)];
    }

    private function logReport(array $report): void
    {
        $logDir = rtrim($this->logDir, "/\\");
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/csp-reports.log';
        $entry = date('Y-m-d H:i:s') . ' ' . json_encode([
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'report' => $report,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
