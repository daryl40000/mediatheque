<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\Database;
use Moncine\FamilyGroupService;
use Moncine\FoyerRepository;
use Moncine\LibraryStatut;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
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

    public function testAdminCanDeleteUserInSoloGroupLikeAfterRegistration(): void
    {
        $db = Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);

        $repo = new UtilisateurRepository();
        $userId = $repo->create(
            'Solo Inscrit',
            'solo-reg-delete@test.local',
            'TestPass123!',
            UserRole::USER
        );
        $this->assertIsInt($userId);

        $foyerId = (new FoyerRepository())->createDefaultForUser($userId);
        $this->assertGreaterThan(0, $foyerId);

        $countStmt = $db->prepare('SELECT COUNT(*) FROM group_members WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $this->assertSame(1, (int) $countStmt->fetchColumn());

        $result = $repo->delete($userId);
        $this->assertTrue($result === true, is_string($result) ? $result : 'delete failed');
        $this->assertNull($repo->findById($userId));

        $foyerCheck = $db->prepare('SELECT COUNT(*) FROM foyers WHERE id = ?');
        $foyerCheck->execute([$foyerId]);
        $this->assertSame(0, (int) $foyerCheck->fetchColumn());
    }

    public function testAdminCanDeleteUserWithGroupMembership(): void
    {
        $db = Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);

        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->assertGreaterThan(0, $foyerId);

        $this->ensureGroupMemberRow($foyerId, $adminId, FamilyGroupService::ROLE_FOUNDER);

        $repo = new UtilisateurRepository();
        $memberId = $repo->create(
            'Membre Groupe',
            'group-member-delete@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);
        $this->ensureGroupMemberRow($foyerId, $memberId);

        $countStmt = $db->prepare('SELECT COUNT(*) FROM group_members WHERE user_id = ?');
        $countStmt->execute([$memberId]);
        $this->assertSame(1, (int) $countStmt->fetchColumn());

        $result = $repo->delete($memberId);
        $this->assertTrue($result === true, is_string($result) ? $result : 'delete failed');
        $this->assertNull($repo->findById($memberId));

        $countStmt->execute([$memberId]);
        $this->assertSame(0, (int) $countStmt->fetchColumn());
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

    private function ensureGroupMemberRow(int $foyerId, int $userId, string $role = FamilyGroupService::ROLE_MEMBER): void
    {
        $db = Database::getInstance();
        $db->prepare(
            'INSERT OR IGNORE INTO group_members (foyer_id, user_id, role, joined_at)
             VALUES (?, ?, ?, datetime(\'now\'))'
        )->execute([$foyerId, $userId, $role]);
    }
}
