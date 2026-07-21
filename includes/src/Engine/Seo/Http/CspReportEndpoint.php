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
        if (!$this->shouldLogReport($report)) {
            return new Response('', 204);
        }

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

    private function shouldLogReport(array $report): bool
    {
        $items = $this->reportItems($report);
        if ($items === []) {
            return true;
        }

        foreach ($items as $item) {
            if (!$this->isIgnorableReportItem($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportItems(array $report): array
    {
        if (array_is_list($report)) {
            return array_values(array_filter($report, 'is_array'));
        }

        if (isset($report['csp-report']) && is_array($report['csp-report'])) {
            return [$report['csp-report']];
        }

        if (isset($report['report']) && is_array($report['report'])) {
            return array_is_list($report['report'])
                ? array_values(array_filter($report['report'], 'is_array'))
                : [$report['report']];
        }

        return [$report];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isIgnorableReportItem(array $item): bool
    {
        $body = isset($item['body']) && is_array($item['body']) ? $item['body'] : $item;
        $directive = strtolower((string) ($body['effectiveDirective'] ?? $body['effective-directive'] ?? ''));
        $blocked = strtolower((string) ($body['blockedURL'] ?? $body['blocked-uri'] ?? ''));
        $disposition = strtolower((string) ($body['disposition'] ?? ''));
        $source = strtolower((string) ($body['sourceFile'] ?? $body['source-file'] ?? ''));
        $document = strtolower((string) ($body['documentURL'] ?? $body['document-uri'] ?? ($item['url'] ?? '')));
        $userAgent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $combined = $blocked . ' ' . $source . ' ' . $document . ' ' . $userAgent;

        foreach ([
            'chrome-extension',
            'edge-extension',
            'extension://',
            'moz-extension',
            'safari-extension',
            'kaspersky-labs.com',
        ] as $needle) {
            if (str_contains($combined, $needle)) {
                return true;
            }
        }

        return $disposition === 'report'
            && $directive === 'style-src-attr'
            && $blocked === 'inline';
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
