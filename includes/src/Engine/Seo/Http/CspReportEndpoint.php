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
            $this->logDir = dirname(__DIR__, 4) . '/storage/logs';
        }
    }

    public function handle(Request $request): Response
    {
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT'], true)) {
            return new Response('', 405);
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        $body = (string) $request->getBody();

        if ($body === '') {
            return new Response('', 400);
        }

        if (str_contains($contentType, 'application/csp-report')) {
            $report = $this->parseCspReport($body);
        } else {
            $decoded = json_decode($body, true);
            $report = is_array($decoded) ? $decoded : ['raw' => $body];
        }

        $this->logReport($report);

        return new Response('', 204);
    }

    private function parseCspReport(string $body): array
    {
        $data = [];
        parse_str($body, $data);

        return is_array($data) ? $data : ['raw' => $body];
    }

    private function logReport(array $report): void
    {
        $logDir = rtrim($this->logDir, "/\\");
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/csp-reports.log';
        $entry = date('Y-m-d H:i:s') . ' ' . json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
