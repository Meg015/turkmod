<?php

declare(strict_types=1);

final class ScraperEncodingNormalizer
{
    public static function normalizeHtml(string $html): string
    {
        $html = preg_replace('/^\xEF\xBB\xBF/u', '', $html) ?? $html;

        if (preg_match('/<meta[^>]+charset=["\']?([^"\'>\s]+)/i', $html, $matches)) {
            $charset = strtoupper(trim($matches[1]));
            if ($charset !== '' && $charset !== 'UTF-8') {
                $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
                if (is_string($converted) && $converted !== '') {
                    return self::decodeEntities($converted);
                }
            }
        }

        if (!mb_check_encoding($html, 'UTF-8')) {
            $detected = mb_detect_encoding($html, ['UTF-8', 'Windows-1254', 'ISO-8859-9', 'Windows-1252', 'ISO-8859-1'], true);
            if ($detected && $detected !== 'UTF-8') {
                $converted = @mb_convert_encoding($html, 'UTF-8', $detected);
                if (is_string($converted) && $converted !== '') {
                    return self::decodeEntities($converted);
                }
            }
        }

        if (preg_match('/[ÂÃ][\x80-\xBF]/u', $html)) {
            $fixed = @mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
            if (is_string($fixed) && $fixed !== '' && mb_check_encoding($fixed, 'UTF-8') && !preg_match('/[ÂÃ][\x80-\xBF]/u', $fixed)) {
                return self::decodeEntities($fixed);
            }
        }

        return self::decodeEntities(str_replace(array_keys(self::replacementMap()), array_values(self::replacementMap()), $html));
    }

    private static function decodeEntities(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function replacementMap(): array
    {
        $hexMap = [
            'c3a2e28093' => '-',
            'c3a2e28094' => '-',
            'c3a2e2809c' => '"',
            'c3a2e2809d' => '"',
            'c3a2e28098' => "'",
            'c3a2e28099' => "'",
            'c3a2e280a2' => '*',
            'c3a2e280a6' => '...',
            'c383c297' => 'x',
            'c383c2a1' => "\u{00E1}",
            'c383c2a9' => "\u{00E9}",
            'c383c2ad' => "\u{00ED}",
            'c383c2b3' => "\u{00F3}",
            'c383c2ba' => "\u{00FA}",
            'c383c2b1' => "\u{00F1}",
            'c383c2a7' => "\u{00E7}",
            'c383c2bc' => "\u{00FC}",
            'c383c2b6' => "\u{00F6}",
            'c383c2a4' => "\u{00E4}",
            'c384c2b0' => "\u{0130}",
            'c384c2b1' => "\u{0131}",
            'c385c29e' => "\u{015E}",
            'c385c29f' => "\u{015F}",
            'c384c29f' => "\u{011F}",
            'c384c29e' => "\u{011E}",
            'c383c287' => "\u{00C7}",
            'c383c29c' => "\u{00DC}",
            'c383c296' => "\u{00D6}",
        ];

        $map = ['??ndir' => "\u{0130}ndir"];
        foreach ($hexMap as $hex => $value) {
            $key = hex2bin($hex);
            if (is_string($key)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }
}