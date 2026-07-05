<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameSteamAppIdMapRepository;
use PHPUnit\Framework\TestCase;

final class GameSteamAppIdMapRepositoryTest extends TestCase
{
    public function testIsAvailableFollowsSchema(): void
    {
        $this->assertIsBool(GameSteamAppIdMapRepository::isAvailable());
    }

    public function testSourceManualConstant(): void
    {
        $this->assertSame('manual', GameSteamAppIdMapRepository::SOURCE_MANUAL);
    }
}
