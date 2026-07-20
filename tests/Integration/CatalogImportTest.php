<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogAdmin;
use Moncine\CatalogExportSchema;
use Moncine\ImportRunner;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class CatalogImportTest extends MoncineTestCase
{
    public function testAdminCanImportNewCatalogRow(): void
    {
        $this->loginAsAdmin();

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '8001',
            'Titre' => 'Nouveau film',
            'Réalisateur' => 'Martin',
            'Année' => '2015',
            'Synopsis' => 'Résumé du film',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $oeuvre = (new OeuvreRepository())->findByTitreAndRealisateur('Nouveau film', 'Martin');
        $this->assertNotNull($oeuvre);
        $this->assertSame(2015, (int) $oeuvre['annee']);
        $this->assertSame('Résumé du film', $oeuvre['synopsis']);
    }

    public function testAdminCanUpdateExistingOeuvreById(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Titre initial', 'Auteur');

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => (string) $oeuvreId,
            'Titre' => 'Titre modifié',
            'Réalisateur' => 'Auteur',
            'Synopsis' => 'Nouveau synopsis',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        $this->assertSame('Titre modifié', $oeuvre['titre']);
        $this->assertSame('Nouveau synopsis', $oeuvre['synopsis']);
    }

    public function testNonAdminCannotImportCatalog(): void
    {
        $this->loginAsAdmin();
        $this->seedCatalogOeuvre('Référence');

        $this->loginAsUser();

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '8002',
            'Titre' => 'Tentative',
            'Réalisateur' => 'Hacker',
            'Synopsis' => 'Ne doit pas passer',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('administrateur', $result['errors'][0]);
        $this->assertNull((new OeuvreRepository())->findByTitreAndRealisateur('Tentative', 'Hacker'));
    }

    public function testImportFilmsSheetDetectsCatalogFormat(): void
    {
        $this->loginAsAdmin();

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '8003',
            'Titre' => 'Via importFilmsSheet',
            'Réalisateur' => 'Auto',
            'Synopsis' => 'OK',
        ]);

        $result = (new ImportRunner())->importFilmsSheet([$row], $header);

        $this->assertSame(1, $result['imported']);
        $this->assertTrue(CatalogAdmin::canAccess());
    }

    public function testAdminCanImportBdCatalogRowWithSeries(): void
    {
        $this->loginAsAdmin();
        (new \Moncine\SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        if (!\Moncine\BdRepository::isAvailable()) {
            $this->markTestSkipped('Module BD indisponible.');
        }

        $header = CatalogExportSchema::headers();
        $row = $this->catalogRowFromHeader($header, [
            'ID catalogue' => '9101',
            'Titre' => 'Tome Catalogue BD',
            'Réalisateur' => 'Scénariste BD',
            'Domaine média' => 'bd',
            'BD — ID série' => '501',
            'BD — titre série' => 'Série Catalogue Import',
            'BD — tome n°' => '2',
            'BD — scénariste' => 'Scénariste BD',
            'BD — dessinateur' => 'Dessinateur BD',
        ]);

        $result = (new ImportRunner())->importCatalogSheet([$row], $header);
        $this->assertSame(1, $result['imported'], implode('; ', $result['errors']));
        $this->assertSame([], $result['errors']);

        $oeuvre = (new OeuvreRepository())->findByIdForAdmin(9101);
        $this->assertNotNull($oeuvre);
        $this->assertSame('bd', $oeuvre['media_domain']);

        $catalog = (new \Moncine\BdRepository())->findCatalogByOeuvreId(9101);
        $this->assertNotNull($catalog);
        $this->assertSame(501, (int) $catalog['series_id']);
        $this->assertSame(2, (int) $catalog['tome_numero']);

        $series = (new \Moncine\SeriesRepository())->findById(501, \Moncine\MediaDomain::BD);
        $this->assertNotNull($series);
        $this->assertSame('Série Catalogue Import', $series['titre']);
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
