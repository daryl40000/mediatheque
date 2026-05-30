<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogAdmin;
use Moncine\OeuvreRepository;
use Moncine\PosterStorage;
use Moncine\Tests\Support\MoncineTestCase;

final class CatalogPosterUploadTest extends MoncineTestCase
{
    public function testUploadPosterFileStoresLocalPosterAndUpdatesCatalogue(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Upload Affiche');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true
        );
        $this->assertNotFalse($png);

        $tmp = tempnam(sys_get_temp_dir(), 'moncine_poster_upload_');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $png);

        $result = (new CatalogAdmin())->uploadPosterFile($oeuvreId, $tmp, strlen($png));
        @unlink($tmp);

        $this->assertSame(true, $result);

        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        $this->assertStringStartsWith('/posters/', (string) ($oeuvre['poster_url'] ?? ''));

        $file = PosterStorage::filesystemPathFromWeb((string) $oeuvre['poster_url']);
        $this->assertNotNull($file);
        $this->assertFileExists($file);
    }

    public function testUploadPosterFileRejectsEmptyFile(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Upload Vide');

        $tmp = tempnam(sys_get_temp_dir(), 'moncine_poster_empty_');
        $this->assertNotFalse($tmp);

        $result = (new CatalogAdmin())->uploadPosterFile($oeuvreId, $tmp, 0);
        @unlink($tmp);

        $this->assertIsString($result);
        $this->assertStringContainsString('volumineuse', $result);
    }
}
