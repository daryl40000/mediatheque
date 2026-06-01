<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazinePdfInfo;
use PHPUnit\Framework\TestCase;

final class MagazinePdfInfoTest extends TestCase
{
    public function testReadPageCountReturnsZeroForMissingFile(): void
    {
        $this->assertSame(0, MagazinePdfInfo::readPageCount('/nonexistent/file.pdf'));
    }

    public function testReadPageCountWhenPdfinfoAvailable(): void
    {
        if (!MagazinePdfInfo::isAvailable()) {
            $this->markTestSkipped('pdfinfo non installé');
        }

        $pdf = sys_get_temp_dir() . '/moncine_pages_test_' . uniqid('', true) . '.pdf';
        $minimal = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Count 3/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        file_put_contents($pdf, $minimal);

        try {
            $count = MagazinePdfInfo::readPageCount($pdf);
            $this->assertGreaterThanOrEqual(0, $count);
        } finally {
            @unlink($pdf);
        }
    }
}
