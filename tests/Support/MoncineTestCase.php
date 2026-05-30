<?php
/**
 * Base des tests avec base SQLite temporaire et session utilisateur.
 */

declare(strict_types=1);

namespace Moncine\Tests\Support;

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\Database;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;
use PHPUnit\Framework\TestCase;

abstract class MoncineTestCase extends TestCase
{
    protected static string $testDataDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabase();
        $this->startSession();
        MediaContext::set(MediaDomain::FILM);
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Auth::logout();
        }
        Database::resetInstance();
        parent::tearDown();
    }

    public static function setUpBeforeClass(): void
    {
        self::$testDataDir = (string) getenv('MONCINE_DATA_PATH');
    }

    protected function resetDatabase(): void
    {
        Database::resetInstance();
        $dbFile = MONCINE_DB_FILE;
        if (is_file($dbFile)) {
            unlink($dbFile);
        }
        foreach (glob(MONCINE_DATA . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        Database::getInstance();
    }

    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    /** Crée l’administrateur et ouvre une session connectée. */
    protected function loginAsAdmin(): int
    {
        $repo = new UtilisateurRepository();
        $existing = $repo->findByEmail('admin@test.local');
        if ($existing !== null) {
            $id = (int) $existing['id'];
        } else {
            $id = $repo->create('Admin Test', 'admin@test.local', 'TestPass123!', UserRole::ADMIN);
            $this->assertIsInt($id);
        }

        $login = Auth::login('admin@test.local', 'TestPass123!');
        $this->assertTrue($login === true);

        return $id;
    }

    /** Compte utilisateur standard (non admin). */
    protected function loginAsUser(string $email = 'user@test.local'): int
    {
        $this->loginAsAdmin();
        Auth::logout();
        $this->startSession();

        $repo = new UtilisateurRepository();
        $id = $repo->create('Utilisateur Test', $email, 'TestPass123!', UserRole::USER);
        $this->assertIsInt($id);

        $login = Auth::login($email, 'TestPass123!');
        $this->assertTrue($login === true);

        return $id;
    }

    /**
     * Ajoute une œuvre au catalogue (nécessite admin connecté).
     *
     * @param array<string, mixed> $extra
     */
    protected function seedCatalogOeuvre(
        string $titre,
        string $realisateur = 'Réalisateur Test',
        array $extra = []
    ): int {
        $admin = new CatalogAdmin();
        $data = array_merge([
            'titre' => $titre,
            'realisateur' => $realisateur,
            'annee' => 2020,
            'styles' => 'Drame',
        ], $extra);
        $admin->importOeuvreFromExport($data, array_keys($data));

        $oeuvre = (new \Moncine\OeuvreRepository())->findByTitreAndRealisateur($titre, $realisateur);
        $this->assertNotNull($oeuvre);

        return (int) $oeuvre['id'];
    }

    /**
     * @param list<string|null> $header
     * @param list<list<string|null>> $rows
     */
    protected function libraryHeaderRow(): array
    {
        return \Moncine\LibraryExportSchema::headers();
    }
}
