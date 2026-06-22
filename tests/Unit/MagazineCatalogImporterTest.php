<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineCatalogImporter;
use PHPUnit\Framework\TestCase;

final class MagazineCatalogImporterTest extends TestCase
{
    public function testNormalizeCoverBatchSize(): void
    {
        $this->assertSame(20, MagazineCatalogImporter::normalizeCoverBatchSize(0));
        $this->assertSame(20, MagazineCatalogImporter::normalizeCoverBatchSize(20));
        $this->assertSame(10, MagazineCatalogImporter::normalizeCoverBatchSize(10));
        $this->assertSame(40, MagazineCatalogImporter::normalizeCoverBatchSize(99));
        $this->assertSame(1, MagazineCatalogImporter::normalizeCoverBatchSize(1));
    }
}
