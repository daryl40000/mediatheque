<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GogCatalogClient;
use PHPUnit\Framework\TestCase;

final class GogCatalogClientTest extends TestCase
{
    public function testStoreUrlBuildsGogLink(): void
    {
        $this->assertSame(
            'https://www.gog.com/game/hades',
            GogCatalogClient::storeUrl('hades')
        );
    }

    public function testSlugFromStoreUrlSupportsLocalePath(): void
    {
        $this->assertSame('diablo', GogCatalogClient::slugFromStoreUrl('https://www.gog.com/fr/game/diablo'));
        $this->assertSame(
            'dungeon_keeper',
            GogCatalogClient::slugFromStoreUrl(
                'https://www.gog.com/en/game/dungeon_keeper?utm_campaign=adtraction'
            )
        );
        $this->assertSame('hades', GogCatalogClient::slugFromStoreUrl('https://www.gog.com/game/hades'));
    }

    public function testNormalizeImageUrlAddsHttpsScheme(): void
    {
        $this->assertSame(
            'https://images.gog.com/x.jpg',
            GogCatalogClient::normalizeImageUrl('//images.gog.com/x.jpg')
        );
    }
}
