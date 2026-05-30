<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\FamilyGroupService;
use Moncine\FriendshipRepository;
use Moncine\FoyerRepository;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class FriendshipGroupTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = \Moncine\Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);
    }

    public function testFriendRequestAndAccept(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $repo = new UtilisateurRepository();
        $aliceId = $repo->create('Alice', 'alice@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($aliceId);
        Auth::login('alice@test.local', 'TestPass123!');

        Auth::logout();
        $this->startSession();

        $bobId = $repo->create('Bob', 'bob@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($bobId);
        Auth::login('bob@test.local', 'TestPass123!');

        $friendRepo = new FriendshipRepository();
        $requestId = $friendRepo->sendRequest($bobId, $aliceId);
        $this->assertIsInt($requestId);

        Auth::logout();
        $this->startSession();
        Auth::login('alice@test.local', 'TestPass123!');

        $this->assertTrue($friendRepo->acceptRequest($requestId, $aliceId) === true);
        $this->assertTrue($friendRepo->areFriends($aliceId, $bobId));
    }

    public function testCreateGroupAndInviteFriend(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $repo = new UtilisateurRepository();
        $aliceId = $repo->create('Alice G', 'aliceg@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($aliceId);
        Auth::login('aliceg@test.local', 'TestPass123!');

        Auth::logout();
        $this->startSession();

        $bobId = $repo->create('Bob G', 'bobg@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($bobId);
        Auth::login('bobg@test.local', 'TestPass123!');

        $friendRepo = new FriendshipRepository();
        $reqId = $friendRepo->sendRequest($bobId, $aliceId);
        $this->assertIsInt($reqId);

        Auth::logout();
        $this->startSession();
        Auth::login('aliceg@test.local', 'TestPass123!');
        $friendRepo->acceptRequest($reqId, $aliceId);

        $groupService = new FamilyGroupService();
        $groupId = $groupService->createGroup($aliceId, 'Famille Test');
        $this->assertIsInt($groupId);

        $inviteId = $groupService->inviteFriend($groupId, $aliceId, $bobId);
        $this->assertIsInt($inviteId);

        Auth::logout();
        $this->startSession();
        Auth::login('bobg@test.local', 'TestPass123!');

        $this->assertTrue($groupService->acceptInvitation($inviteId, $bobId) === true);
        $this->assertSame($groupId, (new FoyerRepository())->currentFoyerIdForUser($bobId));
        $this->assertTrue($groupService->isMember($groupId, $bobId));
    }
}
