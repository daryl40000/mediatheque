<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\FriendshipRepository;
use Moncine\LikePattern;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
use Moncine\SocialRateLimit;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class SocialSecurityTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = \Moncine\Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);
        SocialRateLimit::resetForTests();
    }

    public function testLikePatternEscapesWildcards(): void
    {
        $this->assertSame('\\%', LikePattern::escapeLiteral('%'));
        $this->assertSame('\\_', LikePattern::escapeLiteral('_'));
        $this->assertSame('%\\%%', LikePattern::containsFragment('%'));
    }

    public function testSearchWithPercentDoesNotReturnEveryone(): void
    {
        $this->loginAsAdmin();
        $repo = new UtilisateurRepository();

        $id1 = $repo->create('U1', 'u1@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($id1);
        $repo->updateProfile($id1, 'U1', 'A', 'u1@test.local', 'Alpha', 'Paris', true);

        $id2 = $repo->create('U2', 'u2@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($id2);
        $repo->updateProfile($id2, 'U2', 'B', 'u2@test.local', 'Beta', 'Lyon', true);

        $results = $repo->searchDiscoverableUsers('%', '', 0);
        $this->assertCount(0, $results);
    }

    public function testFriendRequestRateLimit(): void
    {
        $adminId = $this->loginAsAdmin();
        $repo = new UtilisateurRepository();
        $friendRepo = new FriendshipRepository();

        for ($i = 0; $i < 20; $i++) {
            $targetId = $repo->create('T' . $i, 't' . $i . '@test.local', 'TestPass123!', UserRole::USER);
            $this->assertIsInt($targetId);
            $repo->updateProfile($targetId, 'T', 'X', 't' . $i . '@test.local', 'user' . $i, '', true);
            $result = $friendRepo->sendRequest($adminId, $targetId);
            $this->assertIsInt($result, 'Request ' . $i);
        }

        $extraId = $repo->create('Extra', 'extra@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($extraId);
        $repo->updateProfile($extraId, 'Extra', 'Y', 'extra@test.local', 'extrauser', '', true);

        $blocked = $friendRepo->sendRequest($adminId, $extraId);
        $this->assertIsString($blocked);
        $this->assertStringContainsString('demandes', strtolower($blocked));
    }

    public function testBlockPreventsFriendRequestAndHidesFromSearch(): void
    {
        $adminId = $this->loginAsAdmin();
        $repo = new UtilisateurRepository();
        $friendRepo = new FriendshipRepository();

        $targetId = $repo->create('Victime', 'victime@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($targetId);
        $repo->updateProfile($targetId, 'Victime', 'Z', 'victime@test.local', 'VictimePseudo', 'Nantes', true);

        $this->assertTrue($friendRepo->blockUser($adminId, $targetId) === true);

        $send = $friendRepo->sendRequest($adminId, $targetId);
        $this->assertIsString($send);

        $search = $repo->searchDiscoverableUsers('Victime', '', $adminId);
        $this->assertCount(0, $search);

        Auth::logout();
        $this->startSession();
        SocialRateLimit::resetForTests();
        Auth::login('victime@test.local', 'TestPass123!');

        $fromBlocked = $friendRepo->sendRequest($targetId, $adminId);
        $this->assertIsString($fromBlocked);

        $this->assertTrue($friendRepo->unblockUser($adminId, $targetId) === true);
        $this->assertFalse($friendRepo->isBlockedBetween($adminId, $targetId));
    }
}
