<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazinePdfCoverExtractor;
use PHPUnit\Framework\TestCase;

final class MagazinePdfCoverExtractorTest extends TestCase
{
    public function testRenderFirstPageJpegReturnsEmptyForMissingFile(): void
    {
        $this->assertSame('', MagazinePdfCoverExtractor::renderFirstPageJpeg('/nonexistent/file.pdf'));
    }

    public function testRenderFirstPageJpegWhenPdftoppmAvailable(): void
    {
        if (!MagazinePdfCoverExtractor::isAvailable()) {
            $this->markTestSkipped('pdftoppm non installé');
        }

        $pdf = sys_get_temp_dir() . '/moncine_cover_test_' . uniqid('', true) . '.pdf';
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/MediaBox[0 0 200 300]/Parent 2 0 R>>endobj\n"
            . "xref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n%%EOF\n";
        file_put_contents($pdf, $content);

        try {
            $jpeg = MagazinePdfCoverExtractor::renderFirstPageJpeg($pdf);
            $this->assertNotSame('', $jpeg);
            $this->assertStringStartsWith("\xFF\xD8", $jpeg);
        } finally {
            @unlink($pdf);
        }
    }
}
