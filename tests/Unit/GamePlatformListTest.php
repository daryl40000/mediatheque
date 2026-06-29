<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GamePlatform;
use Moncine\GamePlatformList;
use PHPUnit\Framework\TestCase;

final class GamePlatformListTest extends TestCase
{
    public function testParseAndSerializeList(): void
    {
        $keys = GamePlatformList::parseList('ps5, pc;ps4');
        $this->assertSame([GamePlatform::PC, GamePlatform::PS4, GamePlatform::PS5], $keys);
        $this->assertSame('pc,ps4,ps5', GamePlatformList::serializeList($keys));
    }

    public function testCatalogKeysFromRowPrefersPlatformsColumn(): void
    {
        $keys = GamePlatformList::catalogKeysFromRow([
            'platform' => GamePlatform::SWITCH,
            'platforms' => 'pc,ps5',
        ]);

        $this->assertSame([GamePlatform::PC, GamePlatform::PS5], $keys);
    }

    public function testOwnedKeysSubsetOfCatalog(): void
    {
        $ownedCsv = GamePlatformList::normalizeOwnedFromPost(
            [GamePlatform::PC, GamePlatform::PS5, GamePlatform::XBOX_ONE],
            [GamePlatform::PC, GamePlatform::PS5]
        );

        $this->assertSame('pc,ps5', $ownedCsv);
    }

    public function testOwnedKeysFromRowFallsBackToCatalog(): void
    {
        $keys = GamePlatformList::ownedKeysFromRow([
            'platform' => GamePlatform::PS4,
            'platforms' => 'pc,ps4',
            'owned_platforms' => '',
        ]);

        $this->assertSame([GamePlatform::PC, GamePlatform::PS4], $keys);
    }

    public function testShortLabelsDisplay(): void
    {
        $label = GamePlatformList::shortLabelsDisplay([GamePlatform::PC, GamePlatform::PS5]);
        $this->assertStringContainsString('PC', $label);
        $this->assertStringContainsString('PS5', $label);
    }
}
