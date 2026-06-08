<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
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

        $previewResults = $repo->searchCatalog('Legacy', MagazineSubject::PREVIEW, 50);
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
}
