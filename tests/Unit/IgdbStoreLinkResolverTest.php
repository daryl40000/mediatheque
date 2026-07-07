<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\EpicCatalogClient;
use Moncine\GameDigitalStore;
use Moncine\GogCatalogClient;
use Moncine\IgdbStoreLinkResolver;
use PHPUnit\Framework\TestCase;

final class IgdbStoreLinkResolverTest extends TestCase
{
    public function testParseEpicStoreEntry(): void
    {
        $parsed = IgdbStoreLinkResolver::parseStoreEntry([
            'name' => 'Hades',
            'url' => 'https://store.epicgames.com/p/hades',
        ], GameDigitalStore::EPIC);

        $this->assertNotNull($parsed);
        $this->assertSame('hades', $parsed['slug']);
        $this->assertSame('Hades', $parsed['title']);
    }

    public function testParseGogStoreEntry(): void
    {
        $parsed = IgdbStoreLinkResolver::parseStoreEntry([
            'name' => 'The Witcher 3: Wild Hunt',
            'url' => 'https://www.gog.com/game/the_witcher_3_wild_hunt',
        ], GameDigitalStore::GOG);

        $this->assertNotNull($parsed);
        $this->assertSame('the_witcher_3_wild_hunt', $parsed['slug']);
    }

    public function testRejectsUnrelatedUrl(): void
    {
        $this->assertNull(IgdbStoreLinkResolver::parseStoreEntry([
            'name' => 'Half-Life 2',
            'url' => 'https://store.steampowered.com/app/220',
        ], GameDigitalStore::EPIC));
    }

    public function testSlugFromStoreUrlHelpers(): void
    {
        $this->assertSame('hades', EpicCatalogClient::slugFromStoreUrl('https://store.epicgames.com/p/hades'));
        $this->assertSame('hades', GogCatalogClient::slugFromStoreUrl('https://www.gog.com/game/hades'));
    }
}
