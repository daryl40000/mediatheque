<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\FamilyGroupService;
use Moncine\FilmRepository;
use Moncine\FriendshipRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserPublicProfileService;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class UserPublicProfileTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = \Moncine\Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);
    }

    public function testFriendCanViewProfileAndStats(): void
    {
        $this->loginAsAdmin();
        $adminId = \Moncine\UserContext::currentUserId();

        Auth::logout();
        $this->startSession();

        $users = new UtilisateurRepository();
        $aliceId = $users->create('Alice P', 'alicep@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($aliceId);

        Auth::logout();
        $this->startSession();
        Auth::login('alicep@test.local', 'TestPass123!');

        $oeuvreId = $this->seedCatalogOeuvre('Film Profil Social', 'Réal Social');
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        $this->assertIsInt($filmId);
        (new HistoriqueRepository())->markVu($filmId, 8);

        $friendRepo = new FriendshipRepository();
        $requestId = $friendRepo->sendRequest($aliceId, $adminId);
        $this->assertIsInt($requestId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');

        $this->assertTrue($friendRepo->acceptRequest($requestId, $adminId) === true);

        $profile = new UserPublicProfileService();
        $this->assertTrue($profile->canView($adminId, $aliceId) === true);

        $stats = $profile->getStats($aliceId);
        $this->assertGreaterThanOrEqual(1, $stats['wishlist_count']);
        $this->assertGreaterThanOrEqual(1, $stats['films_vus_count']);

        $lastViewed = $profile->lastViewedFilms($aliceId, 5);
        $this->assertNotEmpty($lastViewed);
        $this->assertSame('Film Profil Social', $lastViewed[0]['titre'] ?? '');

        $history = $profile->listViewingHistory($aliceId);
        $this->assertCount(1, $history);
        $this->assertSame(
            \Moncine\RessentiNote::scoreFromLegacyTen(8),
            (int) ($history[0]['note'] ?? 0)
        );
        $this->assertNotSame('', (string) ($history[0]['date_vue'] ?? ''));
    }

    public function testGroupMembersCanViewEachOtherProfile(): void
    {
        Auth::logout();
        $this->startSession();

        $users = new UtilisateurRepository();
        $aliceId = $users->create('Alice G', 'aliceg@test.local', 'TestPass123!', UserRole::USER);
        $bobId = $users->create('Bob G', 'bobg@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($aliceId);
        $this->assertIsInt($bobId);

        Auth::logout();
        $this->startSession();
        Auth::login('aliceg@test.local', 'TestPass123!');

        $groupService = new FamilyGroupService();
        $groupId = $groupService->createGroup($aliceId, 'Groupe Profil');
        $this->assertIsInt($groupId);

        $friendRepo = new FriendshipRepository();
        $requestId = $friendRepo->sendRequest($aliceId, $bobId);
        $this->assertIsInt($requestId);

        Auth::logout();
        $this->startSession();
        Auth::login('bobg@test.local', 'TestPass123!');
        $friendRepo->acceptRequest($requestId, $bobId);

        $inviteId = $groupService->inviteFriend($groupId, $aliceId, $bobId);
        $this->assertIsInt($inviteId);
        $groupService->acceptInvitation($inviteId, $bobId);

        $profile = new UserPublicProfileService();
        $this->assertTrue($profile->canView($aliceId, $bobId) === true);
        $this->assertTrue($profile->canView($bobId, $aliceId) === true);
        $this->assertTrue($groupService->shareSameGroup($aliceId, $bobId));
    }

    public function testStrangerCannotViewProfile(): void
    {
        $this->loginAsAdmin();
        $adminId = \Moncine\UserContext::currentUserId();

        Auth::logout();
        $this->startSession();

        $strangerId = (new UtilisateurRepository())->create(
            'Stranger',
            'stranger@test.local',
            'TestPass123!',
            UserRole::USER
        );
        $this->assertIsInt($strangerId);

        Auth::logout();
        $this->startSession();
        Auth::login('stranger@test.local', 'TestPass123!');

        $profile = new UserPublicProfileService();
        $this->assertNotTrue($profile->canView($strangerId, $adminId));
    }
}
