<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameDigitalStore;
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

    public function testPcDigitalStoresWithUrls(): void
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
        $this->assertStringContainsString('steampowered.com', $stores[0]['url']);
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
