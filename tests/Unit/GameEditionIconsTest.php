<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameDigitalStore;
use Moncine\GameEditionIcons;
use Moncine\GamePhysicalSupport;
use PHPUnit\Framework\TestCase;

final class GameEditionIconsTest extends TestCase
{
    public function testIconKeysFromPhysicalAndDigital(): void
    {
        $keys = GameEditionIcons::iconKeys([
            'physical_supports' => GamePhysicalSupport::CD_DVD,
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => ''],
                ['store' => GameDigitalStore::GOG, 'url' => ''],
            ]),
        ]);

        $this->assertSame(
            [GameEditionIcons::CD_DVD, GameDigitalStore::STEAM, GameDigitalStore::GOG],
            $keys
        );
    }

    public function testIconKeysEmptyWhenNoEdition(): void
    {
        $this->assertSame([], GameEditionIcons::iconKeys([]));
    }
}
