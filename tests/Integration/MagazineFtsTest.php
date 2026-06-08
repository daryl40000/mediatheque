<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\MagazineIssueFts;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectFts;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class MagazineFtsTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testIssueAndSubjectFtsSearch(): void
    {
        $this->assertTrue(MagazineIssueFts::isAvailable());
        $this->assertTrue(MagazineSubjectFts::isAvailable());

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'FTS Revue Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $bibA = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '42',
            'numero_ordre' => 42,
            'sommaire' => 'Dossier exclusif Warhammer 40K',
            'date_parution' => '2024-06-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibA);

        $db = \Moncine\Database::getInstance();
        $db->prepare(
            'UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE numero = ? AND series_id = ?'
        )->execute(['Preview PDF contenant Zelda sur Switch', '42', $seriesId]);

        $foundIssues = $magRepo->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'numero_ordre',
            'desc',
            'Zelda'
        );
        $this->assertCount(1, $foundIssues);
        $this->assertSame('42', $foundIssues[0]['numero']);

        $subject = $subjectRepo->findOrCreate(
            MagazineSubject::TEST,
            'Gran Turismo 7',
            'PS5',
            2024
        );
        $this->assertNotNull($subject);

        $foundSubjects = $subjectRepo->searchCatalog('Gran Turismo', MagazineSubject::TEST, 10);
        $this->assertNotEmpty($foundSubjects);
        $this->assertSame('Gran Turismo 7', (string) ($foundSubjects[0]['label'] ?? ''));
    }

    public function testGlobalLibrarySearchAcrossSeriesSubjectsAndIssues(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Global Search Revue',
            'publication_type' => PublicationType::MENSUEL,
            'tags' => 'PC',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $bibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '99',
            'numero_ordre' => 99,
            'sommaire' => 'Comparatif cartes graphiques RTX',
            'date_parution' => '2024-08-01',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $issue = $magRepo->findIssueByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($issue);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($series);

        $prepared = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::COMPARATIF,
            'RTX 4080',
            '',
            $series,
            $issue,
            2024
        );
        $this->assertIsArray($prepared);
        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], (int) ($subject['id'] ?? 0));

        $seriesList = $magRepo->listSeriesInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            'RTX'
        );
        $this->assertNotEmpty($seriesList);

        $issues = $magRepo->searchIssuesInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'Comparatif cartes',
            10
        );
        $this->assertCount(1, $issues);
        $this->assertSame('99', $issues[0]['numero']);

        $subjects = $subjectRepo->searchCatalog('RTX 4080', null, 10);
        $this->assertNotEmpty($subjects);
    }
}
