<?php

declare(strict_types=1);

namespace App\Core\Http;

final class RedirectResponse extends Response
{
    public function __construct(string $location, int $statusCode = 302, array $headers = [])
    {
        $this->location = $location;

        parent::__construct('', $statusCode, array_merge(['Location' => $location], $headers));
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public static function onlyTrusted(string $target): bool
    {
        $target = trim($target);
        if ($target === '') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $target) === 1 || str_contains($target, '\\')) {
            return false;
        }

        if ($target[0] === '/') {
            return !str_starts_with($target, '//');
        }

        $parts = parse_url($target);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $trustedHosts = ['localhost', '127.0.0.1', '::1'];
        $configuredBaseUrl = (string) (
            getenv('BASE_URL')
            ?: getenv('APP_BASE_URL')
            ?: getenv('APP_URL')
            ?: ($_ENV['APP_URL'] ?? '')
        );
        $configuredHost = strtolower((string) parse_url($configuredBaseUrl, PHP_URL_HOST));
        if ($configuredHost !== '') {
            $trustedHosts[] = $configuredHost;
        }

        $trustedHostsConfig = (string) (getenv('APP_TRUSTED_HOSTS') ?: ($_ENV['APP_TRUSTED_HOSTS'] ?? ''));
        foreach (array_map('trim', explode(',', $trustedHostsConfig)) as $trustedHost) {
            if ($trustedHost === '') {
                continue;
            }

            if (str_contains($trustedHost, '://')) {
                $trustedHost = (string) parse_url($trustedHost, PHP_URL_HOST);
            }

            $trustedHost = strtolower(trim($trustedHost));
            if ($trustedHost !== '') {
                $trustedHosts[] = $trustedHost;
            }
        }

        return in_array($host, $trustedHosts, true);
    }

    private string $location;
}
