<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BdKind;
use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\ShareLinkBdRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\UserPublicProfileService;
use Moncine\View;

final class UserPublicProfileBdTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testBdStatsAndRecentTomesOnProfile(): void
    {
        $this->assertTrue(BdRepository::isAvailable());

        $seriesId = (new SeriesRepository())->create([
            'titre' => 'Profil BD Test',
            'tags' => BdKind::MANGA,
        ], MediaDomain::BD);
        $this->assertIsInt($seriesId);

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new BdRepository();
        $repo->registerSeriesInLibrary($seriesId, LibraryStatut::COLLECTION, $userId, $foyerId);

        $bibId = $repo->createTomeWithLibrary($seriesId, [
            'tome_numero' => 3,
            'support_physique' => BdPhysicalSupport::ALBUM,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $profile = new UserPublicProfileService();
        $stats = $profile->getStats($userId, MediaDomain::BD);

        $this->assertSame(MediaDomain::BD, $stats['media_domain']);
        $this->assertGreaterThanOrEqual(1, $stats['collection_count']);
        $this->assertSame(1, $stats['tome_count']);

        $recent = $profile->lastCollectionFilms($userId, 5, MediaDomain::BD);
        $this->assertNotEmpty($recent);
        $this->assertSame('Profil BD Test', (string) ($recent[0]['series_titre'] ?? ''));

        $seriesList = $profile->listCollection($userId, 'titre', 'asc', MediaDomain::BD);
        $this->assertNotEmpty($seriesList);

        $this->assertSame(
            '/utilisateur.php?id=' . $userId . '&domain=bd',
            View::userProfileUrl($userId, MediaDomain::BD)
        );
    }
}
