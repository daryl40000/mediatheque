<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameDigitalStore;
use Moncine\GameEditionIcons;
use PHPUnit\Framework\TestCase;

final class GameEditionIconsLinkTest extends TestCase
{
    public function testLinkUrlFromDigitalStores(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::STEAM, [
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => 'https://store.steampowered.com/app/42/'],
            ]),
        ]);

        $this->assertSame('https://store.steampowered.com/app/42/', $url);
    }

    public function testLinkUrlFallbackFromSteamAppId(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::STEAM, [
            'titre' => 'Half-Life 2',
            'library_steam_appid' => 220,
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => ''],
            ]),
        ]);

        $this->assertStringStartsWith('https://store.steampowered.com/app/220/', $url);
    }

    public function testLinkUrlPrefersCatalogStoreUrls(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::GOG, [
            'catalog_store_urls' => [
                GameDigitalStore::GOG => 'https://www.gog.com/game/catalog-link',
            ],
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::GOG, 'url' => 'https://www.gog.com/game/user-link'],
            ]),
        ]);

        $this->assertSame('https://www.gog.com/game/catalog-link', $url);
    }
}
