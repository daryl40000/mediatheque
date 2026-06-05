<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\UserPublicProfileService;
use Moncine\View;

final class UserPublicProfileMagazineTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testMagazineStatsAndRecentSeriesOnProfile(): void
    {
        $this->assertTrue(MagazineRepository::isAvailable());

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Joystick Profil',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new MagazineRepository();
        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::WISHLIST, $userId, $foyerId);

        $profile = new UserPublicProfileService();
        $stats = $profile->getStats($userId, MediaDomain::MAGAZINE);

        $this->assertSame(MediaDomain::MAGAZINE, $stats['media_domain']);
        $this->assertGreaterThanOrEqual(1, $stats['wishlist_count']);

        $recentWishlist = $profile->lastWishlistFilms($userId, 5, MediaDomain::MAGAZINE);
        $this->assertNotEmpty($recentWishlist);
        $this->assertSame('Joystick Profil', (string) ($recentWishlist[0]['titre'] ?? ''));

        $this->assertSame(
            '/utilisateur.php?id=' . $userId . '&domain=magazine',
            View::userProfileUrl($userId, MediaDomain::MAGAZINE)
        );
    }

    public function testProfileCanListFriendMagazineIssues(): void
    {
        $adminId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'PC Jeux Profil Public',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $repo = new MagazineRepository();
        $bibId = $repo->createIssueWithLibrary($seriesId, [
            'numero' => '7',
            'numero_ordre' => 7,
            'date_parution' => '2024-07-01',
            'sommaire' => 'Test sommaire public',
        ], LibraryStatut::COLLECTION, $adminId, $foyerId);
        $this->assertIsInt($bibId);

        $profile = new UserPublicProfileService();
        $this->assertTrue($profile->canViewMagazineSeries($adminId, $adminId, $seriesId) === true);
        $this->assertTrue($profile->canViewMagazineIssue($adminId, $adminId, $bibId) === true);

        $issues = $profile->listMagazineIssuesForSeries($adminId, $seriesId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $issues);
        $this->assertSame('7', (string) ($issues[0]['numero'] ?? ''));

        $this->assertStringContainsString(
            'utilisateur-serie-magazine.php',
            View::userProfileMagazineSeriesUrl($adminId, $seriesId)
        );
        $this->assertStringContainsString(
            'utilisateur-numero-magazine.php',
            View::userProfileMagazineIssueUrl($adminId, $bibId)
        );
    }
}
