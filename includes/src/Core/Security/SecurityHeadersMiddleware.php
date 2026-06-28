<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware;

final class SecurityHeadersMiddleware implements Middleware
{
    /** @var array<string, list<string>> */
    private array $csp;

    /** @var array{max-age:int, includeSubDomains:bool, preload:bool}|null */
    private ?array $hsts;

    /** @var array{embedder:string, opener:string, resource:string} */
    private array $crossOrigin;

    private bool $isProduction;

    private ?string $reportUri;

    /**
     * @param array<string, mixed> $envConfig
     */
    public function __construct(
        private array $envConfig = [],
        ?array $csp = null,
        ?array $hsts = null,
        ?array $crossOrigin = null,
        ?string $reportUri = null,
    ) {
        $this->isProduction = $this->envBool('APP_ENV') === 'production';
        $this->csp = $csp ?? $this->defaultCsp();
        $this->hsts = $this->isProduction && $this->envBool('APP_FORCE_HTTPS', false)
            ? ($hsts ?? $this->defaultHsts())
            : null;
        $this->crossOrigin = $crossOrigin ?? $this->defaultCrossOrigin();
        $this->reportUri = $reportUri;
    }

    /**
     * Create instance from runtime env config (for use in init.php bootstrap).
     *
     * @param array<string, mixed> $envConfig
     */
    public static function fromEnvConfig(array $envConfig = []): self
    {
        $reportUri = null;
        $cspReportEndpoint = (string) ($envConfig['APP_CSP_REPORT_URI'] ?? '');
        if ($cspReportEndpoint !== '') {
            $reportUri = $cspReportEndpoint;
        }

        $middleware = new self($envConfig, null, null, null, $reportUri);

        $strictNonce = (bool) ($envConfig['APP_CSP_STRICT_NONCE'] ?? false);
        $allowUnsafeInline = (bool) ($envConfig['APP_CSP_ALLOW_UNSAFE_INLINE'] ?? !$strictNonce);
        $allowUnsafeInlineStyles = (bool) ($envConfig['APP_CSP_ALLOW_UNSAFE_INLINE_STYLES'] ?? !$strictNonce);
        $appDebug = (bool) ($envConfig['APP_DEBUG'] ?? false);

        $csp = $middleware->getCsp();

        // Apply nonce
        $nonce = appCspNonce();
        $csp['script-src'][] = "'nonce-" . $nonce . "'";
        $csp['style-src'][] = "'nonce-" . $nonce . "'";

        if ($allowUnsafeInline) {
            $csp['script-src'][] = "'unsafe-inline'";
        }
        if ($allowUnsafeInlineStyles) {
            $csp['style-src'][] = "'unsafe-inline'";
        }
        $csp['style-src-attr'][] = "'unsafe-inline'";
        if ($appDebug) {
            $csp['script-src'][] = "'unsafe-eval'";
        }

        $middleware->csp = $csp;

        return $middleware;
    }

    public function process(Request $request, Handler $next): Response
    {
        $response = $next->handle($request);

        if (headers_sent()) {
            return $response;
        }

        $headers = $this->buildHeaders();

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    public function buildHeaders(): array
    {
        $headers = [];

        // Content Security Policy
        $headers['Content-Security-Policy'] = $this->buildCsp();

        // CSP violation reporting
        if ($this->reportUri !== null) {
            $headers['Content-Security-Policy-Report-Only'] = $this->buildCspReportOnly();
        }

        // HTTP Strict Transport Security (production only, HTTPS only)
        if ($this->hsts !== null) {
            $hsts = 'max-age=' . $this->hsts['max-age'];
            if ($this->hsts['includeSubDomains']) {
                $hsts .= '; includeSubDomains';
            }
            if ($this->hsts['preload']) {
                $hsts .= '; preload';
            }
            $headers['Strict-Transport-Security'] = $hsts;
        }

        // Standard headers
        $headers['X-Frame-Options'] = 'SAMEORIGIN';
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        // Permissions Policy
        $headers['Permissions-Policy'] = $this->buildPermissionsPolicy();

        // Cross-Origin Policies
        $headers['Cross-Origin-Embedder-Policy'] = $this->crossOrigin['embedder'];
        $headers['Cross-Origin-Opener-Policy'] = $this->crossOrigin['opener'];
        $headers['Cross-Origin-Resource-Policy'] = $this->crossOrigin['resource'];

        return $headers;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCsp(): array
    {
        return $this->csp;
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }

    /**
     * @param array<string, list<string>> $directives
     */
    public function withCspDirectives(array $directives): self
    {
        $clone = clone $this;
        foreach ($directives as $directive => $sources) {
            $clone->csp[$directive] = array_values(array_unique(array_merge(
                $clone->csp[$directive] ?? [],
                $sources,
            )));
        }

        return $clone;
    }

    private function buildCsp(): string
    {
        $parts = [];
        foreach ($this->csp as $directive => $sources) {
            if ($sources !== []) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        $csp = implode('; ', $parts);

        // Append report-uri/report-to to the main CSP when reportUri is set
        if ($this->reportUri !== null) {
            $csp .= '; report-uri ' . $this->reportUri;
            $csp .= '; report-to csp-endpoint';
        }

        return $csp;
    }

    private function buildCspReportOnly(): string
    {
        $parts = [];
        foreach ($this->csp as $directive => $sources) {
            if ($sources !== []) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        $csp = implode('; ', $parts);
        $csp .= '; report-uri ' . $this->reportUri;
        $csp .= '; report-to csp-endpoint';

        return $csp;
    }

    /**
     * @return array{endpoints:list<array{url:string}>, group:string}
     */
    public function getReportToConfig(): ?array
    {
        if ($this->reportUri === null) {
            return null;
        }

        return [
            'group' => 'csp-endpoint',
            'max_age' => 10886400,
            'endpoints' => [
                ['url' => $this->reportUri],
            ],
        ];
    }

    private function buildPermissionsPolicy(): string
    {
        $policies = [];
        $features = [
            'geolocation' => [],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'accelerometer' => [],
        ];

        foreach ($features as $feature => $allowlist) {
            $policies[] = $allowlist === []
                ? "$feature=()"
                : "$feature=(" . implode(' ', $allowlist) . ")";
        }

        return implode(', ', $policies);
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultCsp(): array
    {
        return [
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
        ];
    }

    /**
     * @return array{max-age:int, includeSubDomains:bool, preload:bool}
     */
    private function defaultHsts(): array
    {
        return [
            'max-age' => 31536000,
            'includeSubDomains' => true,
            'preload' => true,
        ];
    }

    /**
     * @return array{embedder:string, opener:string, resource:string}
     */
    private function defaultCrossOrigin(): array
    {
        return [
            'embedder' => 'require-corp',
            'opener' => 'same-origin',
            'resource' => 'same-origin',
        ];
    }

    private function envBool(string $key, bool $default = false): bool
    {
        $value = $this->envConfig[$key] ?? ($_ENV[$key] ?? $_SERVER[$key] ?? null);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
