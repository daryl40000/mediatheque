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
}
