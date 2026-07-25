<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazinePdfService;
use PHPUnit\Framework\TestCase;

final class MagazinePdfPathTest extends TestCase
{
    public function testStandardAndHorsSerieSameNumeroProduceDistinctPaths(): void
    {
        $standard = MagazinePdfService::buildMagazinePdfRelativePath(
            'Tilt',
            '33',
            '1996-03-01',
            false,
            100
        );
        $horsSerie = MagazinePdfService::buildMagazinePdfRelativePath(
            'Tilt',
            '33',
            '1996-03-01',
            true,
            101
        );

        $this->assertIsString($standard);
        $this->assertIsString($horsSerie);
        $this->assertNotSame($standard, $horsSerie);
        $this->assertStringContainsString('-33-id100.pdf', $standard);
        $this->assertStringContainsString('-33-hs-id101.pdf', $horsSerie);
        $this->assertStringNotContainsString('-hs-', $standard);
    }

    public function testPathRequiresPositiveOeuvreId(): void
    {
        $this->assertFalse(
            MagazinePdfService::buildMagazinePdfRelativePath('Tilt', '1', '1996', false, 0)
        );
    }
}
