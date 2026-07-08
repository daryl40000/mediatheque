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
            'steam_appid' => 220,
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => ''],
            ]),
        ]);

        $this->assertStringStartsWith('https://store.steampowered.com/app/220/', $url);
    }

    public function testLinkUrlUsesCatalogStoreUrlFields(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::EPIC, [
            'oeuvre_id' => 42,
            'catalog_store_urls' => [],
            'catalog_store_url_epic' => 'https://store.epicgames.com/p/example-game',
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::EPIC, 'url' => ''],
            ]),
        ]);

        $this->assertSame('https://store.epicgames.com/p/example-game', $url);
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

    public function testLinkUrlUsesLocaleGogUrlFromDigitalStores(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::GOG, [
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::GOG, 'url' => 'https://www.gog.com/fr/game/diablo'],
            ]),
        ]);

        $this->assertSame('https://www.gog.com/fr/game/diablo', $url);
    }

    public function testLinkUrlBuildsGogUrlFromStoreLinkSlug(): void
    {
        $url = GameEditionIcons::linkUrlForKey(GameEditionIcons::GOG, [
            'store_link_slug_gog' => 'hades',
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::GOG, 'url' => ''],
            ]),
        ]);

        $this->assertSame('https://www.gog.com/game/hades', $url);
    }
}
