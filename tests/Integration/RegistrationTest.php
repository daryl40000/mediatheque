<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\Database;
use Moncine\InscriptionRequestRepository;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UtilisateurRepository;

final class RegistrationTest extends MoncineTestCase
{
    public function testSubmitIsNeutralWhenEmailAlreadyRegistered(): void
    {
        $this->loginAsAdmin();
        (new RegistrationSettings())->setMode(RegistrationSettings::MODE_OPEN);
        Auth::logout();

        $service = new RegistrationService();
        $this->assertTrue($service->submitRequest('Existant', 'admin@test.local', 'TestPass123!') === true);
        $this->assertSame(0, (new InscriptionRequestRepository())->countPendingAdmin());
    }

    public function testConfirmTokenValidBeforePostConfirm(): void
    {
        $this->loginAsAdmin();
        (new RegistrationSettings())->setMode(RegistrationSettings::MODE_OPEN);
        Auth::logout();

        $plain = $this->seedPendingRegistration('precheck@test.local');
        $service = new RegistrationService();
        $this->assertTrue($service->isConfirmTokenValid($plain));
        $this->assertNull((new UtilisateurRepository())->findByEmail('precheck@test.local'));

        $result = $service->confirmEmail($plain);
        $this->assertSame('ready', $result['outcome']);
        $this->assertFalse($service->isConfirmTokenValid($plain));
    }

    public function testOpenModeCreatesAccountAfterEmailConfirm(): void
    {
        $this->loginAsAdmin();
        (new RegistrationSettings())->setMode(RegistrationSettings::MODE_OPEN);
        Auth::logout();

        $plain = $this->seedPendingRegistration('newopen@test.local');
        $result = (new RegistrationService())->confirmEmail($plain);
        $this->assertSame('ready', $result['outcome']);

        $login = Auth::login('newopen@test.local', 'TestPass123!');
        $this->assertTrue($login === true);

        $stmt = Database::getInstance()->prepare(
            'SELECT password_hash, status FROM inscription_requests WHERE email = ? LIMIT 1'
        );
        $stmt->execute(['newopen@test.local']);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        $this->assertSame(InscriptionRequestRepository::STATUS_APPROVED, $row['status']);
        $this->assertSame('', trim((string) ($row['password_hash'] ?? 'x')));
    }

    public function testApprovalModeRequiresAdminAfterEmailConfirm(): void
    {
        $this->loginAsAdmin();
        $settings = new RegistrationSettings();
        $settings->setMode(RegistrationSettings::MODE_APPROVAL_REQUIRED);
        Auth::logout();

        $plain = $this->seedPendingRegistration('pending@test.local');
        $service = new RegistrationService();
        $result = $service->confirmEmail($plain);
        $this->assertSame('pending_admin', $result['outcome']);

        $this->assertNotTrue(Auth::login('pending@test.local', 'TestPass123!'));

        $this->loginAsAdmin();
        $pending = $service->listPendingAdmin();
        $this->assertCount(1, $pending);
        $approve = $service->approve((int) $pending[0]['id'], Auth::currentUserId());
        $this->assertTrue($approve === true);
        Auth::logout();

        $this->assertTrue(Auth::login('pending@test.local', 'TestPass123!') === true);
    }

    public function testInsertDuplicateActiveEmailReturnsMessageNotException(): void
    {
        $hash = UtilisateurRepository::hashPassword('TestPass123!');
        $this->assertNotNull($hash);
        $expires = gmdate('Y-m-d H:i:s', time() + InscriptionRequestRepository::CONFIRM_TTL_SECONDS);
        $repo = new InscriptionRequestRepository();

        $this->assertTrue($repo->insertPendingEmail(
            'Once',
            '',
            '',
            'dup@test.local',
            $hash,
            hash('sha256', 'token1'),
            $expires
        ) === true);

        $second = $repo->insertPendingEmail(
            'Twice',
            '',
            '',
            'dup@test.local',
            $hash,
            hash('sha256', 'token2'),
            $expires
        );
        $this->assertIsString($second);
        $this->assertStringContainsString('déjà en cours', $second);
    }

    public function testSubmitTreatsInsertRaceAsNeutralSuccess(): void
    {
        $this->loginAsAdmin();
        (new RegistrationSettings())->setMode(RegistrationSettings::MODE_OPEN);
        Auth::logout();

        $hash = UtilisateurRepository::hashPassword('TestPass123!');
        $this->assertNotNull($hash);
        $repo = new InscriptionRequestRepository();
        $repo->insertPendingEmail(
            'Race',
            '',
            '',
            'race@test.local',
            $hash,
            hash('sha256', 'x'),
            gmdate('Y-m-d H:i:s', time() + InscriptionRequestRepository::CONFIRM_TTL_SECONDS)
        );

        $service = new RegistrationService();
        $this->assertTrue($service->submitRequest('Race', 'race@test.local', 'TestPass123!') === true);
    }

    public function testOnlyOneActiveRequestPerEmail(): void
    {
        $this->loginAsAdmin();
        (new RegistrationSettings())->setMode(RegistrationSettings::MODE_OPEN);
        Auth::logout();

        $service = new RegistrationService();
        $this->assertTrue($service->submitRequest('Once', 'once@test.local', 'TestPass123!') === true);
        $this->assertTrue($service->submitRequest('Twice', 'once@test.local', 'TestPass123!') === true);

        $repo = new InscriptionRequestRepository();
        $this->assertTrue($repo->hasActiveRequestForEmail('once@test.local'));
        $count = (int) \Moncine\Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscription_requests WHERE LOWER(TRIM(email)) = 'once@test.local'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    private function seedPendingRegistration(string $email): string
    {
        $plain = bin2hex(random_bytes(32));
        $hash = UtilisateurRepository::hashPassword('TestPass123!');
        $this->assertNotNull($hash);

        $insert = (new InscriptionRequestRepository())->insertPendingEmail(
            'Test',
            '',
            '',
            $email,
            $hash,
            hash('sha256', $plain),
            gmdate('Y-m-d H:i:s', time() + InscriptionRequestRepository::CONFIRM_TTL_SECONDS)
        );
        $this->assertTrue($insert === true);

        return $plain;
    }
}
