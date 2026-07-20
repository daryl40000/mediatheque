<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\BdUrls;
use Moncine\GameUrls;
use Moncine\LibraryStatut;
use Moncine\MagazineUrls;
use Moncine\View;
use PHPUnit\Framework\TestCase;

final class DomainViewUrlsTest extends TestCase
{
    public function testMagazinesUrlDefault(): void
    {
        $this->assertSame('/magazines.php?sort=titre&dir=asc', MagazineUrls::magazinesUrl());
    }

    public function testMagazineSeriesStatsUrlCollection(): void
    {
        $this->assertSame(
            '/stats-serie-magazine.php?series_id=12',
            MagazineUrls::magazineSeriesStatsUrl(12)
        );
    }

    public function testMagazineSeriesStatsUrlWishlist(): void
    {
        $this->assertSame(
            '/stats-serie-magazine.php?series_id=12&statut=wishlist',
            MagazineUrls::magazineSeriesStatsUrl(12, LibraryStatut::WISHLIST)
        );
    }

    public function testBdUrl(): void
    {
        $this->assertSame('/album-bd.php?id=5', BdUrls::bdUrl(5));
    }

    public function testGamesCollectionUrlDefault(): void
    {
        $this->assertSame('/jeux.php', GameUrls::gamesCollectionUrl());
    }

    public function testViewMagazinesUrlDelegatesToMagazineUrls(): void
    {
        $this->assertSame(
            MagazineUrls::magazinesUrl('x'),
            View::magazinesUrl('x')
        );
    }
}
