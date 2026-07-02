<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameFormPayload;
use Moncine\GamePlatform;
use PHPUnit\Framework\TestCase;

final class GameFormPayloadTest extends TestCase
{
    public function testLinuxFlagsMutuallyExclusiveForPc(): void
    {
        $flags = GameFormPayload::linuxFlagsFromPost([
            'platforms' => [GamePlatform::PC],
            'linux_not_supported' => '1',
            'tested_on_linux' => '1',
        ]);

        $this->assertFalse($flags['tested_on_linux']);
        $this->assertTrue($flags['linux_not_supported']);
    }

    public function testOwnedPlatformsSubsetOfCatalog(): void
    {
        $owned = GameFormPayload::ownedPlatformsFromPost(
            ['owned_platforms' => [GamePlatform::PS5]],
            GamePlatform::PS5 . ',' . GamePlatform::PC
        );

        $this->assertSame(GamePlatform::PS5, $owned['owned_platforms']);
        $this->assertSame([GamePlatform::PS5], $owned['owned_platform_list']);
    }
}
