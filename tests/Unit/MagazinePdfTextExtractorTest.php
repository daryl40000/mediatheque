<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazinePdfTextExtractor;
use PHPUnit\Framework\TestCase;

final class MagazinePdfTextExtractorTest extends TestCase
{
    public function testNormalizeForStorageCollapsesWhitespace(): void
    {
        $raw = "Ligne 1\n\n\nLigne 2\t\tmot";
        $normalized = MagazinePdfTextExtractor::normalizeForStorage($raw);
        $this->assertStringContainsString('Ligne 1', $normalized);
        $this->assertStringContainsString('Ligne 2', $normalized);
        $this->assertStringNotContainsString("\0", $normalized);
    }

    public function testNormalizeForStorageRespectsMaxLength(): void
    {
        $long = str_repeat('a', MagazinePdfTextExtractor::MAX_STORED_CHARS + 500);
        $normalized = MagazinePdfTextExtractor::normalizeForStorage($long);
        $this->assertSame(MagazinePdfTextExtractor::MAX_STORED_CHARS, mb_strlen($normalized));
    }
}
