<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\FamilyGroupService;
use Moncine\FilmRepository;
use Moncine\FriendshipRepository;
use Moncine\FoyerRepository;
use Moncine\GroupWishlistRepository;
use Moncine\LibraryStatut;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class GroupWishlistTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = \Moncine\Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);
    }

    public function testAggregatedWishlistAndVote(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);

        Auth::logout();
        $this->startSession();

        $repo = new UtilisateurRepository();
        $aliceId = $repo->create('Alice W', 'alicew@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($aliceId);

        Auth::logout();
        $this->startSession();

        $bobId = $repo->create('Bob W', 'bobw@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($bobId);
        Auth::login('bobw@test.local', 'TestPass123!');

        $friendRepo = new FriendshipRepository();
        $reqId = $friendRepo->sendRequest($bobId, $aliceId);
        $this->assertIsInt($reqId);

        Auth::logout();
        $this->startSession();
        Auth::login('alicew@test.local', 'TestPass123!');
        $friendRepo->acceptRequest($reqId, $aliceId);

        $groupService = new FamilyGroupService();
        $groupId = $groupService->createGroup($aliceId, 'Groupe Envies');
        $this->assertIsInt($groupId);

        $inviteId = $groupService->inviteFriend($groupId, $aliceId, $bobId);
        $this->assertIsInt($inviteId);
        Auth::logout();
        $this->startSession();
        Auth::login('bobw@test.local', 'TestPass123!');
        $groupService->acceptInvitation($inviteId, $bobId);

        $oeuvreId = $this->seedCatalogOeuvre('Film Envie Groupe', 'Réal Groupe');
        $films = new FilmRepository();

        Auth::logout();
        $this->startSession();
        Auth::login('alicew@test.local', 'TestPass123!');
        $this->assertIsInt($films->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST));

        $groupWish = new GroupWishlistRepository();
        $this->assertTrue($groupWish->canShowGroupView($groupId));

        $rows = $groupWish->findAggregated($groupId, $bobId, 'votes', 'desc', '');
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) ($rows[0]['vote_count'] ?? 0));
        $this->assertFalse((bool) ($rows[0]['in_my_wishlist'] ?? true));

        $bobEntry = $films->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($bobEntry);

        $rowsAfter = $groupWish->findAggregated($groupId, $bobId, 'votes', 'desc', '');
        $this->assertSame(2, (int) ($rowsAfter[0]['vote_count'] ?? 0));
        $this->assertTrue((bool) ($rowsAfter[0]['in_my_wishlist'] ?? false));
    }
}
