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
