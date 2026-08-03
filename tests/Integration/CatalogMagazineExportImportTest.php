<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogExportSchema;
use Moncine\CatalogMagazineSheets;
use Moncine\Database;
use Moncine\ImportRunner;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;

/**
 * Vérifie qu’un aller-retour catalogue conserve séries (rating_scale),
 * sujets, page PDF et note de test.
 */
final class CatalogMagazineExportImportTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::MAGAZINE);
        $this->loginAsAdmin();
    }

    public function testCsvImportRecreatesMagazineSeriesWithRatingScale(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '9201',
            'Titre' => 'Joystick n°100',
            'Réalisateur' => '',
            'Domaine média' => 'magazine',
            'Magazine — ID série' => '601',
            'Magazine — titre série' => 'Joystick Export',
            'Magazine — tags série' => 'PC',
            'Magazine — catégories série' => 'jeux',
            'Magazine — notes sur' => '20',
            'Magazine — lien série en ligne' => 'https://www.abandonware-magazines.org/affiche_mag.php?mag=601',
            'Magazine — numéro' => '100',
            'Magazine — ordre' => '100',
            'Magazine — date parution' => '2020-01-01',
            'Magazine — lien en ligne' => 'https://www.abandonware-magazines.org/affiche_mag.php?mag=601&num=100',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);
        $this->assertSame(1, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        $series = (new SeriesRepository())->findById(601, MediaDomain::MAGAZINE);
        $this->assertNotNull($series);
        $this->assertSame('Joystick Export', $series['titre']);
        $this->assertSame('20', (string) ($series['rating_scale'] ?? ''));
        $this->assertStringContainsString('PC', (string) ($series['tags'] ?? ''));
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=601',
            (string) ($series['external_url'] ?? '')
        );

        $catalog = (new MagazineRepository())->findCatalogIssueByOeuvreId(9201);
        $this->assertNotNull($catalog);
        $this->assertSame(601, (int) $catalog['series_id']);
        $this->assertSame('100', (string) $catalog['numero']);
        $this->assertSame(
            'https://www.abandonware-magazines.org/affiche_mag.php?mag=601&num=100',
            (string) ($catalog['external_url'] ?? '')
        );
    }

    public function testRoundTripSeriesSubjectsLinksPageAndScore(): void
    {
        if (!MagazineRepository::isAvailable() || !MagazineSubjectRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines / sujets indisponible.');
        }
        if (!MagazineSubjectRepository::hasPageColumn() || !MagazineSubjectRepository::hasScoreColumn()) {
            $this->markTestSkipped('Colonnes page/score indisponibles.');
        }

        $seriesRepo = new SeriesRepository();
        $magRepo = new MagazineRepository();
        $subjectRepo = new MagazineSubjectRepository();

        $seriesId = $seriesRepo->create([
            'titre' => 'PC Gamer RoundTrip',
            'publication_type' => PublicationType::MENSUEL,
            'tags' => 'PC',
            'categories' => 'jeux',
            'rating_scale' => '10',
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $oeuvreId = $magRepo->createCatalogIssue($seriesId, [
            'numero' => '42',
            'numero_ordre' => 42,
            'date_parution' => '2022-06-01',
            'series_titre' => 'PC Gamer RoundTrip',
        ]);
        $this->assertIsInt($oeuvreId);

        $prevDomain = MediaContext::current();
        MediaContext::set(MediaDomain::JEU);
        $gameOeuvreId = $this->seedCatalogOeuvre('Jeu Test Note', 'Studio', [
            'media_domain' => MediaDomain::JEU,
        ]);
        MediaContext::set($prevDomain);

        $db = Database::getInstance();
        $subject = $subjectRepo->findOrCreate(
            MagazineSubject::TEST,
            'Jeu Test Note',
            'PC',
            2022
        );
        $this->assertNotNull($subject);
        $subjectId = (int) $subject['id'];

        $db->prepare('UPDATE magazine_subject SET catalog_oeuvre_id = ? WHERE id = ?')
            ->execute([$gameOeuvreId, $subjectId]);

        $attached = $subjectRepo->attachToOeuvre($oeuvreId, $subjectId, 37);
        $this->assertTrue($attached === true, is_string($attached) ? $attached : 'attach failed');
        $scored = $subjectRepo->updateLinkScore($oeuvreId, $subjectId, 8.5);
        $this->assertTrue($scored === true, is_string($scored) ? $scored : 'score failed');

        // Export → tableaux (comme dans l’ODS).
        $seriesRows = CatalogMagazineSheets::buildSeriesRows();
        $subjectRows = CatalogMagazineSheets::buildSubjectRows();
        $linkRows = CatalogMagazineSheets::buildSubjectLinkRows();
        $catalogHeaders = CatalogExportSchema::headers();
        $catalogData = [];
        foreach ((new OeuvreRepository())->findAllForExport() as $oeuvre) {
            if ((int) ($oeuvre['id'] ?? 0) === $oeuvreId
                || (int) ($oeuvre['id'] ?? 0) === $gameOeuvreId
            ) {
                $catalogData[] = CatalogExportSchema::rowToExport($oeuvre);
            }
        }
        $this->assertNotEmpty($catalogData);

        // Simule un import « replace » : on vide les œuvres puis on réimporte.
        (new ImportRunner())->importCatalogSheet([], $catalogHeaders, true);

        // Les séries / sujets orphelins restent — on réimporte tout proprement.
        $seriesBody = array_slice($seriesRows, 1);
        $seriesImport = CatalogMagazineSheets::importSeriesSheet($seriesBody, $seriesRows[0]);
        $this->assertSame([], $seriesImport['errors'], implode('; ', $seriesImport['errors']));
        $this->assertGreaterThanOrEqual(1, $seriesImport['imported']);

        $catalogResult = (new ImportRunner())->importCatalogSheet($catalogData, $catalogHeaders, false);
        $this->assertSame([], $catalogResult['errors'], implode('; ', $catalogResult['errors']));
        $this->assertGreaterThanOrEqual(2, $catalogResult['imported']);

        $subjectBody = array_slice($subjectRows, 1);
        $subjectImport = CatalogMagazineSheets::importSubjectsSheet($subjectBody, $subjectRows[0]);
        $this->assertSame([], $subjectImport['errors'], implode('; ', $subjectImport['errors']));
        $this->assertGreaterThanOrEqual(1, $subjectImport['imported']);

        $linkBody = array_slice($linkRows, 1);
        $linkImport = CatalogMagazineSheets::importSubjectLinksSheet($linkBody, $linkRows[0]);
        $this->assertSame([], $linkImport['errors'], implode('; ', $linkImport['errors']));
        $this->assertGreaterThanOrEqual(1, $linkImport['imported']);

        $seriesAfter = $seriesRepo->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesAfter);
        $this->assertSame('10', (string) ($seriesAfter['rating_scale'] ?? ''));
        $this->assertSame('PC Gamer RoundTrip', $seriesAfter['titre']);

        $subjectAfter = $subjectRepo->findById($subjectId);
        $this->assertNotNull($subjectAfter);
        $this->assertSame($gameOeuvreId, (int) ($subjectAfter['catalog_oeuvre_id'] ?? 0));

        $links = $subjectRepo->listForOeuvre($oeuvreId);
        $this->assertCount(1, $links);
        $this->assertSame(37, (int) ($links[0]['page'] ?? 0));
        $this->assertEqualsWithDelta(8.5, (float) ($links[0]['score'] ?? 0), 0.01);
    }

    /**
     * @param list<string> $header
     * @param array<string, string> $valuesByLabel
     * @return list<string>
     */
    private function catalogRowFromHeader(array $header, array $valuesByLabel): array
    {
        $row = array_fill(0, count($header), '');
        foreach ($valuesByLabel as $label => $value) {
            $index = array_search($label, $header, true);
            if ($index !== false) {
                $row[(int) $index] = $value;
            }
        }

        return $row;
    }
}
