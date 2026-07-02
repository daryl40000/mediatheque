<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BdKind;
use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\BdSeriesMetadata;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class BdRepositoryTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::BD);
        $this->loginAsAdmin();
    }

    private function createTestSeries(string $titre = 'Astérix Test'): int
    {
        $created = (new SeriesRepository())->create([
            'titre' => $titre,
            'publication_type' => 'irregulier',
            'tags' => BdKind::BD,
            'editeur' => 'Dargaud',
        ], MediaDomain::BD);
        $this->assertIsInt($created);

        return $created;
    }

    public function testCreateSeriesAndTomeInCollection(): void
    {
        $this->assertTrue(BdRepository::isAvailable());

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries();

        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
            'scenariste' => 'Goscinny',
            'dessinateur' => 'Uderzo',
            'support_physique' => BdPhysicalSupport::ALBUM,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $seriesList = $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $seriesList);
        $this->assertSame('Astérix Test', $seriesList[0]['titre']);
        $this->assertSame(1, (int) $seriesList[0]['tome_count']);

        $tomes = $repo->listTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $tomes);
        $this->assertStringContainsString('Astérix Test', (string) $tomes[0]['display_titre']);
        $this->assertStringContainsString('Tome 1', (string) $tomes[0]['display_titre']);
        $this->assertTrue($tomes[0]['is_possessed']);
        $this->assertSame(BdPhysicalSupport::ALBUM, $tomes[0]['support_physique']);
    }

    public function testCreateTomeWithPossessionFromPostDefaultsToAlbum(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Possession à la création');

        $support = BdRepository::supportFromPost(['support_possede' => '1']);
        $this->assertSame(BdPhysicalSupport::ALBUM, $support);

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
            'support_physique' => $support,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $this->assertTrue($tome['is_possessed']);
        $this->assertSame(BdPhysicalSupport::ALBUM, $tome['support_physique']);
    }

    public function testSortByReadAtDoesNotCrash(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Lecture Test');

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
            'kind' => BdKind::MANGA,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        (new HistoriqueRepository())->recordViewing($bibId, '2024-06-01', 8);

        $asc = $repo->listTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION, 'read_at', 'asc');
        $desc = $repo->listTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION, 'read_at', 'desc');
        $this->assertCount(1, $asc);
        $this->assertCount(1, $desc);
    }

    public function testSeriesRequiresSeriesIdForTome(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();

        $result = $repo->createWithLibrary([
            'tome_numero' => 1,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $this->assertIsString($result);
        $this->assertStringContainsString('série', strtolower($result));
    }

    public function testSeriesKindStoredInTags(): void
    {
        $seriesId = $this->createTestSeries('One Piece Test');
        (new SeriesRepository())->update($seriesId, ['tags' => BdKind::MANGA]);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        $this->assertNotNull($series);
        $this->assertSame(BdKind::MANGA, BdSeriesMetadata::kindFromSeries($series));
    }

    public function testUnownedTomeHasNoPhysicalSupport(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Non possédé Test');

        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 2,
            'support_physique' => '',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $this->assertFalse($tome['is_possessed']);
        $this->assertSame('Non possédé', $tome['possession_label']);
    }

    public function testUpdateTomeChangesMetadataAndSupport(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Update Test');

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
            'scenariste' => 'Auteur A',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $updated = $repo->updateTome($bibId, [
            'tome_numero' => 1,
            'scenariste' => 'Auteur B',
            'support_possede' => true,
            'support_physique' => BdPhysicalSupport::ALBUM,
        ], $userId, $foyerId);
        $this->assertTrue($updated);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $this->assertSame('Auteur B', $tome['scenariste']);
        $this->assertTrue($tome['is_possessed']);
        $this->assertSame(BdPhysicalSupport::ALBUM, $tome['support_physique']);
    }

    public function testUpdatePosterUrl(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Poster Test');

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $oeuvreId = (int) $tome['oeuvre_id'];

        $this->assertTrue($repo->updatePosterUrl($oeuvreId, '/posters/' . $oeuvreId . '.jpg'));

        $updated = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertSame('/posters/' . $oeuvreId . '.jpg', $updated['poster_url'] ?? '');
    }

    public function testUpdateTomePreservesPosterAndMarksPossessed(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Possession poster Test');

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 3,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $oeuvreId = (int) $tome['oeuvre_id'];
        $this->assertTrue($repo->updatePosterUrl($oeuvreId, '/posters/' . $oeuvreId . '.jpg'));

        $updated = $repo->updateTome($bibId, [
            'tome_numero' => 3,
            'support_possede' => true,
            'support_physique' => '',
        ], $userId, $foyerId);
        $this->assertTrue($updated);

        $after = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($after);
        $this->assertTrue($after['is_possessed']);
        $this->assertSame(BdPhysicalSupport::ALBUM, $after['support_physique']);
        $this->assertSame('/posters/' . $oeuvreId . '.jpg', $after['poster_url'] ?? '');
    }

    public function testSavePosterKeepsLocalPathForExistingLocalUrl(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Poster URL Test');

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 4,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $tome = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($tome);
        $oeuvreId = (int) $tome['oeuvre_id'];

        $localPath = '/posters/' . $oeuvreId . '.png';
        $repo->updatePosterUrl($oeuvreId, $localPath);
        $repo->savePoster($oeuvreId, $localPath, null);

        $updated = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($updated);
        $this->assertSame($localPath, (string) ($updated['poster_url'] ?? ''));
    }

    public function testUnownedTomeNotCountedAsPossessed(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Comptage possession');

        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $ownedId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
            'support_physique' => BdPhysicalSupport::ALBUM,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($ownedId);

        $unownedId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 2,
            'support_physique' => '',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($unownedId);

        $this->assertSame(1, $repo->countPossessedTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION));
        $this->assertSame(2, $repo->countCatalogTomesForSeries($seriesId));
        $this->assertSame(1, $repo->countTomesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION));

        $seriesList = $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $seriesList);
        $this->assertSame(1, (int) $seriesList[0]['possessed_tome_count']);
        $this->assertSame(1, (int) $seriesList[0]['tome_count']);
        $this->assertSame(2, (int) $seriesList[0]['catalog_tome_count']);
    }

    public function testHorsSerieTomeUsesDecimalOrdreAndFilter(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Hors-série Test');

        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $standardId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 38,
            'tome_ordre' => 38,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($standardId);

        $hsId = $repo->createTomeWithLibrary($seriesId, [
            'titre' => 'Astérix et Obélix : Mission Cléopâtre',
            'tome_numero' => 38,
            'tome_ordre' => 38,
            'est_hors_serie' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($hsId);

        $hsTome = $repo->findByBibId($hsId, $userId, $foyerId);
        $this->assertNotNull($hsTome);
        $this->assertTrue($hsTome['est_hors_serie']);
        $this->assertSame(38.5, (float) ($hsTome['tome_ordre'] ?? 0));

        $ordered = $repo->listTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(2, $ordered);
        $this->assertSame($standardId, (int) $ordered[0]['id']);
        $this->assertSame($hsId, (int) $ordered[1]['id']);

        $hsOnly = $repo->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'tome',
            'asc',
            '',
            BdRepository::FILTER_HORS_SERIE
        );
        $this->assertCount(1, $hsOnly);
        $this->assertSame($hsId, (int) $hsOnly[0]['id']);
        $this->assertStringContainsString('Mission Cléopâtre', (string) $hsOnly[0]['titre']);
    }

    public function testResolveTomeOrdreAddsHalfForHorsSerie(): void
    {
        $ordre = BdRepository::resolveTomeOrdre([
            'tome_numero' => 5,
            'tome_ordre' => 5,
            'est_hors_serie' => true,
        ], 1);

        $this->assertSame(5.5, $ordre);
    }

    public function testCreateTomeZeroSortsBeforeTomeOne(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $seriesId = $this->createTestSeries('Tome zéro Test');

        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $tome1Id = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 1,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($tome1Id);

        $tome0Id = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 0,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($tome0Id);

        $tome0 = $repo->findByBibId($tome0Id, $userId, $foyerId);
        $this->assertNotNull($tome0);
        $this->assertSame(0, (int) ($tome0['tome_numero'] ?? -1));
        $this->assertSame(0.0, (float) ($tome0['tome_ordre'] ?? -1));
        $this->assertStringContainsString('Tome 0', (string) ($tome0['display_titre'] ?? ''));

        $ordered = $repo->listTomesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(2, $ordered);
        $this->assertSame($tome0Id, (int) $ordered[0]['id']);
        $this->assertSame($tome1Id, (int) $ordered[1]['id']);

        $duplicateError = $repo->validateTomeNumeroForSeries($seriesId, 0, false);
        $this->assertSame('Un autre tome avec ce numéro existe déjà pour cette série.', $duplicateError);
    }
}
