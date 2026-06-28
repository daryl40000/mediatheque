<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazineGameLinkMaintenance;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MagazineGameLinkMaintenanceTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testRetroactiveLinkAndGlobalSearchByCatalogTitle(): void
    {
        if (!MagazineGameLinkMaintenance::isAvailable()) {
            $this->markTestSkipped('Pont magazine ↔ jeu non disponible.');
        }

        MediaContext::set(MediaDomain::JEU);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Retro Link Unique Game',
            'annee' => 2021,
            'platform' => GamePlatform::PS5,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $game = $gameRepo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $catalogOeuvreId = (int) ($game['oeuvre_id'] ?? 0);

        MediaContext::set(MediaDomain::MAGAZINE);
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Retro Link Series',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $issueBibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '12',
            'numero_ordre' => 12,
            'date_parution' => '2021-03-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issue = $magRepo->findIssueByBibId((int) $issueBibId, $userId, $foyerId);
        $this->assertNotNull($issue);

        $subject = $subjectRepo->findOrCreate(
            MagazineSubject::TEST,
            'Libellé libre rétro',
            'PS5',
            2021
        );
        $this->assertNotNull($subject);
        $subjectId = (int) ($subject['id'] ?? 0);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true);

        $maintenance = new MagazineGameLinkMaintenance();
        $stats = $maintenance->dashboardStats();
        $this->assertGreaterThan(0, (int) ($stats['unlinked_count'] ?? 0));

        $unlinked = $maintenance->findUnlinkedSubjects('Libellé libre rétro', 20);
        $this->assertNotEmpty($unlinked);
        $this->assertSame($subjectId, (int) ($unlinked[0]['id'] ?? 0));

        $result = $maintenance->setSubjectCatalogLink($subjectId, $catalogOeuvreId, $userId);
        $this->assertSame(true, $result);

        $linked = $subjectRepo->findById($subjectId);
        $this->assertNotNull($linked);
        $this->assertSame($catalogOeuvreId, (int) ($linked['catalog_oeuvre_id'] ?? 0));

        $catalogCoverage = (new MagazineGameLink())->listCatalogSubjectCoverageForGame($catalogOeuvreId);
        $this->assertCount(1, $catalogCoverage);
        $this->assertSame($subjectId, (int) ($catalogCoverage[0]['subject_id'] ?? 0));

        $seriesMatches = $magRepo->listSeriesInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            'Retro Link Unique Game'
        );
        $this->assertCount(1, $seriesMatches);
        $this->assertSame('Retro Link Series', $seriesMatches[0]['titre']);

        $result = $maintenance->setSubjectCatalogLink($subjectId, null, $userId);
        $this->assertSame(true, $result);
        $linked = $subjectRepo->findById($subjectId);
        $this->assertNotNull($linked);
        $this->assertSame(0, (int) ($linked['catalog_oeuvre_id'] ?? 0));
    }
}
