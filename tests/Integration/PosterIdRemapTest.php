<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogAdmin;
use Moncine\CatalogExportSchema;
use Moncine\ImportFilmRows;
use Moncine\OeuvreRepository;
use Moncine\PosterIdRemap;
use Moncine\PosterStorage;
use Moncine\Tests\Support\MoncineTestCase;

final class PosterIdRemapTest extends MoncineTestCase
{
    public function testRemapMovesPosterFromOldIdToCurrentCatalogId(): void
    {
        $this->loginAsAdmin();

        $admin = new CatalogAdmin();
        $admin->importOeuvreFromExport([
            'titre' => 'Film à recaler',
            'realisateur' => 'Réalisateur',
        ], ['titre', 'realisateur']);

        $oeuvre = (new OeuvreRepository())->findByTitreAndRealisateur('Film à recaler', 'Réalisateur');
        $this->assertNotNull($oeuvre);
        $newId = (int) $oeuvre['id'];
        $this->assertGreaterThan(0, $newId);

        $oldId = 7777;
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        file_put_contents(
            PosterStorage::postersFilesystemDir() . '/' . $oldId . '.png',
            $png !== false ? $png : ''
        );

        $csvPath = $this->writeRemapCsv($oldId, 'Film à recaler', 'Réalisateur');
        $result = (new PosterIdRemap())->remapFromCatalogExportPath($csvPath);
        @unlink($csvPath);

        $this->assertSame(1, $result['remapped']);

        $oeuvre = (new OeuvreRepository())->findById($newId);
        $this->assertStringContainsString('/posters/' . $newId . '.', (string) ($oeuvre['poster_url'] ?? ''));
        $this->assertFileDoesNotExist(PosterStorage::postersFilesystemDir() . '/' . $oldId . '.png');
    }

    private function writeRemapCsv(int $oldId, string $titre, string $realisateur): string
    {
        $header = CatalogExportSchema::headers();
        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);
        $row = array_fill(0, count($header), '');
        $row[$map['oeuvre_id']] = (string) $oldId;
        $row[$map['titre']] = $titre;
        $row[$map['realisateur']] = $realisateur;
        $row[$map['synopsis']] = 'x';

        $path = sys_get_temp_dir() . '/moncine_remap_' . uniqid('', true) . '.csv';
        $handle = fopen($path, 'wb');
        fputcsv($handle, $header, ';');
        fputcsv($handle, $row, ';');
        fclose($handle);

        return $path;
    }
}
