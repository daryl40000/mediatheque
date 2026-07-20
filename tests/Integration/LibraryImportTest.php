<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BdRepository;
use Moncine\BibliothequeRepository;
use Moncine\CatalogFilmRepository;
use Moncine\FoyerRepository;
use Moncine\FilmRepository;
use Moncine\ImportRunner;
use Moncine\LibraryExportSchema;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SeriesRepository;
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
        $this->assertStringContainsString('Réinitialiser le catalogue', $result['errors'][0]);
    }

    public function testImportLibraryByOeuvreIdIgnoresActiveMediaTab(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);
        $oeuvreId = $this->seedCatalogOeuvre('Film Onglet BD', 'Réal Onglet');

        // L’utilisateur est sur l’onglet BD : l’ID film doit quand même être trouvé.
        MediaContext::set(MediaDomain::BD);

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Statut')] = 'collection';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(1, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        MediaContext::set(MediaDomain::FILM);
        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);
    }

    public function testImportLibraryFallsBackToTitreWhenOeuvreIdStale(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);
        $oeuvreId = $this->seedCatalogOeuvre('Film ID Obsolète', 'Réal Obsolète');

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        // Ancien ID d’une autre instance — le titre permet le rapprochement.
        $row[$this->columnIndex($header, 'ID catalogue')] = '99998';
        $row[$this->columnIndex($header, 'Titre')] = 'Film ID Obsolète';
        $row[$this->columnIndex($header, 'Réalisateur')] = 'Réal Obsolète';
        $row[$this->columnIndex($header, 'Statut')] = 'collection';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame(1, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);
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

    public function testImportLibraryAttachesBdAndRegistersSeries(): void
    {
        $this->loginAsAdmin();
        (new \Moncine\SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        if (!BdRepository::isAvailable()) {
            $this->markTestSkipped('Module BD indisponible.');
        }

        MediaContext::set(MediaDomain::BD);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Série Import Lib BD',
            'publication_type' => 'irregulier',
        ], MediaDomain::BD);
        $this->assertIsInt($seriesId);

        $oeuvreId = (new BdRepository())->createCatalogOnly([
            'series_id' => $seriesId,
            'tome_numero' => 1,
            'titre' => 'Tome Import Lib',
            'scenariste' => 'Auteur BD',
        ]);
        $this->assertIsInt($oeuvreId);

        // Import depuis l’onglet Films : doit quand même rattacher la BD.
        MediaContext::set(MediaDomain::FILM);

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Domaine média')] = MediaDomain::BD;
        $row[$this->columnIndex($header, 'Statut')] = 'collection';
        $row[$this->columnIndex($header, 'Support')] = 'album';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);
        $this->assertSame(1, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);

        $userId = Auth::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
        $this->assertTrue(
            (new BdRepository())->isSeriesInLibrary(
                $seriesId,
                LibraryStatut::COLLECTION,
                $userId,
                $foyerId
            )
        );
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
