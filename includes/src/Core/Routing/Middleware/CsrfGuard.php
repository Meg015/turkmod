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

        $token = $this->resolveToken($request);
        if (verify_csrf_token($token)) {
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

        $parsedBody = $request->getParsedBody();
        if (array_key_exists('_token', $parsedBody)) {
            return is_string($parsedBody['_token']) ? trim($parsedBody['_token']) : '';
        }

        $body = $request->getBody();
        if ($body === '') {
            return '';
        }

        $contentType = trim(explode(';', strtolower((string) $request->header('Content-Type', '')))[0]);
        if ($contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            $decoded = json_decode($body, true);
            $token = is_array($decoded) ? ($decoded['_token'] ?? null) : null;
            return is_string($token) ? trim($token) : '';
        }

        if ($contentType === 'application/x-www-form-urlencoded') {
            parse_str($body, $parsed);
            $token = $parsed['_token'] ?? null;
            return is_string($token) ? trim($token) : '';
        }

        return '';
    }

    private function failureResponse(Request $request): Response
    {
        $path = ltrim($request->getPath(), '/');
        $accept = strtolower((string) $request->header('Accept', ''));
        $isJson = preg_match('~(?:^|/)api/~', $path) === 1
            || str_contains($accept, 'json')
            || str_contains(strtolower((string) $request->header('Content-Type', '')), 'json');
        $headers = ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex'];

        if ($isJson) {
            return new Response(
                '{"success":false,"error":"csrf_token_invalid","message":"Güvenlik doğrulaması yenilendi. Lütfen işlemi tekrar deneyin.","retryable":true}',
                419,
                $headers + ['Content-Type' => 'application/json; charset=utf-8'],
            );
        }

        return new Response('Güvenlik doğrulaması yenilendi. Lütfen işlemi tekrar deneyin.', 419, $headers + ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
