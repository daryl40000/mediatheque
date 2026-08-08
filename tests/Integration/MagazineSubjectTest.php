<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectCatalogLink;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\View;

final class MagazineSubjectTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testAttachSubjectWithIssueYearAndSeriesTags(): void
    {
        $this->assertTrue(MagazineSubjectRepository::isAvailable());

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();
        $seriesRepo = new SeriesRepository();

        $seriesA = $seriesRepo->create([
            'titre' => 'PC Jeux Tags',
            'publication_type' => PublicationType::MENSUEL,
            'tags' => 'PC',
        ], MediaDomain::MAGAZINE);
        $seriesB = $seriesRepo->create([
            'titre' => 'Joystick Tags',
            'publication_type' => PublicationType::MENSUEL,
            'tags' => 'PS5, PS4',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesA);
        $this->assertIsInt($seriesB);

        $bibA = $magRepo->createIssueWithLibrary($seriesA, [
            'numero' => '10',
            'numero_ordre' => 10,
            'date_parution' => '2024-03-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $bibB = $magRepo->createIssueWithLibrary($seriesB, [
            'numero' => '20',
            'numero_ordre' => 20,
            'date_parution' => '2024-04-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibA);
        $this->assertIsInt($bibB);

        $issueA = $magRepo->findIssueByBibId($bibA, $userId, $foyerId);
        $issueB = $magRepo->findIssueByBibId($bibB, $userId, $foyerId);
        $this->assertNotNull($issueA);
        $this->assertNotNull($issueB);

        $seriesAData = $seriesRepo->findById($seriesA, MediaDomain::MAGAZINE);
        $seriesBData = $seriesRepo->findById($seriesB, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesAData);
        $this->assertNotNull($seriesBData);

        $prepared = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::DOSSIER,
            'Les jeux indépendants',
            '',
            $seriesAData,
            $issueA,
            2024
        );
        $this->assertIsArray($prepared);
        $this->assertSame('PC', $prepared['detail']);
        $this->assertSame(2024, $prepared['parution_year']);

        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $this->assertSame(
            'Les jeux indépendants (PC · 2024)',
            (string) ($subject['display_label'] ?? '')
        );

        $subjectId = (int) ($subject['id'] ?? 0);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueA['oeuvre_id'], $subjectId) === true);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueB['oeuvre_id'], $subjectId) === true);

        $stats = $subjectRepo->countInLibrary($subjectId, $userId, $foyerId);
        $this->assertSame(2, $stats['issue_count']);
        $this->assertSame(2, $stats['series_count']);

        $needsTag = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::TEST,
            'Gran Turismo 7',
            '',
            $seriesBData,
            $issueB,
            2024
        );
        $this->assertIsString($needsTag);

        $preparedPs4 = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::PREVIEW,
            'Gran Turismo 7',
            'PS4',
            $seriesBData,
            $issueB,
            2024
        );
        $this->assertIsArray($preparedPs4);

        $distinctVersion = $subjectRepo->findOrCreate(
            (string) $preparedPs4['category'],
            (string) $preparedPs4['label'],
            (string) $preparedPs4['detail'],
            (int) $preparedPs4['parution_year']
        );
        $this->assertNotNull($distinctVersion);
        $this->assertNotSame($subjectId, (int) ($distinctVersion['id'] ?? 0));
        $this->assertSame(
            'Gran Turismo 7 (PS4 · 2024)',
            (string) ($distinctVersion['display_label'] ?? '')
        );

        $this->assertSame(
            '/magazine-sujet.php?id=' . $subjectId,
            View::magazineSubjectUrl($subjectId)
        );
    }

    public function testSearchCatalogMergesLegacyTestCategories(): void
    {
        $db = \Moncine\Database::getInstance();
        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute(['test_jeu', 'Sujet Jeu Legacy', 'PC', 2023]);
        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute(['test_voiture', 'Sujet Auto Legacy', 'Diesel', 2023]);
        $db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        )->execute(['preview', 'Sujet Preview Seul', '', 2023]);

        $repo = new MagazineSubjectRepository();
        $testResults = $repo->searchCatalog('Legacy', MagazineSubject::TEST, 50);
        $testLabels = array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            $testResults
        );

        $this->assertContains('Sujet Jeu Legacy', $testLabels);
        $this->assertContains('Sujet Auto Legacy', $testLabels);
        $this->assertNotContains('Sujet Preview Seul', $testLabels);

        $previewResults = $repo->searchCatalog('Preview Seul', MagazineSubject::PREVIEW, 50);
        $previewLabels = array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            $previewResults
        );
        $this->assertContains('Sujet Preview Seul', $previewLabels);
        $this->assertNotContains('Sujet Jeu Legacy', $previewLabels);
    }

    public function testPrepareSubjectUsesSelectedYearNotIssueDate(): void
    {
        $subjectRepo = new MagazineSubjectRepository();
        $prepared = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::TEST,
            'Jeu rétro',
            '',
            ['tags' => 'PC'],
            ['date_parution' => '2024-06-01'],
            2020
        );
        $this->assertIsArray($prepared);
        $this->assertSame(2020, $prepared['parution_year']);

        $missingYear = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::TEST,
            'Sans année',
            '',
            ['tags' => 'PC'],
            ['date_parution' => '2024-06-01'],
            0
        );
        $this->assertIsString($missingYear);
    }

    public function testFindOrCreateReusesSimilarLabelSpelling(): void
    {
        $repo = new MagazineSubjectRepository();
        $first = $repo->findOrCreate(MagazineSubject::TEST, 'After Life', 'PC', 2024);
        $this->assertNotNull($first);

        $second = $repo->findOrCreate(MagazineSubject::TEST, 'Afterlife', 'PC', 2024);
        $this->assertNotNull($second);
        $this->assertSame((int) ($first['id'] ?? 0), (int) ($second['id'] ?? 0));
        $this->assertSame('After Life', (string) ($second['label'] ?? ''));
    }

    public function testPrepareSubjectWithCatalogGameLink(): void
    {
        if (!MagazineGameLink::isAvailable() || !GameRepository::isAvailable()) {
            $this->markTestSkipped('Pont magazine ↔ jeu non disponible.');
        }

        MediaContext::set(MediaDomain::JEU);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Catalog Link Test Game',
            'annee' => 2023,
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
            'titre' => 'Pont Jeu Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);
        $issueBibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '77',
            'numero_ordre' => 77,
            'date_parution' => '2024-05-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issue = $magRepo->findIssueByBibId((int) $issueBibId, $userId, $foyerId);
        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($issue);
        $this->assertNotNull($series);

        $prepared = $subjectRepo->prepareSubjectForIssueWithCatalog(
            MagazineSubject::TEST,
            'Saisie libre',
            '',
            $series,
            $issue,
            2024,
            $catalogOeuvreId
        );
        $this->assertIsArray($prepared);
        $this->assertSame('Catalog Link Test Game', $prepared['label']);
        $this->assertSame('PS5', $prepared['detail']);
        $this->assertSame($catalogOeuvreId, (int) ($prepared['catalog_oeuvre_id'] ?? 0));

        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectId = (int) ($subject['id'] ?? 0);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true);
        $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogOeuvreId));

        $linked = $subjectRepo->findById($subjectId);
        $this->assertNotNull($linked);
        $this->assertSame($catalogOeuvreId, (int) ($linked['catalog_oeuvre_id'] ?? 0));

        MediaContext::set(MediaDomain::JEU);
        $coverage = (new MagazineGameLink())->listMagazineCoverageForGame($catalogOeuvreId, $userId, $foyerId);
        $this->assertCount(1, $coverage);
    }

    public function testEnsureMagazineContextOnPostSwitchesWithoutRedirect(): void
    {
        MediaContext::set(MediaDomain::FILM);
        $previous = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Ne doit pas exit/rediriger : sinon le test s’interrompt.
        MediaDomainGuards::ensureMagazineContext();
        $this->assertSame(MediaDomain::MAGAZINE, MediaContext::current());

        if ($previous === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $previous;
        }
    }

    public function testDossierAndSoluceSupportCatalogLink(): void
    {
        if (!MagazineSubjectCatalogLink::isAvailable()) {
            $this->markTestSkipped('Pont magazine ↔ catalogue non disponible.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Dossier Link Game',
            'annee' => 2022,
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $game = $gameRepo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $catalogOeuvreId = (int) ($game['oeuvre_id'] ?? 0);

        MediaContext::set(MediaDomain::MAGAZINE);
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Dossier Soluce Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);
        $issueBibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '88',
            'numero_ordre' => 88,
            'date_parution' => '2024-06-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issue = $magRepo->findIssueByBibId((int) $issueBibId, $userId, $foyerId);
        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($issue);
        $this->assertNotNull($series);

        foreach ([MagazineSubject::DOSSIER, MagazineSubject::SOLUCE] as $category) {
            $prepared = $subjectRepo->prepareSubjectForIssueWithCatalog(
                $category,
                'Saisie libre',
                '',
                $series,
                $issue,
                2024,
                $catalogOeuvreId
            );
            $this->assertIsArray($prepared, 'Catégorie ' . $category);
            $this->assertSame('Dossier Link Game', $prepared['label']);
            $this->assertSame($catalogOeuvreId, (int) ($prepared['catalog_oeuvre_id'] ?? 0));

            $subject = $subjectRepo->findOrCreate(
                (string) $prepared['category'],
                (string) $prepared['label'],
                (string) $prepared['detail'],
                (int) $prepared['parution_year']
            );
            $this->assertNotNull($subject);
            $subjectId = (int) ($subject['id'] ?? 0);
            $this->assertTrue($subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true);
            $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogOeuvreId));
        }
    }

    public function testSubjectLinkPageStoredAndUsedInGameMagazinePdfUrl(): void
    {
        if (!MagazineSubjectRepository::hasPageColumn()) {
            $this->markTestSkipped('Colonne page absente (migration 071).');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();
        $seriesRepo = new SeriesRepository();
        $gameRepo = new GameRepository();

        $seriesId = $seriesRepo->create([
            'titre' => 'Page Link Mag ' . uniqid('', true),
            'publication_type' => PublicationType::MENSUEL,
            'tags' => 'PC',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $bibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '99',
            'numero_ordre' => 99,
            'date_parution' => '2024-05-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $issue = $magRepo->findIssueByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($issue);
        $issueOeuvreId = (int) $issue['oeuvre_id'];

        // Simuler un PDF déjà importé.
        $db = \Moncine\Database::getInstance();
        $db->prepare(
            'INSERT INTO stored_objects (backend, relative_path, mime, size_bytes)
             VALUES (\'local\', ?, \'application/pdf\', 100)'
        )->execute(['magazines/test-page-link-' . uniqid('', true) . '.pdf']);
        $storedObjectId = (int) $db->lastInsertId();
        $this->assertGreaterThan(0, $storedObjectId);
        $db->prepare(
            'UPDATE oeuvre_magazine SET stored_object_id = ? WHERE oeuvre_id = ?'
        )->execute([$storedObjectId, $issueOeuvreId]);

        MediaContext::set(MediaDomain::JEU);
        $gameBibId = $gameRepo->createWithLibrary([
            'titre' => 'Page Link Game ' . uniqid('', true),
            'platform' => GamePlatform::PC,
            'annee' => 2024,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($gameBibId);
        $game = $gameRepo->findByBibId($gameBibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $catalogOeuvreId = (int) $game['oeuvre_id'];

        MediaContext::set(MediaDomain::MAGAZINE);
        $series = $seriesRepo->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($series);
        $prepared = $subjectRepo->prepareSubjectForIssueWithCatalog(
            MagazineSubject::TEST,
            (string) $game['titre'],
            '',
            $series,
            $issue,
            2024,
            $catalogOeuvreId
        );
        $this->assertIsArray($prepared);
        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectId = (int) $subject['id'];

        $this->assertTrue($subjectRepo->attachToOeuvre($issueOeuvreId, $subjectId, 42) === true);
        $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogOeuvreId));

        $linked = $subjectRepo->listForOeuvre($issueOeuvreId);
        $this->assertCount(1, $linked);
        $this->assertSame(42, (int) ($linked[0]['page'] ?? 0));

        $this->assertTrue($subjectRepo->updateLinkPage($issueOeuvreId, $subjectId, 55) === true);
        $linked = $subjectRepo->listForOeuvre($issueOeuvreId);
        $this->assertSame(55, (int) ($linked[0]['page'] ?? 0));

        $coverage = (new MagazineGameLink())->listIssueCoverageForGame($catalogOeuvreId, $userId, $foyerId);
        $this->assertNotEmpty($coverage);
        $match = null;
        foreach ($coverage as $row) {
            if ((int) ($row['issue_oeuvre_id'] ?? 0) === $issueOeuvreId) {
                $match = $row;
                break;
            }
        }
        $this->assertNotNull($match);
        $this->assertSame(55, (int) ($match['article_page'] ?? 0));
        $this->assertSame(
            '/media-object.php?id=' . $storedObjectId . '#page=55',
            (string) ($match['pdf_url'] ?? '')
        );
    }
}
