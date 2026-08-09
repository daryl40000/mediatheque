<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazinePeriodStats;
use Moncine\MagazineRatingScale;
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

final class MagazinePeriodStatsTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testNormalizePeriodSwapsInvertedYears(): void
    {
        $stats = new MagazinePeriodStats();
        $this->assertNull($stats->normalizePeriod(null, null));
        $this->assertSame(['from' => 1996, 'to' => 1996], $stats->normalizePeriod(1996, null));
        $this->assertSame(['from' => 1990, 'to' => 1995], $stats->normalizePeriod(1995, 1990));
    }

    public function testPeriodRanksGamesAndSeries(): void
    {
        if (!MagazinePeriodStats::isAvailable() || !GameRepository::isAvailable()) {
            $this->markTestSkipped('Stats période magazines indisponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        MediaContext::set(MediaDomain::JEU);
        $gameRepo = new GameRepository();
        $bibA = $gameRepo->createWithLibrary([
            'titre' => 'Jeu Stats Periode A',
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $bibB = $gameRepo->createWithLibrary([
            'titre' => 'Jeu Stats Periode B',
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibA);
        $this->assertIsInt($bibB);
        $gameA = $gameRepo->findByBibId($bibA, $userId, $foyerId);
        $gameB = $gameRepo->findByBibId($bibB, $userId, $foyerId);
        $this->assertNotNull($gameA);
        $this->assertNotNull($gameB);
        $oeuvreA = (int) $gameA['oeuvre_id'];
        $oeuvreB = (int) $gameB['oeuvre_id'];

        MediaContext::set(MediaDomain::MAGAZINE);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Revue Stats Periode',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'Jeux vidéo',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $magRepo = new MagazineRepository();
        $issueBib = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '1',
            'numero_ordre' => 1,
            'date_parution' => '1996-03-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($issueBib);
        $issue = $magRepo->findIssueByBibId($issueBib, $userId, $foyerId);
        $this->assertNotNull($issue);
        $issueOeuvreId = (int) $issue['oeuvre_id'];

        $subjectRepo = new MagazineSubjectRepository();
        $seriesRow = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesRow);

        // Deux tests pour le jeu A, un preview pour le jeu B.
        foreach (
            [
                [MagazineSubject::TEST, 'Jeu Stats Periode A', $oeuvreA],
                [MagazineSubject::TEST, 'Jeu Stats Periode A bis', $oeuvreA],
                [MagazineSubject::PREVIEW, 'Jeu Stats Periode B', $oeuvreB],
            ] as [$category, $label, $catalogId]
        ) {
            $prepared = $subjectRepo->prepareSubjectForIssue(
                $category,
                $label,
                'PC',
                $seriesRow,
                $issue,
                1996
            );
            $this->assertIsArray($prepared);
            $subject = $subjectRepo->findOrCreate(
                (string) $prepared['category'],
                (string) $prepared['label'],
                (string) $prepared['detail'],
                (int) $prepared['parution_year']
            );
            $this->assertNotNull($subject);
            $subjectId = (int) ($subject['id'] ?? 0);
            $this->assertTrue($subjectRepo->attachToOeuvre($issueOeuvreId, $subjectId) === true);
            $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogId));
        }

        $dashboard = (new MagazinePeriodStats())->getPeriodDashboard(1996, 1996);
        $this->assertTrue($dashboard['active']);
        $this->assertNotEmpty($dashboard['games_most']);
        $this->assertSame($oeuvreA, (int) ($dashboard['games_most'][0]['oeuvre_id'] ?? 0));
        $this->assertSame(2, (int) ($dashboard['games_most'][0]['subject_count'] ?? 0));

        $this->assertNotEmpty($dashboard['series_most_tests']);
        $this->assertSame($seriesId, (int) ($dashboard['series_most_tests'][0]['series_id'] ?? 0));
        $this->assertGreaterThanOrEqual(2, (int) ($dashboard['series_most_tests'][0]['subject_count'] ?? 0));

        $this->assertNotEmpty($dashboard['series_most_previews']);
        $this->assertSame($seriesId, (int) ($dashboard['series_most_previews'][0]['series_id'] ?? 0));
    }

    public function testSameSubjectOnThreeIssuesCountsAsThreeMentions(): void
    {
        if (!MagazinePeriodStats::isAvailable() || !GameRepository::isAvailable()) {
            $this->markTestSkipped('Stats période magazines indisponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        MediaContext::set(MediaDomain::JEU);
        $gameRepo = new GameRepository();
        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Jeu Mentions Multiples',
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $game = $gameRepo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $gameOeuvreId = (int) $game['oeuvre_id'];

        MediaContext::set(MediaDomain::MAGAZINE);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Revue Mentions Multiples',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'Jeux vidéo',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);
        $seriesRow = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesRow);

        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        // Un seul sujet réutilisé sur 3 numéros (dates FR + ISO).
        $prepared = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::TEST,
            'Jeu Mentions Multiples',
            'PC',
            $seriesRow,
            ['date_parution' => '1998-01-01'],
            1998
        );
        $this->assertIsArray($prepared);
        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectId = (int) ($subject['id'] ?? 0);
        $this->assertSame(true, (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $gameOeuvreId));

        $dates = ['janvier 1998', '1998-02-01', 'mars 1998'];
        foreach ($dates as $index => $dateParution) {
            $issueBib = $magRepo->createIssueWithLibrary($seriesId, [
                'numero' => (string) ($index + 1),
                'numero_ordre' => $index + 1,
                'date_parution' => $dateParution,
            ], LibraryStatut::COLLECTION, $userId, $foyerId);
            $this->assertIsInt($issueBib);
            $issue = $magRepo->findIssueByBibId($issueBib, $userId, $foyerId);
            $this->assertNotNull($issue);
            $this->assertTrue(
                $subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true
            );
        }

        $stats = new MagazinePeriodStats();
        $issueIds = $stats->issueOeuvreIdsInPeriod(1998, 1998);
        $this->assertCount(3, $issueIds);

        $dashboard = $stats->getPeriodDashboard(1998, 1998);
        $this->assertTrue($dashboard['active']);
        $this->assertNotEmpty($dashboard['games_most']);
        $this->assertSame($gameOeuvreId, (int) ($dashboard['games_most'][0]['oeuvre_id'] ?? 0));
        // 1 sujet × 3 numéros = 3 mentions (pas 1).
        $this->assertSame(3, (int) ($dashboard['games_most'][0]['subject_count'] ?? 0));
    }

    public function testMonthCategoryListsTestsPreviewsDossiers(): void
    {
        if (!MagazinePeriodStats::isAvailable()) {
            $this->markTestSkipped('Stats période magazines indisponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        MediaContext::set(MediaDomain::MAGAZINE);

        $seriesJeux = (new SeriesRepository())->create([
            'titre' => 'Revue Mois Jeux',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'Jeux vidéo',
        ], MediaDomain::MAGAZINE);
        $seriesCinema = (new SeriesRepository())->create([
            'titre' => 'Revue Mois Cinema',
            'publication_type' => PublicationType::MENSUEL,
            'categories' => 'Cinéma',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesJeux);
        $this->assertIsInt($seriesCinema);

        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        $issueJeuxBib = $magRepo->createIssueWithLibrary($seriesJeux, [
            'numero' => '10',
            'numero_ordre' => 10,
            'date_parution' => '2020-06-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issueCinemaBib = $magRepo->createIssueWithLibrary($seriesCinema, [
            'numero' => '5',
            'numero_ordre' => 5,
            'date_parution' => 'juin 2020',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $issueOtherMonthBib = $magRepo->createIssueWithLibrary($seriesJeux, [
            'numero' => '11',
            'numero_ordre' => 11,
            'date_parution' => '2020-07-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($issueJeuxBib);
        $this->assertIsInt($issueCinemaBib);
        $this->assertIsInt($issueOtherMonthBib);

        $issueJeux = $magRepo->findIssueByBibId($issueJeuxBib, $userId, $foyerId);
        $issueCinema = $magRepo->findIssueByBibId($issueCinemaBib, $userId, $foyerId);
        $issueOtherMonth = $magRepo->findIssueByBibId($issueOtherMonthBib, $userId, $foyerId);
        $this->assertNotNull($issueJeux);
        $this->assertNotNull($issueCinema);
        $this->assertNotNull($issueOtherMonth);

        $test = $subjectRepo->findOrCreate(MagazineSubject::TEST, 'Jeu Test Juin', 'PC', 2020);
        $preview = $subjectRepo->findOrCreate(MagazineSubject::PREVIEW, 'Jeu Preview Juin', 'PC', 2020);
        $dossier = $subjectRepo->findOrCreate(MagazineSubject::DOSSIER, 'Dossier Juin', '', 2020);
        $cinemaTest = $subjectRepo->findOrCreate(MagazineSubject::TEST, 'Film Test Juin', '', 2020);
        $julyTest = $subjectRepo->findOrCreate(MagazineSubject::TEST, 'Jeu Test Juillet', 'PC', 2020);
        $this->assertNotNull($test);
        $this->assertNotNull($preview);
        $this->assertNotNull($dossier);
        $this->assertNotNull($cinemaTest);
        $this->assertNotNull($julyTest);

        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueJeux['oeuvre_id'], (int) $test['id']) === true);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueJeux['oeuvre_id'], (int) $preview['id']) === true);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueJeux['oeuvre_id'], (int) $dossier['id']) === true);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueCinema['oeuvre_id'], (int) $cinemaTest['id']) === true);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issueOtherMonth['oeuvre_id'], (int) $julyTest['id']) === true);

        $stats = new MagazinePeriodStats();
        $result = $stats->getMonthCategorySubjects(2020, 6, 'jeux video');
        $this->assertTrue($result['active']);
        $this->assertSame(3, $result['total']);
        $this->assertSame('Jeux vidéo', $result['series_category_label']);
        $this->assertSame('Juin', $result['month_label']);
        $this->assertCount(3, $result['groups']);

        $labels = [];
        foreach ($result['groups'] as $group) {
            foreach ($group['subjects'] as $subject) {
                $labels[] = (string) ($subject['label'] ?? '');
            }
        }
        sort($labels);
        $this->assertSame(['Dossier Juin', 'Jeu Preview Juin', 'Jeu Test Juin'], $labels);
        $this->assertStringContainsString('Revue Mois Jeux', (string) ($result['groups'][0]['subjects'][0]['issue_label'] ?? ''));
    }

    public function testSortSubjectsByScorePercentRespectsDifferentScales(): void
    {
        $subjects = [
            [
                'label' => 'Sur dix moyen',
                'score' => 8.0,
                'rating_scale' => '10',
                'score_percent' => MagazineRatingScale::toPercent(8.0, '10'),
            ],
            [
                'label' => 'Sur cent fort',
                'score' => 90.0,
                'rating_scale' => '100',
                'score_percent' => MagazineRatingScale::toPercent(90.0, '100'),
            ],
            [
                'label' => 'Sans note',
                'score' => null,
                'rating_scale' => '10',
                'score_percent' => null,
            ],
            [
                'label' => 'Sur vingt fort',
                'score' => 18.0,
                'rating_scale' => '20',
                'score_percent' => MagazineRatingScale::toPercent(18.0, '20'),
            ],
        ];

        $sorted = MagazinePeriodStats::sortSubjectsByScorePercentDesc($subjects);
        $labels = array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            $sorted
        );

        // 90/100 = 90 %, 18/20 = 90 %, 8/10 = 80 %, puis sans note.
        // À 90 % égal : alphabétique entre « Sur cent fort » et « Sur vingt fort ».
        $this->assertSame(
            ['Sur cent fort', 'Sur vingt fort', 'Sur dix moyen', 'Sans note'],
            $labels
        );

        $alpha = MagazinePeriodStats::sortSubjectsAlphabetically($subjects);
        $alphaLabels = array_map(
            static fn (array $row): string => (string) ($row['label'] ?? ''),
            $alpha
        );
        $this->assertSame(
            ['Sans note', 'Sur cent fort', 'Sur dix moyen', 'Sur vingt fort'],
            $alphaLabels
        );
    }
}
