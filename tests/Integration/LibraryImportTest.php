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
        $this->assertStringContainsString('99999', implode(' ', $result['errors']));
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

    public function testLibraryExportIncludesFilmAndBd(): void
    {
        $this->loginAsAdmin();
        (new \Moncine\SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        if (!BdRepository::isAvailable()) {
            $this->markTestSkipped('Module BD indisponible.');
        }

        MediaContext::set(MediaDomain::FILM);
        $filmOeuvreId = $this->seedCatalogOeuvre('Film Export Mixte', 'Réal Export');
        (new BibliothequeRepository())->insert(
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId()),
            $filmOeuvreId,
            ['statut' => LibraryStatut::COLLECTION]
        );

        MediaContext::set(MediaDomain::BD);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Série Export Mixte',
            'publication_type' => 'irregulier',
        ], MediaDomain::BD);
        $this->assertIsInt($seriesId);
        $bdOeuvreId = (new BdRepository())->createCatalogOnly([
            'series_id' => $seriesId,
            'tome_numero' => 1,
            'titre' => 'Tome Export Mixte',
        ]);
        $this->assertIsInt($bdOeuvreId);
        (new BdRepository())->addFromCatalogOeuvre(
            $bdOeuvreId,
            LibraryStatut::COLLECTION,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );

        // Export demandé depuis l’onglet BD : doit quand même inclure le film.
        MediaContext::set(MediaDomain::BD);
        $rows = (new CatalogFilmRepository())->findAllLibraryForExport();
        $oeuvreIds = array_map(static fn (array $row): int => (int) ($row['oeuvre_id'] ?? 0), $rows);

        $this->assertContains($filmOeuvreId, $oeuvreIds, 'L’export doit contenir le film');
        $this->assertContains($bdOeuvreId, $oeuvreIds, 'L’export doit contenir la BD');

        $byDomain = (new CatalogFilmRepository())->countLibraryEntriesByDomain();
        $this->assertGreaterThanOrEqual(1, (int) ($byDomain[MediaDomain::FILM] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($byDomain[MediaDomain::BD] ?? 0));
    }

    public function testImportLibraryDuplicateOeuvreIdUpdatesInsteadOfFailing(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Doublon CSV', 'Réal Doublon');

        $header = LibraryExportSchema::headers();
        $row1 = $this->emptyLibraryRow($header);
        $row1[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row1[$this->columnIndex($header, 'Statut')] = 'collection';
        $row1[$this->columnIndex($header, 'Support')] = 'dvd';

        $row2 = $this->emptyLibraryRow($header);
        $row2[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row2[$this->columnIndex($header, 'Statut')] = 'collection';
        $row2[$this->columnIndex($header, 'Support')] = 'blu-ray';

        $result = (new ImportRunner())->importLibrarySheet([$row1, $row2], $header);

        $this->assertSame([], $result['errors'], implode('; ', $result['errors']));
        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['added'] ?? 0);
        $this->assertSame(1, $result['updated'] ?? 0);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            Auth::currentUserId(),
            (new FoyerRepository())->currentFoyerIdForUser(Auth::currentUserId())
        );
        $this->assertNotNull($library);
        $this->assertSame('bluray', $library['support_physique']);
    }

    public function testImportLibraryUpdatesExistingWishlistWithoutUniqueError(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Déjà Envie', 'Réal Envie');
        $userId = Auth::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);

        (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
            'statut' => LibraryStatut::WISHLIST,
        ]);

        $header = LibraryExportSchema::headers();
        $row = $this->emptyLibraryRow($header);
        $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
        $row[$this->columnIndex($header, 'Statut')] = 'collection';
        $row[$this->columnIndex($header, 'Support')] = 'dvd';

        $result = (new ImportRunner())->importLibrarySheet([$row], $header);

        $this->assertSame([], $result['errors'], implode('; ', $result['errors']));
        $this->assertGreaterThanOrEqual(1, $result['imported']);

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            $userId,
            $foyerId
        );
        $this->assertNotNull($library);
        $this->assertSame(LibraryStatut::COLLECTION, $library['statut']);
    }

    public function testImportLibraryManyDuplicateWishlistsDoNotPoisonTransaction(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Multi Envies', 'Réal Multi');
        $userId = Auth::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);

        (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
            'statut' => LibraryStatut::WISHLIST,
        ]);

        $header = LibraryExportSchema::headers();
        $rows = [];
        for ($i = 0; $i < 5; $i++) {
            $row = $this->emptyLibraryRow($header);
            $row[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreId;
            $row[$this->columnIndex($header, 'Statut')] = 'mes envies';
            $rows[] = $row;
        }

        // Une autre œuvre après les doublons : doit quand même s’importer
        // (prouve que la transaction n’est pas empoisonnée).
        $oeuvreOk = $this->seedCatalogOeuvre('Film Après Doublons', 'Réal Après');
        $rowOk = $this->emptyLibraryRow($header);
        $rowOk[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreOk;
        $rowOk[$this->columnIndex($header, 'Statut')] = 'collection';
        $rowOk[$this->columnIndex($header, 'Support')] = 'dvd';
        $rows[] = $rowOk;

        $result = (new ImportRunner())->importLibrarySheet($rows, $header);

        $this->assertSame([], $result['errors'], implode('; ', $result['errors']));
        $this->assertGreaterThanOrEqual(1, $result['imported']);

        $libraryOk = (new BibliothequeRepository())->findByOeuvreId($oeuvreOk, $userId, $foyerId);
        $this->assertNotNull($libraryOk);
        $this->assertSame(LibraryStatut::COLLECTION, $libraryOk['statut']);
    }

    public function testImportLibraryAttachesMagazineAndRegistersSeries(): void
    {
        $this->loginAsAdmin();
        (new \Moncine\SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        if (!\Moncine\MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        MediaContext::set(MediaDomain::MAGAZINE);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Série Import Lib Magazine',
            'publication_type' => 'mensuel',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $oeuvreOwned = (new \Moncine\MagazineRepository())->createCatalogIssue($seriesId, [
            'numero' => '42',
            'titre' => 'Numéro Possédé Import',
        ]);
        $this->assertIsInt($oeuvreOwned);
        $oeuvreUnowned = (new \Moncine\MagazineRepository())->createCatalogIssue($seriesId, [
            'numero' => '43',
            'titre' => 'Numéro Non Possédé Import',
        ]);
        $this->assertIsInt($oeuvreUnowned);

        MediaContext::set(MediaDomain::FILM);

        $header = LibraryExportSchema::headers();
        $rowOwned = $this->emptyLibraryRow($header);
        $rowOwned[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreOwned;
        $rowOwned[$this->columnIndex($header, 'Domaine média')] = MediaDomain::MAGAZINE;
        $rowOwned[$this->columnIndex($header, 'Statut')] = 'collection';
        $rowOwned[$this->columnIndex($header, 'Support')] = 'Papier';

        $rowUnowned = $this->emptyLibraryRow($header);
        $rowUnowned[$this->columnIndex($header, 'ID catalogue')] = (string) $oeuvreUnowned;
        $rowUnowned[$this->columnIndex($header, 'Domaine média')] = MediaDomain::MAGAZINE;
        $rowUnowned[$this->columnIndex($header, 'Statut')] = 'collection';
        // Support vide = suivi dans la série mais non possédé.
        $rowUnowned[$this->columnIndex($header, 'Support')] = '';

        $result = (new ImportRunner())->importLibrarySheet([$rowOwned, $rowUnowned], $header);
        $this->assertSame(2, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        $userId = Auth::currentUserId();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);

        $owned = (new BibliothequeRepository())->findByOeuvreId($oeuvreOwned, $userId, $foyerId);
        $this->assertNotNull($owned);
        $this->assertStringContainsString('papier', (string) ($owned['support_physique'] ?? ''));

        $unowned = (new BibliothequeRepository())->findByOeuvreId($oeuvreUnowned, $userId, $foyerId);
        $this->assertNotNull($unowned);
        $this->assertSame('', trim((string) ($unowned['support_physique'] ?? '')));

        $this->assertTrue(
            (new \Moncine\MagazineRepository())->isSeriesInLibrary(
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
