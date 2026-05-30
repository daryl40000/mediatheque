<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogExportSchema;
use Moncine\ImportFilmRows;
use Moncine\ImportRunner;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class CatalogImportReplaceCatalogTest extends MoncineTestCase
{
    public function testReplaceCatalogRecreatesExplicitIds(): void
    {
        $this->loginAsAdmin();

        $admin = new \Moncine\CatalogAdmin();
        $admin->importOeuvreFromExport([
            'titre' => 'Ancien titre',
            'realisateur' => 'X',
        ], ['titre', 'realisateur']);

        $wrong = (new OeuvreRepository())->findByTitreAndRealisateur('Ancien titre', 'X');
        $this->assertNotNull($wrong);
        $this->assertSame(1, (int) $wrong['id']);

        $header = CatalogExportSchema::headers();
        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);
        $row = array_fill(0, count($header), '');
        $row[$map['oeuvre_id']] = '9001';
        $row[$map['titre']] = 'Ancien titre';
        $row[$map['realisateur']] = 'X';
        $row[$map['synopsis']] = 'Synopsis';

        $result = (new ImportRunner())->importCatalogSheet([$row], $header, true);

        $this->assertSame(1, $result['imported']);
        $this->assertNull((new OeuvreRepository())->findById(1));
        $this->assertNotNull((new OeuvreRepository())->findById(9001));
    }
}
