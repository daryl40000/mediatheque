<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GamePlaytime;
use PHPUnit\Framework\TestCase;

final class GamePlaytimeTest extends TestCase
{
    public function testManualMinutesFromPostCombinesHoursAndMinutes(): void
    {
        $minutes = GamePlaytime::manualMinutesFromPost([
            'manual_playtime_hours' => 12,
            'manual_playtime_minutes_part' => 45,
        ]);

        $this->assertSame(765, $minutes);
    }

    public function testHydrateRowSumsSteamAndManual(): void
    {
        $row = GamePlaytime::hydrateRow([
            'steam_playtime_minutes' => 120,
            'manual_playtime_minutes' => 30,
        ]);

        $this->assertSame(150, $row['playtime_minutes']);
        $this->assertSame('2 h 30 min', $row['playtime_label']);
        $this->assertTrue($row['has_manual_playtime']);
        $this->assertFalse($row['never_played']);
    }
}
