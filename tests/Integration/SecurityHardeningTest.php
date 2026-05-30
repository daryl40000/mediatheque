<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\EmailChangeRepository;
use Moncine\EmailChangeService;
use Moncine\RegistrationPasswordCipher;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class SecurityHardeningTest extends MoncineTestCase
{
    public function testRegistrationPasswordCipherRoundTrip(): void
    {
        $hash = UtilisateurRepository::hashPassword('TestPass123!');
        $this->assertNotNull($hash);

        $stored = RegistrationPasswordCipher::encryptHash($hash);
        $this->assertNotSame($hash, $stored);

        $decoded = RegistrationPasswordCipher::decryptStored($stored);
        $this->assertSame($hash, $decoded);
    }

    public function testEmailChangeRequiresConfirmation(): void
    {
        if (!EmailChangeRepository::tableExists()) {
            $this->markTestSkipped('Migration 029 non appliquée.');
        }

        $userId = $this->loginAsUser('change@test.local');
        $repo = new UtilisateurRepository();
        $user = $repo->findById($userId);
        $this->assertIsArray($user);

        $result = (new EmailChangeService())->requestChange(
            $userId,
            'TestPass123!',
            'newaddr@test.local',
            (string) ($user['nom'] ?? ''),
            (string) ($user['prenom'] ?? ''),
            (string) ($user['pseudo'] ?? ''),
            (string) ($user['ville'] ?? ''),
            true
        );
        $this->assertIsString($result);
        $this->assertStringContainsString('lien de confirmation', $result);

        $this->assertSame('change@test.local', $repo->findById($userId)['email'] ?? '');
        $this->assertNull($repo->findByEmail('newaddr@test.local'));
    }

    public function testSoloFoyerRemovedOnAccountDelete(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new \Moncine\FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->assertGreaterThan(0, $foyerId);

        $memberId = (new UtilisateurRepository())->create(
            'Solo',
            'solo@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);

        $db = \Moncine\Database::getInstance();
        $db->prepare('DELETE FROM group_members WHERE foyer_id = ? AND user_id != ?')
            ->execute([$foyerId, $memberId]);

        $this->assertTrue((new UtilisateurRepository())->deleteOwnAccount($memberId, 'TestPass123!') === true);

        $stmt = $db->prepare('SELECT 1 FROM foyers WHERE id = ?');
        $stmt->execute([$foyerId]);
        $this->assertFalse($stmt->fetchColumn());
    }
}
