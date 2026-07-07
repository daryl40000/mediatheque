<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameDigitalStore;
use Moncine\GameEditionIcons;
use Moncine\GamePhysicalSupport;
use Moncine\GamePlatform;
use PHPUnit\Framework\TestCase;

final class GameEditionTest extends TestCase
{
    public function testPhysicalSupportsSerialize(): void
    {
        $this->assertSame(
            'cd_dvd,disquette',
            GamePhysicalSupport::normalizeFromPost(['cd_dvd', 'disquette', 'cd_dvd'])
        );
        $this->assertSame(['CD / DVD'], GamePhysicalSupport::displayLabels('cd_dvd'));
        $this->assertSame(
            ['Disquette/cartouche'],
            GamePhysicalSupport::displayLabels('disquette')
        );
        $this->assertSame(
            'disquette',
            GamePhysicalSupport::normalizeFromPost(['cartouche'])
        );
    }

    public function testPcDigitalStoresOwnershipOnly(): void
    {
        $json = GameDigitalStore::buildFromPost([
            'is_digital' => '1',
            'platform' => GamePlatform::PC,
            'digital_pc_stores' => ['steam', 'gog'],
            'digital_store_url' => [
                'steam' => 'https://store.steampowered.com/app/123/',
                'gog' => 'https://www.gog.com/game/example',
            ],
        ], GamePlatform::PC);

        $stores = GameDigitalStore::parseStoredList($json);
        $this->assertCount(2, $stores);
        $this->assertSame('steam', $stores[0]['store']);
        $this->assertSame('', $stores[0]['url']);
        $this->assertSame('gog', $stores[1]['store']);
        $this->assertSame('', $stores[1]['url']);
    }

    public function testBattlenetStoreNormalizeAndIcon(): void
    {
        $json = GameDigitalStore::serializeList([
            ['store' => 'battle.net', 'url' => 'https://battle.net/shop/fr/game/example'],
        ]);
        $stores = GameDigitalStore::parseStoredList($json);
        $this->assertCount(1, $stores);
        $this->assertSame(GameDigitalStore::BATTLENET, $stores[0]['store']);
        $this->assertSame('Battle.net', $stores[0]['label']);

        $keys = GameEditionIcons::iconKeys(['digital_stores' => $json]);
        $this->assertSame([GameDigitalStore::BATTLENET], $keys);

        $url = GameEditionIcons::iconImageUrl(GameEditionIcons::BATTLENET);
        $this->assertStringContainsString('battlenet.', $url);
    }

    public function testConsoleDigitalStoreWithoutUrl(): void
    {
        $json = GameDigitalStore::buildFromPost([
            'is_digital' => '1',
            'platform' => GamePlatform::PS5,
        ], GamePlatform::PS5);

        $stores = GameDigitalStore::parseStoredList($json);
        $this->assertCount(1, $stores);
        $this->assertSame('psn', $stores[0]['store']);
        $this->assertSame('', $stores[0]['url']);
        $this->assertSame('PlayStation Store', $stores[0]['label']);
    }
}
