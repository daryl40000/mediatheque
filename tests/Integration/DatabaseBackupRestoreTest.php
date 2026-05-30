<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Database;
use Moncine\DatabaseBackupService;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class DatabaseBackupRestoreTest extends MoncineTestCase
{
    public function testRestoreReplacesDatabaseContent(): void
    {
        if (!DatabaseBackupService::sqlite3Available()) {
            $this->markTestSkipped('SQLite3 extension not available');
        }

        $adminId = $this->loginAsAdmin();
        $uniqueTitle = 'Film Avant Sauvegarde ' . bin2hex(random_bytes(4));
        $this->seedCatalogOeuvre($uniqueTitle, 'Réalisateur Restore');

        $service = new DatabaseBackupService();
        $backupPath = $service->tempBackupPath('roundtrip');
        $export = $service->exportToPath($backupPath);
        $this->assertTrue($export === true);

        $otherTitle = 'Film Après Export ' . bin2hex(random_bytes(4));
        $this->seedCatalogOeuvre($otherTitle, 'Réalisateur Autre');
        $this->assertNotNull((new OeuvreRepository())->findByTitreAndRealisateur($otherTitle, 'Réalisateur Autre'));

        $restore = $service->restoreFromPath($backupPath, $adminId);
        $this->assertTrue($restore === true);

        $this->assertNotNull((new OeuvreRepository())->findByTitreAndRealisateur($uniqueTitle, 'Réalisateur Restore'));
        $this->assertNull((new OeuvreRepository())->findByTitreAndRealisateur($otherTitle, 'Réalisateur Autre'));

        Database::getInstance();
    }
}
