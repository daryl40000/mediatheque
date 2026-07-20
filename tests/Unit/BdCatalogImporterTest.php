<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\BdCatalogImporter;
use Moncine\BdKind;
use Moncine\ImportFilmRows;
use Moncine\Tests\Support\MoncineTestCase;

final class BdCatalogImporterTest extends MoncineTestCase
{
    public function testMapHeadersRecognizesAliases(): void
    {
        $header = ['Série', 'Type', 'N°', 'HS', 'Titre'];
        $map = ImportFilmRows::mapHeaders($header, BdCatalogImporter::COLUMN_ALIASES);
        $this->assertArrayHasKey('serie', $map);
        $this->assertArrayHasKey('kind', $map);
        $this->assertArrayHasKey('tome_numero', $map);
        $this->assertArrayHasKey('hors_serie', $map);
        $this->assertArrayHasKey('titre', $map);
    }

    public function testParseRowAndBool(): void
    {
        $this->assertTrue(BdCatalogImporter::parseBool('oui'));
        $this->assertTrue(BdCatalogImporter::parseBool('HS'));
        $this->assertFalse(BdCatalogImporter::parseBool('non'));

        $importer = new BdCatalogImporter();
        $header = ['serie', 'kind', 'tome_numero', 'hors_serie', 'annee'];
        $map = ImportFilmRows::mapHeaders($header, BdCatalogImporter::COLUMN_ALIASES);
        $parsed = $importer->parseRow(['Astérix', 'manga', '1', 'oui', '1961'], $map);
        $this->assertIsArray($parsed);
        $this->assertSame('Astérix', $parsed['serie']);
        $this->assertSame(BdKind::MANGA, $parsed['kind']);
        $this->assertSame(1, $parsed['tome_numero']);
        $this->assertTrue($parsed['est_hors_serie']);
        $this->assertSame(1961, $parsed['annee']);
    }

    public function testParseRowRequiresSerie(): void
    {
        $importer = new BdCatalogImporter();
        $map = ImportFilmRows::mapHeaders(['serie', 'tome_numero'], BdCatalogImporter::COLUMN_ALIASES);
        $this->assertIsString($importer->parseRow(['', '1'], $map));
    }

    public function testDryRunCountsWithoutWriting(): void
    {
        $this->loginAsAdmin();
        $csv = "serie;kind;tome_numero;titre\n"
            . "Astérix;bd;1;Astérix le Gaulois\n"
            . "Astérix;bd;2;La Serpe d'or\n";
        $tmp = tempnam(sys_get_temp_dir(), 'bdcsv');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $csv);

        $result = (new BdCatalogImporter())->importFromPath($tmp, ['dry_run' => true]);
        unlink($tmp);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['series_created']);
        $this->assertSame(2, $result['tomes_created']);
        $this->assertSame([], $result['errors']);
    }
}
