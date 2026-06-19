<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameRelations;
use PHPUnit\Framework\TestCase;

final class GameRelationsTest extends TestCase
{
    public function testExtensionAndRemakeAreMutuallyExclusive(): void
    {
        $error = GameRelations::validateFlags([
            'is_extension' => true,
            'base_game_oeuvre_id' => 1,
            'is_remake' => true,
            'original_game_oeuvre_id' => 2,
        ]);

        $this->assertSame(
            'Une fiche ne peut pas être à la fois une extension et un remake.',
            $error
        );
    }

    public function testSelfReferenceIsRejected(): void
    {
        $this->assertSame(
            'Un remake ne peut pas pointer vers lui-même.',
            GameRelations::validateFlags([
                'is_remake' => true,
                'original_game_oeuvre_id' => 42,
            ], 42)
        );
    }
}
