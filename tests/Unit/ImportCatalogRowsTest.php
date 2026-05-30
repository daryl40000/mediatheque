<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CatalogExportSchema;
use Moncine\ImportCatalogRows;
use Moncine\ImportFilmRows;
use Moncine\MoncineContentKind;
use PHPUnit\Framework\TestCase;

final class ImportCatalogRowsTest extends TestCase
{
    public function testRowToOeuvreParsesCatalogExportColumns(): void
    {
        $header = CatalogExportSchema::headers();
        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);

        $row = [
            '1',
            'Le Film',
            'Original Title',
            'Dupont',
            '1h30',
            'Action',
            '1999',
            'France',
            'Acteur A',
            'Acteur B',
            'Acteur C',
            'Un synopsis',
            '/posters/1.jpg',
            '12345',
            'Film',
            MoncineContentKind::FILM,
        ];

        $data = ImportCatalogRows::rowToOeuvre($row, $map);

        $this->assertSame(1, $data['oeuvre_id']);
        $this->assertSame('Le Film', $data['titre']);
        $this->assertSame(90, $data['duree_min']);
        $this->assertSame(1999, $data['annee']);
        $this->assertSame(12345, $data['tmdb_id']);
        $this->assertSame(MoncineContentKind::FILM, $data['moncine_kind']);
    }
}
