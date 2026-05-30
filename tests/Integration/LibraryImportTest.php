<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\CatalogFilmRepository;
use Moncine\FoyerRepository;
use Moncine\FilmRepository;
use Moncine\ImportRunner;
use Moncine\LibraryExportSchema;
use Moncine\LibraryStatut;
use Moncine\Tests\Support\MoncineTestCase;

final class LibraryImportTest extends MoncineTestCase
{
    public function testImportLibraryByOeuvreIdCreatesCollectionEntry(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Matrix', 'Wachowski');

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Statut')] = 'collection';
        $row[$this->columnIndex($header, 'Support')] = 'dvd';
        $row[$this->columnIndex($header, 'format image')] = '1080p';
        $row[$this->columnIndex($header, 'Bande sonore FR')] = 'VF';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);
        $this->assertSame(LibraryStatut::COLLECTION, $library['statut']);
        $this->assertSame('dvd', $library['support_physique']);
        $this->assertSame('1080p', $library['format_image']);
    }

    public function testImportLibraryWishlistStatut(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Envie');

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Statut')] = 'mes envies';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);
        $this->assertSame(1, $result['imported']);

        $film = (new FilmRepository())->findByTitreAndRealisateur('Film Envie', 'Réalisateur Test');
        $this->assertNotNull($film);
        $this->assertSame(LibraryStatut::WISHLIST, $film['statut'] ?? '');
    }

    public function testImportLibraryFailsWhenOeuvreMissing(): void
    {
        $this->loginAsAdmin();

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = '99999';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('99999', $result['errors'][0]);
    }

    public function testImportLibraryPrefersOeuvreIdOverExportedBibliothequeId(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('300', 'Zack Snyder');

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'ID bibliothèque')] = '1';
        $row[$this->columnIndex($header, 'Statut')] = 'Mes films';
        $row[$this->columnIndex($header, 'Support')] = 'blu-ray';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);
        $this->assertSame('bluray', $library['support_physique']);
    }

    public function testImportLibraryRecordsViewingFromVuColumn(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Vu');

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Vu')] = '10/05/2024';
        $row[$this->columnIndex($header, 'Note')] = '9';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['vues']);

        $film = (new CatalogFilmRepository())->findByTitreAndRealisateur('Film Vu', 'Réalisateur Test');
        $this->assertNotNull($film);
    }

    /**
     * @param list<string> $header
     * @return list<string>
     */
    private function emptyLibraryRow(array $header): array
    {
        return array_fill(0, count($header), '');
    }

    /**
     * @param list<string> $header
     */
    private function columnIndex(array $header, string $label): int
    {
        $index = array_search($label, $header, true);
        $this->assertNotFalse($index, 'Colonne « ' . $label . ' » introuvable');

        return (int) $index;
    }
}
