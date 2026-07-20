<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Exception\NotFoundException;
use Moncine\Exception\ValidationException;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

/**
 * Phase E — pilote exceptions sur la création de compte.
 */
final class UtilisateurCreateExceptionTest extends MoncineTestCase
{
    public function testCreateRejectsInvalidEmail(): void
    {
        $repo = new UtilisateurRepository();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Adresse e-mail invalide.');
        $repo->create('Nom', 'pas-un-email', 'TestPass123!', UserRole::USER);
    }

    public function testCreateRejectsDuplicateEmail(): void
    {
        $repo = new UtilisateurRepository();
        $repo->create('Premier', 'dup@test.local', 'TestPass123!', UserRole::USER);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cette adresse e-mail est déjà utilisée.');
        $repo->create('Second', 'dup@test.local', 'TestPass123!', UserRole::USER);
    }

    public function testCreateRejectsUnknownFoyer(): void
    {
        $repo = new UtilisateurRepository();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Foyer introuvable.');
        $repo->create('Nom', 'foyer@test.local', 'TestPass123!', UserRole::USER, 99999);
    }

    public function testCreateSuccessReturnsId(): void
    {
        $id = (new UtilisateurRepository())->create(
            'Ok',
            'ok-create@test.local',
            'TestPass123!',
            UserRole::USER
        );
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateFirstAdminWhenAccountExists(): void
    {
        $this->loginAsAdmin();
        $repo = new UtilisateurRepository();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Un compte administrateur existe déjà');
        $repo->createFirstAdmin('Autre', 'autre-admin@test.local', 'TestPass123!');
    }
}
