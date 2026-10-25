<?php
declare(strict_types=1);

/** Retain GIF raster frames/animation while discarding comments, text, unknown extensions and trailing bytes. */
function mediaSanitizeGifData(string $contents, int $canvasWidth, int $canvasHeight): string
{
    $offset = 0;
    $length = strlen($contents);
    $read = static function (int $count) use (&$offset, $length, $contents): string {
        if ($count < 0 || $count > $length - $offset) {
            throw new RuntimeException('GIF dosyası eksik veya bozuk.');
        }
        $part = substr($contents, $offset, $count);
        $offset += $count;
        return $part;
    };
    $subBlocks = static function () use ($read): string {
        $blocks = '';
        do {
            $size = $read(1);
            $blocks .= $size . $read(ord($size));
        } while ($size !== "\0");
        return $blocks;
    };
    $header = $read(13);
    $screen = unpack('vwidth/vheight', substr($header, 6, 4));
    if (!in_array(substr($header, 0, 6), ['GIF87a', 'GIF89a'], true)
        || $screen['width'] !== $canvasWidth || $screen['height'] !== $canvasHeight || $canvasWidth < 1 || $canvasHeight < 1) {
        throw new RuntimeException('Geçersiz GIF başlığı.');
    }
    $clean = $header;
    $packed = ord($header[10]);
    $globalPalette = ($packed & 0x80) ? $read(3 * (1 << (($packed & 7) + 1))) : '';
    $clean .= $globalPalette;
    $frames = 0;
    $pixels = 0;
    $pendingControl = '';
    while ($offset < $length) {
        $marker = $read(1);
        if ($marker === "\x3B" && $frames > 0) { return $clean . $marker; }
        if ($marker === "\x2C") {
            $descriptor = $read(9);
            $frame = unpack('vleft/vtop/vwidth/vheight/Cpacked', $descriptor);
            $pixels += (float) $frame['width'] * $frame['height'];
            if (++$frames > 1000 || $pixels > 240000000 || $frame['width'] < 1 || $frame['height'] < 1 || ($frame['packed'] & 0x18)
                || $frame['left'] + $frame['width'] > $canvasWidth || $frame['top'] + $frame['height'] > $canvasHeight) {
                throw new RuntimeException('GIF kareleri çok büyük veya geçersiz.');
            }
            $palette = ($frame['packed'] & 0x80) ? $read(3 * (1 << (($frame['packed'] & 7) + 1))) : '';
            $codeSize = $read(1);
            if (ord($codeSize) < 2 || ord($codeSize) > 8 || ($palette === '' && $globalPalette === '')) {
                throw new RuntimeException('Geçersiz GIF sıkıştırma biçimi.');
            }
            if ($pendingControl !== '' && (ord($pendingControl[3]) & 1)
                && ord($pendingControl[6]) >= strlen($palette !== '' ? $palette : $globalPalette) / 3) {
                throw new RuntimeException('Geçersiz GIF saydamlık rengi.');
            }
            $blocks = $subBlocks();
            $standalone = substr($header, 0, 6) . pack('vv', $frame['width'], $frame['height']) . substr($header, 10)
                . $globalPalette . $pendingControl . $marker . pack('vv', 0, 0) . substr($descriptor, 4)
                . $palette . $codeSize . $blocks . "\x3B";
            $decoded = $blocks !== "\0" ? @imagecreatefromstring($standalone) : false;
            if ($decoded === false || imagesx($decoded) !== $frame['width'] || imagesy($decoded) !== $frame['height']) {
                throw new RuntimeException('GIF karesi çözümlenemedi.');
            }
            unset($decoded);
            $clean .= $pendingControl . $marker . $descriptor . $palette . $codeSize . $blocks;
            $pendingControl = '';
        } elseif ($marker === "\x21") {
            $label = $read(1);
            $blocks = $subBlocks();
            if ($label === "\xF9") {
                if (strlen($blocks) !== 6 || ord($blocks[0]) !== 4
                    || (ord($blocks[1]) & 0xE0) || ((ord($blocks[1]) >> 2) & 7) > 3) {
                    throw new RuntimeException('Geçersiz GIF kare kontrolü.');
                }
                $pendingControl = $marker . $label . $blocks;
            } elseif ($label === "\xFF" && strlen($blocks) === 17
                && in_array(substr($blocks, 0, 12), ["\x0BNETSCAPE2.0", "\x0BANIMEXTS1.0"], true)
                && substr($blocks, 12, 2) === "\x03\x01" && $blocks[16] === "\0") {
                $clean .= $marker . $label . $blocks;
            } elseif ($label === "\x01") {
                $pendingControl = ''; // A preceding graphic control applied to the discarded text block.
            }
        } else {
            throw new RuntimeException('Geçersiz GIF veri bloğu.');
        }
    }
    throw new RuntimeException('GIF dosyası tamamlanmamış.');
}
