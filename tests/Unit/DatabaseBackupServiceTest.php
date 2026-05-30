<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Database;
use Moncine\DatabaseBackupService;
use Moncine\Tests\Support\MoncineTestCase;
use PDO;

final class DatabaseBackupServiceTest extends MoncineTestCase
{
    public function testValidateBackupRejectsNonSqliteFile(): void
    {
        $path = MONCINE_DATA . '/not-a-db.bin';
        file_put_contents($path, 'not sqlite content');

        $result = (new DatabaseBackupService())->validateBackupFile($path);
        $this->assertIsString($result);
        $this->assertStringContainsString('SQLite', $result);

        unlink($path);
    }

    public function testValidateBackupAcceptsMinimalMoncineSchema(): void
    {
        if (!DatabaseBackupService::sqlite3Available()) {
            $this->markTestSkipped('SQLite3 extension not available');
        }

        $path = MONCINE_DATA . '/minimal-backup.db';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec(
            'CREATE TABLE app_metadata (key TEXT PRIMARY KEY, value TEXT);
             CREATE TABLE oeuvres (id INTEGER PRIMARY KEY, titre TEXT);
             CREATE TABLE utilisateurs (id INTEGER PRIMARY KEY, nom TEXT);
             CREATE TABLE bibliotheque (id INTEGER PRIMARY KEY, oeuvre_id INTEGER);'
        );
        unset($pdo);

        $result = (new DatabaseBackupService())->validateBackupFile($path);
        $this->assertTrue($result === true);

        unlink($path);
    }

    public function testVerifyAdminPasswordRequiresMatchingSession(): void
    {
        $adminId = $this->loginAsAdmin();
        $service = new DatabaseBackupService();

        $this->assertTrue($service->verifyAdminPassword($adminId, 'TestPass123!'));
        $this->assertFalse($service->verifyAdminPassword($adminId, 'wrong-password'));
        $this->assertFalse($service->verifyAdminPassword($adminId + 999, 'TestPass123!'));
    }

    public function testExportCreatesReadableBackup(): void
    {
        if (!DatabaseBackupService::sqlite3Available()) {
            $this->markTestSkipped('SQLite3 extension not available');
        }

        $this->loginAsAdmin();
        $this->seedCatalogOeuvre('Backup Export Test', 'Réalisateur B');

        $dest = MONCINE_DATA . '/export-test.db';
        $service = new DatabaseBackupService();
        $result = $service->exportToPath($dest);
        $this->assertTrue($result === true);
        $this->assertFileExists($dest);

        $check = new PDO('sqlite:' . $dest);
        $count = (int) $check->query('SELECT COUNT(*) FROM oeuvres')->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $count);

        unlink($dest);
    }
}
