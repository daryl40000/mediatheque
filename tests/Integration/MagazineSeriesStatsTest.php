<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MagazineSeriesStats;
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

final class MagazineSeriesStatsTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testExtractYearFromIsoAndFrenchLabels(): void
    {
        $this->assertSame(2024, MagazineSeriesStats::extractYear('2024-06-01'));
        $this->assertSame(2018, MagazineSeriesStats::extractYear('mars 2018'));
        $this->assertNull(MagazineSeriesStats::extractYear(''));
        $this->assertNull(MagazineSeriesStats::extractYear('sans date'));
    }

    public function testMagazineSeriesStatsUrl(): void
    {
        $this->assertSame(
            '/stats-serie-magazine.php?series_id=12',
            View::magazineSeriesStatsUrl(12)
        );
        $this->assertSame(
            '/stats-serie-magazine.php?series_id=12&statut=wishlist',
            View::magazineSeriesStatsUrl(12, LibraryStatut::WISHLIST)
        );
    }

    public function testDashboardPagesAndSubjectsByYear(): void
    {
        $this->assertTrue(MagazineSeriesStats::isAvailable());
        $this->assertTrue(MagazineSubjectRepository::isAvailable());

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $seriesRepo = new SeriesRepository();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        $seriesId = $seriesRepo->create([
            'titre' => 'Stats Revue Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $bib2020a = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '1',
            'numero_ordre' => 1,
            'date_parution' => '2020-01-01',
            'pages' => 100,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $bib2020b = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '2',
            'numero_ordre' => 2,
            'date_parution' => '2020-06-01',
            'pages' => 140,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $bib2021 = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '3',
            'numero_ordre' => 3,
            'date_parution' => '2021-03-01',
            'pages' => 80,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        // Numéro sans pages : ne doit pas fausser la moyenne
        $bibSansPages = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '4',
            'numero_ordre' => 4,
            'date_parution' => '2021-09-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $this->assertIsInt($bib2020a);
        $this->assertIsInt($bib2020b);
        $this->assertIsInt($bib2021);
        $this->assertIsInt($bibSansPages);

        $issue2020a = $magRepo->findIssueByBibId($bib2020a, $userId, $foyerId);
        $issue2020b = $magRepo->findIssueByBibId($bib2020b, $userId, $foyerId);
        $issue2021 = $magRepo->findIssueByBibId($bib2021, $userId, $foyerId);
        $this->assertNotNull($issue2020a);
        $this->assertNotNull($issue2020b);
        $this->assertNotNull($issue2021);

        $series = $seriesRepo->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($series);

        $this->attachSubject($subjectRepo, $series, $issue2020a, MagazineSubject::TEST, 'Jeu A', 2020);
        $this->attachSubject($subjectRepo, $series, $issue2020a, MagazineSubject::PREVIEW, 'Jeu B', 2020);
        $this->attachSubject($subjectRepo, $series, $issue2020b, MagazineSubject::TEST, 'Jeu C', 2020);
        $this->attachSubject($subjectRepo, $series, $issue2021, MagazineSubject::SOLUCE, 'Jeu D', 2021);

        $dashboard = (new MagazineSeriesStats())->getDashboard($seriesId);
        $summary = $dashboard['summary'];

        $this->assertSame(4, $summary['issue_count']);
        $this->assertSame(3, $summary['issues_with_pages']);
        $this->assertSame(1, $summary['issues_without_pages']);
        $this->assertSame(320, $summary['pages_total']);
        $this->assertSame(106.7, $summary['pages_avg']);
        $this->assertSame(80, $summary['pages_min']);
        $this->assertSame(140, $summary['pages_max']);
        $this->assertSame(4, $summary['subject_link_count']);
        $this->assertSame(3, $summary['issues_with_subjects']);
        $this->assertSame(1, $summary['issues_without_subjects']);

        $pagesByYear = $dashboard['pages_by_year'];
        $this->assertCount(2, $pagesByYear);
        $this->assertSame(2020, $pagesByYear[0]['year']);
        $this->assertSame(120.0, $pagesByYear[0]['avg_pages']);
        $this->assertSame(2, $pagesByYear[0]['issue_count']);
        $this->assertSame(2021, $pagesByYear[1]['year']);
        $this->assertSame(80.0, $pagesByYear[1]['avg_pages']);

        // Moyenne par numéro et par année (uniquement les numéros qui ont des sujets) :
        // 2020 : 2 numéros avec sujets → Test 2/2=1, Preview 1/2=0.5
        // 2021 : 1 numéro avec sujets (le n°4 sans sujet est ignoré) → Soluce 1/1=1
        $avgByYear = $dashboard['subjects_avg_by_year'];
        $this->assertCount(2, $avgByYear);
        $this->assertSame(2020, $avgByYear[0]['year']);
        $this->assertSame(2, $avgByYear[0]['issue_count']);
        $this->assertSame(1.0, $avgByYear[0]['categories'][MagazineSubject::TEST]);
        $this->assertSame(0.5, $avgByYear[0]['categories'][MagazineSubject::PREVIEW]);
        $this->assertSame(2021, $avgByYear[1]['year']);
        $this->assertSame(1, $avgByYear[1]['issue_count']);
        $this->assertSame(1.0, $avgByYear[1]['categories'][MagazineSubject::SOLUCE]);

        // Évolution numéro par numéro (seulement ceux avec sujets)
        $byIssue = $dashboard['subjects_by_issue'];
        $this->assertCount(3, $byIssue);
        $this->assertSame('n°1', $byIssue[0]['numero_label']);
        $this->assertSame(1, $byIssue[0]['categories'][MagazineSubject::TEST]);
        $this->assertSame(1, $byIssue[0]['categories'][MagazineSubject::PREVIEW]);
        $this->assertSame('n°2', $byIssue[1]['numero_label']);
        $this->assertSame(1, $byIssue[1]['categories'][MagazineSubject::TEST]);
        $this->assertSame('n°3', $byIssue[2]['numero_label']);
        $this->assertSame(1, $byIssue[2]['categories'][MagazineSubject::SOLUCE]);
    }

    /**
     * @param array<string, mixed> $series
     * @param array<string, mixed> $issue
     */
    private function attachSubject(
        MagazineSubjectRepository $subjectRepo,
        array $series,
        array $issue,
        string $category,
        string $label,
        int $year
    ): void {
        $prepared = $subjectRepo->prepareSubjectForIssue(
            $category,
            $label,
            '',
            $series,
            $issue,
            $year
        );
        $this->assertIsArray($prepared);
        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], (int) $subject['id']);
    }
}
