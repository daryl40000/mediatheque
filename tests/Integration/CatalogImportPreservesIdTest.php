<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogExportSchema;
use Moncine\ImportRunner;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class CatalogImportPreservesIdTest extends MoncineTestCase
{
    public function testCatalogImportKeepsOeuvreIdFromExport(): void
    {
        $this->loginAsAdmin();

        $header = CatalogExportSchema::headers();
        $row = array_fill(0, count($header), '');
        $map = \Moncine\ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);
        $row[$map['oeuvre_id']] = '4242';
        $row[$map['titre']] = 'Film ID fixe';
        $row[$map['realisateur']] = 'Testeur';
        $row[$map['synopsis']] = 'Synopsis test';

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $oeuvre = (new OeuvreRepository())->findById(4242);
        $this->assertNotNull($oeuvre);
        $this->assertSame('Film ID fixe', $oeuvre['titre']);
    }
}
