<?php

declare(strict_types=1);

final class ScraperUrlGuard
{
    /**
     * @return array{scheme:string,host:string,port:int,addresses:list<string>,curl_resolve:string}|null
     */
    public static function inspect(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = self::normalizeHost((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return null;
        }

        if (
            in_array($host, ['localhost', 'localhost.localdomain'], true) ||
            str_ends_with($host, '.localhost')
        ) {
            return null;
        }

        $addresses = self::resolvePublicAddresses($host);
        if ($addresses === []) {
            return null;
        }

        $pinnedAddress = $addresses[0];
        if (str_contains($pinnedAddress, ':')) {
            $pinnedAddress = '[' . $pinnedAddress . ']';
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'addresses' => $addresses,
            'curl_resolve' => $host . ':' . $port . ':' . $pinnedAddress,
        ];
    }

    public static function isSafe(string $url): bool
    {
        return self::inspect($url) !== null;
    }

    public static function isSafeProxyUrl(string $url): bool
    {
        // Proxy transport can resolve or route destinations independently of
        // CURLOPT_RESOLVE. Keep outbound requests fail-closed until a pinned
        // proxy transport is implemented.
        return trim($url) === '';
    }

    /**
     * @return list<string>
     */
    private static function resolvePublicAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIpAddress($host) ? [$host] : [];
        }

        $addresses = [];
        if (function_exists('dns_get_record')) {
            $dnsTypes = DNS_A;
            if (defined('DNS_AAAA')) {
                $dnsTypes |= DNS_AAAA;
            }
            foreach (@dns_get_record($host, $dnsTypes) ?: [] as $record) {
                if (!empty($record['ip'])) {
                    $addresses[] = (string) $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $addresses[] = (string) $record['ipv6'];
                }
            }
        }

        if ($addresses === []) {
            foreach (@gethostbynamel($host) ?: [] as $address) {
                $addresses[] = (string) $address;
            }
        }

        $addresses = array_values(array_unique($addresses));
        foreach ($addresses as $address) {
            if (!self::isPublicIpAddress($address)) {
                return [];
            }
        }

        return $addresses;
    }

    private static function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host, "[] \t\n\r\0\x0B")), '.');
    }

    private static function isPublicIpAddress(string $ip): bool
    {
        $mappedIpv4 = self::mappedIpv4Address($ip);
        if ($mappedIpv4 !== null) {
            $ip = $mappedIpv4;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private static function mappedIpv4Address(string $ip): ?string
    {
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
