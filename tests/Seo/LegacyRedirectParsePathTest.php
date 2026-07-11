<?php

declare(strict_types=1);

namespace Tests\Seo;

require_once dirname(__DIR__, 2) . '/includes/src/Engine/Seo/Legacy/legacy-redirect-helpers.php';

final class LegacyRedirectParsePathTest extends \PHPUnit\Framework\TestCase
{
    public function testParsesTopicLegacyPathWithTrailingGarbage(): void
    {
        $parsed = \legacyRedirectParsePath('/konu/fs-25-ek-tarla-bilgisi-modu-indir.997/[QUOTE=bad]');

        $this->assertIsArray($parsed);
        $this->assertSame('topic', $parsed['type']);
        $this->assertSame('fs-25-ek-tarla-bilgisi-modu-indir', $parsed['slug']);
        $this->assertSame(997, $parsed['legacy_id']);
        $this->assertSame('/konu/fs-25-ek-tarla-bilgisi-modu-indir.997/', $parsed['path']);
    }

    public function testParsesCategoryLegacyPathWithTrailingGarbage(): void
    {
        $parsed = \legacyRedirectParsePath('/forums/fs-25-kamyon-araba-modlari.17/page-10');

        $this->assertIsArray($parsed);
        $this->assertSame('category', $parsed['type']);
        $this->assertSame('fs-25-kamyon-araba-modlari', $parsed['slug']);
        $this->assertSame(17, $parsed['legacy_id']);
        $this->assertSame('/forums/fs-25-kamyon-araba-modlari.17/', $parsed['path']);
    }

    public function testRejectsNonLegacyTopicPaths(): void
    {
        $this->assertNull(\legacyRedirectParsePath('/konu/fs-25-ek-tarla-bilgisi-modu-indir/page-2'));
    }
}
