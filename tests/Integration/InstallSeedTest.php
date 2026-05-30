<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogExportSchema;
use Moncine\Database;
use Moncine\ExportCatalog;
use Moncine\InstallSeed;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;

final class InstallSeedTest extends MoncineTestCase
{
    private string $seedDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDir = MONCINE_DATA . '/install_seed';
        if (!is_dir($this->seedDir)) {
            mkdir($this->seedDir, 0755, true);
        }
        foreach (glob($this->seedDir . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testApplyInstallSeedOnEmptyDatabase(): void
    {
        $this->writeMinimalCatalogCsv();

        $pdo = Database::getInstance();
        $migrator = new SchemaMigrator($pdo);
        $result = (new InstallSeed($migrator))->applyIfEligible();

        $this->assertSame('applied', $result['status']);
        $this->assertSame(1, $result['catalog_imported'] ?? 0);
        $this->assertSame(1, (new ExportCatalog())->catalogEntryCount());
        $this->assertNotSame('', $migrator->getMetadata(SchemaMigrator::META_INSTALL_SEED_APPLIED));
    }

    public function testInstallSeedSkippedWhenAlreadyApplied(): void
    {
        $this->writeMinimalCatalogCsv();

        $migrator = new SchemaMigrator(Database::getInstance());
        $seed = new InstallSeed($migrator);
        $this->assertSame('applied', $seed->applyIfEligible()['status']);

        $again = $seed->applyIfEligible();
        $this->assertSame('skipped', $again['status']);
        $this->assertStringContainsString('déjà appliquée', $again['message']);
    }

    public function testInstallSeedSkippedWhenCatalogNotEmpty(): void
    {
        $this->loginAsAdmin();
        $this->seedCatalogOeuvre('Film existant');

        $this->writeMinimalCatalogCsv();

        $result = (new InstallSeed(new SchemaMigrator(Database::getInstance())))->applyIfEligible();

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('non vide', $result['message']);
    }

    private function writeMinimalCatalogCsv(): void
    {
        $path = $this->seedDir . '/catalogue.csv';
        $headers = CatalogExportSchema::headers();
        $row = array_fill(0, count($headers), '');
        $idIdx = array_search('ID catalogue', $headers, true);
        $titreIdx = array_search('Titre', $headers, true);
        $realIdx = array_search('Réalisateur', $headers, true);
        $this->assertNotFalse($idIdx);
        $this->assertNotFalse($titreIdx);
        $row[$idIdx] = '9001';
        $row[$titreIdx] = 'Film graine';
        $row[$realIdx] = 'Test Seed';

        $out = fopen($path, 'wb');
        $this->assertNotFalse($out);
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        fputcsv($out, $row, ';');
        fclose($out);
    }
}
