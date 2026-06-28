<?php

declare(strict_types=1);

namespace App\Core\Security;

use InvalidArgumentException;

final class Nonce
{
    public static function generate(int $bytes = 16): string
    {
        if ($bytes < 1) {
            throw new InvalidArgumentException('Nonce byte length must be greater than zero.');
        }

        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function isValid(string $nonce): bool
    {
        return $nonce !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $nonce) === 1;
    }

    public static function verify(string $expected, string $actual): bool
    {
        return self::isValid($expected) && self::isValid($actual) && hash_equals($expected, $actual);
    }

    public static function equals(string $expected, string $actual): bool
    {
        return self::verify($expected, $actual);
    }
}
