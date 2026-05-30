<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\CatalogSubmission;
use Moncine\CatalogSubmissionRepository;
use Moncine\FilmManualEdit;
use Moncine\FilmRepository;
use Moncine\FoyerRepository;
use Moncine\OeuvreRepository;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class CatalogSubmissionTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
    }

    public function testUserCanSubmitAndAdminApproves(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $userId = (new UtilisateurRepository())->create(
            'Proposeur Test',
            'proposeur@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($userId);
        Auth::login('proposeur@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Proposition Unique',
            'realisateur' => 'Réalisateur Proposition',
            'annee' => '1999',
            'content_kind' => 'film',
        ]);
        $this->assertTrue($parsed['ok']);

        $submitId = (new CatalogSubmission())->submit($userId, $parsed['data'], 'Ma note test');
        $this->assertIsInt($submitId);

        $row = (new CatalogSubmissionRepository())->findById($submitId);
        $this->assertSame(CatalogSubmissionRepository::STATUS_PENDING, $row['status'] ?? '');

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        $oeuvreId = (new CatalogSubmission())->approve($submitId, $adminId, $parsed['data']);
        $this->assertIsInt($oeuvreId);

        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        $this->assertSame('Film Proposition Unique', $oeuvre['titre'] ?? '');

        $updated = (new CatalogSubmissionRepository())->findById($submitId);
        $this->assertSame(CatalogSubmissionRepository::STATUS_APPROVED, $updated['status'] ?? '');
        $this->assertSame($oeuvreId, (int) ($updated['resulting_oeuvre_id'] ?? 0));
    }

    public function testRejectDoesNotCreateOeuvre(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $rejectUserId = (new UtilisateurRepository())->create(
            'User Reject',
            'reject@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($rejectUserId);
        Auth::login('reject@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Refusé Test',
            'realisateur' => 'Réalisateur Refus',
            'content_kind' => 'film',
        ]);
        $submitId = (new CatalogSubmission())->submit($rejectUserId, $parsed['data']);
        $this->assertIsInt($submitId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        $result = (new CatalogSubmission())->reject($submitId, $adminId, 'Doublon probable');
        $this->assertTrue($result);

        $this->assertNull(
            (new OeuvreRepository())->findByTitreAndRealisateur('Film Refusé Test', 'Réalisateur Refus')
        );
    }

    public function testNonAdminCannotCreateOeuvreViaAddFilm(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $addUserId = (new UtilisateurRepository())->create(
            'User Add',
            'addfilm@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($addUserId);
        Auth::login('addfilm@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Hors Catalogue',
            'realisateur' => 'Réalisateur Hors',
            'content_kind' => 'film',
            'statut' => 'collection',
        ]);
        $this->assertTrue($parsed['ok']);

        $result = (new FilmRepository())->createManual($parsed['data'], 'collection');
        $this->assertIsString($result);
        $this->assertStringContainsString('Proposer au catalogue', $result);
        $this->assertFalse(CatalogAdmin::canAccess());
    }

    public function testDuplicateCatalogEntryRejectedOnSubmit(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->seedCatalogOeuvre('Film Existant', 'Réalisateur Existant');

        Auth::logout();
        $this->startSession();

        $dupUserId = (new UtilisateurRepository())->create(
            'User Dup',
            'dup@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($dupUserId);
        Auth::login('dup@test.local', 'TestPass123!');

        $parsed = FilmManualEdit::parseFromPost([
            'titre' => 'Film Existant',
            'realisateur' => 'Réalisateur Existant',
            'content_kind' => 'film',
        ]);

        $result = (new CatalogSubmission())->submit($dupUserId, $parsed['data']);
        $this->assertIsString($result);
        $this->assertStringContainsString('déjà au catalogue', $result);
    }
}
