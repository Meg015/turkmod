<?php

declare(strict_types=1);

namespace App\Core\Routing\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware;
use PDO;

final class CsrfGuard implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next->handle($request);
        }

        if (!function_exists('verify_csrf_token')) {
            trigger_error('CSRF guard bypassed: verify_csrf_token() not loaded', E_USER_WARNING);
            return $next->handle($request);
        }

        $token = $this->resolveToken($request);
        if ($token !== '' && verify_csrf_token($token)) {
            return $next->handle($request);
        }

        global $pdo;
        if (($pdo ?? null) instanceof PDO && function_exists('logCsrfFailure')) {
            logCsrfFailure($pdo, $request->getPath());
        }

        return $this->failureResponse($request);
    }

    private function resolveToken(Request $request): string
    {
        foreach (['X-CSRF-Token', 'X-XSRF-Token'] as $headerName) {
            $header = trim((string) $request->header($headerName, ''));
            if ($header !== '') {
                return $header;
            }
        }

        $queryToken = trim((string) $request->getQueryParam('_token', ''));
        if ($queryToken !== '') {
            return $queryToken;
        }

        $body = $request->getBody();
        if ($body === '') {
            return '';
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $jsonToken = trim((string) ($decoded['_token'] ?? ''));
                if ($jsonToken !== '') {
                    return $jsonToken;
                }
            }
        }

        parse_str($body, $parsed);
        if (is_array($parsed)) {
            return trim((string) ($parsed['_token'] ?? ''));
        }

        return '';
    }

    private function failureResponse(Request $request): Response
    {
        $path = ltrim($request->getPath(), '/');
        $accept = strtolower((string) $request->header('Accept', ''));
        $isJson = str_starts_with($path, 'api/') || str_contains($accept, 'json');

        if ($isJson) {
            return new Response(
                '{"success":false,"error":"csrf_token_invalid","message":"Invalid CSRF token."}',
                419,
                ['Content-Type' => 'application/json; charset=utf-8'],
            );
        }

        return new Response('Invalid CSRF token.', 419, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
