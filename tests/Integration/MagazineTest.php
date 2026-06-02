<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MagazineSupport;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MagazineTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testCreateSeriesAndIssue(): void
    {
        $this->assertTrue(MagazineRepository::isAvailable());

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'PC Jeux Test',
            'publication_type' => PublicationType::MENSUEL,
            'editeur' => 'Éditeur Test',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();

        $bibId = $repo->createIssueWithLibrary($seriesId, [
            'numero' => '42',
            'numero_ordre' => 42,
            'date_parution' => '2024-06-01',
            'sommaire' => "Dossier : jeux indépendants\nTest matériel",
            'pages' => 100,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $seriesList = $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $seriesList);
        $this->assertSame('PC Jeux Test', $seriesList[0]['titre']);

        $issues = $repo->listIssuesForSeries($seriesId, $userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $issues);
        $this->assertSame('42', $issues[0]['numero']);
        $this->assertStringContainsString('jeux indépendants', (string) $issues[0]['sommaire']);

        $issue = $repo->findIssueByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($issue);
        $this->assertSame('juin 2024', PublicationType::formatParutionDate(
            (string) $issue['date_parution'],
            (string) $issue['publication_type']
        ));
    }

    public function testCreateIssueWithPaperSupportTag(): void
    {
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Support Papier Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();

        $bibId = $repo->createIssueWithLibrary($seriesId, [
            'numero' => '7',
            'numero_ordre' => 7,
            'support_papier' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $issue = $repo->findIssueByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($issue);
        $this->assertSame('papier', (string) ($issue['support_physique'] ?? ''));
        $this->assertContains(MagazineSupport::TAG_PAPIER, MagazineSupport::tagsForIssue($issue));
    }

    public function testGlobalSearchInSeries(): void
    {
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Revue Recherche Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();

        $repo->createIssueWithLibrary($seriesId, [
            'numero' => '10',
            'numero_ordre' => 10,
            'date_parution' => '2023-03-01',
            'sommaire' => 'Article Warhammer',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $repo->createIssueWithLibrary($seriesId, [
            'numero' => '20',
            'numero_ordre' => 20,
            'date_parution' => '2024-06-01',
            'sommaire' => 'Test matériel',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $byNumero = $repo->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'numero_ordre',
            'desc',
            '20'
        );
        $this->assertCount(1, $byNumero);
        $this->assertSame('20', $byNumero[0]['numero']);

        $byDate = $repo->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'numero_ordre',
            'desc',
            'juin 2024'
        );
        $this->assertCount(1, $byDate);
        $this->assertSame('20', $byDate[0]['numero']);

        $byKeyword = $repo->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'numero_ordre',
            'desc',
            'Warhammer'
        );
        $this->assertCount(1, $byKeyword);
        $this->assertSame('10', $byKeyword[0]['numero']);

        if (MagazineRepository::pdfTextPreviewColumnExists()) {
            $db = \Moncine\Database::getInstance();
            $db->prepare(
                'UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE numero = ? AND series_id = ?'
            )->execute(['Dossier exclusif Zelda sur Switch', '20', $seriesId]);

            $byPdf = $repo->listIssuesForSeries(
                $seriesId,
                $userId,
                $foyerId,
                LibraryStatut::COLLECTION,
                'numero_ordre',
                'desc',
                'Zelda'
            );
            $this->assertCount(1, $byPdf);
            $this->assertSame('20', $byPdf[0]['numero']);
        }
    }

    public function testEmptySeriesAppearsInCollection(): void
    {
        $this->assertTrue(MagazineRepository::isAvailable());

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Canard PC Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();
        $this->assertTrue($repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId));

        $seriesList = $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $seriesList);
        $this->assertSame('Canard PC Test', $seriesList[0]['titre']);
        $this->assertSame(0, (int) ($seriesList[0]['issue_count'] ?? -1));
    }

    public function testFilmCollectionExcludesMagazineIssues(): void
    {
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Joystick Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        (new MagazineRepository())->createIssueWithLibrary($seriesId, [
            'numero' => '1',
            'numero_ordre' => 1,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        MediaContext::set(MediaDomain::FILM);
        $films = (new \Moncine\FilmRepository())->findAll();
        foreach ($films as $film) {
            $this->assertStringNotContainsString('Joystick', (string) ($film['titre'] ?? ''));
        }
    }
}
