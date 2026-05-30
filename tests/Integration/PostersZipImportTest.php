<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\ImportPostersZip;
use Moncine\OeuvreRepository;
use Moncine\PosterStorage;
use Moncine\Tests\Support\MoncineTestCase;

final class PostersZipImportTest extends MoncineTestCase
{
    public function testImportZipInstallsPostersAndUpdatesCatalogue(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive non disponible.');
        }

        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Affiche');

        $zipPath = $this->createTestZip($oeuvreId);
        $result = (new ImportPostersZip())->importFromPath($zipPath);
        @unlink($zipPath);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], array_filter(
            $result['errors'],
            static fn (string $e): bool => !str_contains($e, 'ignoré')
        ));

        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        $this->assertStringStartsWith('/posters/', (string) ($oeuvre['poster_url'] ?? ''));

        $file = PosterStorage::filesystemPathFromWeb((string) $oeuvre['poster_url']);
        $this->assertNotNull($file);
        $this->assertFileExists($file);
    }

    private function createTestZip(int $oeuvreId): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'moncine_test_zip_');
        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        // PNG 1x1 minimal
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $zip->addFromString('posters/' . $oeuvreId . '.png', $png !== false ? $png : '');
        $zip->close();

        return $zipPath;
    }
}
