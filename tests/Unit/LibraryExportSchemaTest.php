<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LibraryExportSchema;
use Moncine\MediaDomain;
use PHPUnit\Framework\TestCase;

final class LibraryExportSchemaTest extends TestCase
{
    public function testFilmRowIncludesTmdbId(): void
    {
        $row = LibraryExportSchema::rowToExport([
            'oeuvre_id' => 12,
            'id' => 34,
            'media_domain' => MediaDomain::FILM,
            'titre' => 'Fight Club',
            'tmdb_id' => 550,
        ]);

        $headers = LibraryExportSchema::headers();
        $tmdbColumn = array_search('TMDB ID', $headers, true);

        $this->assertIsInt($tmdbColumn);
        $this->assertSame('550', $row[$tmdbColumn]);
    }

    public function testNonFilmRowLeavesTmdbIdEmpty(): void
    {
        $row = LibraryExportSchema::rowToExport([
            'media_domain' => MediaDomain::JEU,
            'titre' => 'Un jeu',
            'tmdb_id' => 550,
        ]);

        $headers = LibraryExportSchema::headers();
        $tmdbColumn = array_search('TMDB ID', $headers, true);

        $this->assertIsInt($tmdbColumn);
        $this->assertSame('', $row[$tmdbColumn]);
    }

    public function testFilmWithoutTmdbIdLeavesCellEmpty(): void
    {
        $row = LibraryExportSchema::rowToExport([
            'media_domain' => MediaDomain::FILM,
            'titre' => 'Sans lien',
            'tmdb_id' => 0,
        ]);

        $headers = LibraryExportSchema::headers();
        $tmdbColumn = array_search('TMDB ID', $headers, true);

        $this->assertIsInt($tmdbColumn);
        $this->assertSame('', $row[$tmdbColumn]);
    }
}
