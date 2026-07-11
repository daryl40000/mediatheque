<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\EpicCatalogClient;
use PHPUnit\Framework\TestCase;

final class EpicCatalogClientTest extends TestCase
{
    public function testStoreUrlBuildsEpicLink(): void
    {
        $this->assertSame(
            'https://store.epicgames.com/p/hades',
            EpicCatalogClient::storeUrl('hades')
        );
    }

    public function testSlugFromStoreUrl(): void
    {
        $this->assertSame(
            'hades',
            EpicCatalogClient::slugFromStoreUrl('https://store.epicgames.com/p/hades')
        );
    }
}
