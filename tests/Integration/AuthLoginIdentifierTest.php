<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class AuthLoginIdentifierTest extends MoncineTestCase
{
    public function testLoginWithEmailStillWorks(): void
    {
        $repo = new UtilisateurRepository();
        $id = $repo->create('Alice', 'alice-login@test.local', 'TestPass123!', UserRole::USER, 0, 'Alice', 'FilmFan');
        $this->assertIsInt($id);

        Auth::logout();
        $this->startSession();

        $this->assertTrue(Auth::login('alice-login@test.local', 'TestPass123!') === true);
        $this->assertSame($id, Auth::currentUserId());
    }

    public function testLoginWithPseudoWorks(): void
    {
        $repo = new UtilisateurRepository();
        $id = $repo->create('Bob', 'bob-login@test.local', 'TestPass123!', UserRole::USER, 0, 'Bob', 'GameFan42');
        $this->assertIsInt($id);

        Auth::logout();
        $this->startSession();

        $this->assertTrue(Auth::login('GameFan42', 'TestPass123!') === true);
        $this->assertSame($id, Auth::currentUserId());
    }

    public function testLoginWithPseudoIsCaseInsensitive(): void
    {
        $repo = new UtilisateurRepository();
        $repo->create('Carol', 'carol-login@test.local', 'TestPass123!', UserRole::USER, 0, 'Carol', 'MangaLover');

        Auth::logout();
        $this->startSession();

        $this->assertTrue(Auth::login('mangalover', 'TestPass123!') === true);
    }

    public function testLoginWithUnknownPseudoFails(): void
    {
        $this->loginAsAdmin();
        Auth::logout();
        $this->startSession();

        $this->assertSame('Identifiants incorrects.', Auth::login('NobodyHere', 'TestPass123!'));
    }

    public function testDuplicatePseudoRejectedOnCreate(): void
    {
        $repo = new UtilisateurRepository();
        $this->assertIsInt($repo->create('One', 'one-pseudo@test.local', 'TestPass123!', UserRole::USER, 0, '', 'UniqueNick'));

        $second = $repo->create('Two', 'two-pseudo@test.local', 'TestPass123!', UserRole::USER, 0, '', 'uniquenick');
        $this->assertSame('Ce pseudo est déjà utilisé.', $second);
    }

    public function testEmptyPseudoDoesNotAllowPseudoLogin(): void
    {
        $repo = new UtilisateurRepository();
        $repo->create('No Pseudo', 'nopseudo@test.local', 'TestPass123!', UserRole::USER);

        Auth::logout();
        $this->startSession();

        $this->assertSame('Identifiants incorrects.', Auth::login('No Pseudo', 'TestPass123!'));
        $this->assertTrue(Auth::login('nopseudo@test.local', 'TestPass123!') === true);
    }
}
