<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CatalogGameStoreLinks;
use Moncine\GameDigitalStore;
use PHPUnit\Framework\TestCase;

final class CatalogGameStoreLinksTest extends TestCase
{
    public function testUrlsFromCatalogRowUsesCatalogStoreUrls(): void
    {
        $urls = CatalogGameStoreLinks::urlsForCatalogRow([
            'titre' => 'Hades',
            'catalog_store_urls' => [
                'gog' => 'https://www.gog.com/game/hades',
            ],
            'steam_appid' => 1145360,
        ]);

        $this->assertSame('https://www.gog.com/game/hades', $urls['gog']);
        $this->assertStringContainsString('1145360', $urls['steam']);
    }

    public function testUrlsFromCatalogRowFallsBackToLegacyDigitalStoresForAdminMigration(): void
    {
        $urls = CatalogGameStoreLinks::urlsForCatalogRow([
            'titre' => 'Hades',
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => 'epic', 'url' => 'https://store.epicgames.com/p/hades'],
            ]),
        ]);

        $this->assertSame('https://store.epicgames.com/p/hades', $urls['epic']);
    }

    public function testRemoveStoreClearsJsonEntry(): void
    {
        $json = GameDigitalStore::serializeList([
            ['store' => 'gog', 'url' => 'https://www.gog.com/game/hades'],
            ['store' => 'epic', 'url' => 'https://store.epicgames.com/p/hades'],
        ]);

        $cleared = GameDigitalStore::removeStore($json, 'gog');

        $this->assertStringNotContainsString('gog.com', $cleared);
        $this->assertStringContainsString('epicgames.com', $cleared);
    }
}
