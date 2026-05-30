<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ImportCsv;
use Moncine\ImportFilmRows;
use Moncine\ImportFormat;
use Moncine\CatalogExportSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportCsvParsingTest extends TestCase
{
    #[DataProvider('durationProvider')]
    public function testParseDurationMinutes(string $raw, int $expected): void
    {
        $this->assertSame($expected, ImportCsv::parseDurationMinutes($raw));
    }

    /** @return list<array{0: string, 1: int}> */
    public static function durationProvider(): array
    {
        return [
            ['1h56', 116],
            ['2h', 120],
            ['90 min', 90],
            ['90', 90],
            ['', 0],
            ['invalide', 0],
        ];
    }

    public function testParseVueDateAcceptsFrenchFormat(): void
    {
        $this->assertSame('2024-03-15', ImportCsv::parseVueDate('15/03/2024'));
        $this->assertNull(ImportCsv::parseVueDate(''));
    }

    public function testParseNoteClampsToValidRange(): void
    {
        $this->assertSame(8, ImportCsv::parseNote('8'));
        $this->assertNull(ImportCsv::parseNote(''));
        $this->assertSame(10, ImportCsv::parseNote('11'));
    }

    /** BOM + guillemets sur la 1re colonne (export Excel / LibreOffice). */
    public function testNormalizeHeaderStripsBomAndQuotes(): void
    {
        $this->assertSame('id catalogue', ImportFilmRows::normalizeHeader("\xEF\xBB\xBF\"ID catalogue\""));

        $header = ["\xEF\xBB\xBF\"ID catalogue\"", 'Titre', 'Synopsis'];
        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);
        $this->assertArrayHasKey('oeuvre_id', $map);

        $analysis = ImportFormat::analyzeHeader($header);
        $this->assertTrue($analysis['has_id_column']);
        $this->assertSame(ImportFormat::KIND_CATALOG, $analysis['format']);
    }
}
