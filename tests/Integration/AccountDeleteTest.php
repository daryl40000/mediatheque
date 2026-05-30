<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\LibraryStatut;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class AccountDeleteTest extends MoncineTestCase
{
    public function testUserCanDeleteOwnAccountWithPassword(): void
    {
        $repo = new UtilisateurRepository();
        $userId = $repo->create(
            'Supprimable',
            'delete-me@test.local',
            'TestPass123!',
            UserRole::USER
        );
        $this->assertIsInt($userId);

        $result = $repo->deleteOwnAccount($userId, 'TestPass123!');
        $this->assertTrue($result === true);
        $this->assertNull($repo->findById($userId));
    }

    public function testAdminCannotDeleteOwnAccountFromAccountPage(): void
    {
        $adminId = $this->loginAsAdmin();

        $result = (new UtilisateurRepository())->deleteOwnAccount($adminId, 'TestPass123!');
        $this->assertIsString($result);
        $this->assertStringContainsString('administrateur', $result);
        $this->assertNotNull((new UtilisateurRepository())->findById($adminId));
    }

    public function testUserCanDeleteWhenMemberOfSharedFoyerWithCollection(): void
    {
        $adminId = $this->loginAsAdmin();
        $admin = (new UtilisateurRepository())->findById($adminId);
        $this->assertIsArray($admin);
        $foyerId = (int) ($admin['foyer_id'] ?? 0);
        if ($foyerId <= 0) {
            $foyerId = (new \Moncine\FoyerRepository())->currentFoyerIdForUser($adminId);
        }
        $this->assertGreaterThan(0, $foyerId);

        $oeuvreId = $this->seedCatalogOeuvre('Film membre');
        $memberId = (new UtilisateurRepository())->create(
            'Membre Suppr',
            'member-delete@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);

        $libraryId = (new BibliothequeRepository())->insert(
            $memberId,
            $foyerId,
            $oeuvreId,
            ['statut' => LibraryStatut::COLLECTION]
        );
        $this->assertGreaterThan(0, $libraryId);

        Auth::logout();
        $this->startSession();

        $repo = new UtilisateurRepository();
        $this->assertTrue($repo->deleteOwnAccount($memberId, 'TestPass123!') === true);
        $this->assertNull($repo->findById($memberId));

        $row = (new BibliothequeRepository())->findById($libraryId, $adminId, $foyerId);
        $this->assertNotNull($row);
        $this->assertSame($adminId, (int) ($row['user_id'] ?? 0));
    }

    public function testDeleteOwnAccountRequiresCorrectPassword(): void
    {
        $repo = new UtilisateurRepository();
        $userId = $repo->create(
            'Protégé',
            'protected@test.local',
            'TestPass123!',
            UserRole::USER
        );
        $this->assertIsInt($userId);

        $result = $repo->deleteOwnAccount($userId, 'WrongPass1!');
        $this->assertIsString($result);
        $this->assertStringContainsString('Mot de passe incorrect', $result);
        $this->assertNotNull($repo->findById($userId));
    }
}
