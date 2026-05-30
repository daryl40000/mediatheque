<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CollectionStats;
use PHPUnit\Framework\TestCase;

final class CollectionStatsDurationTest extends TestCase
{
    public function testFormatViewingDurationUnderOneDay(): void
    {
        $this->assertSame('0h 00min', CollectionStats::formatViewingDuration(0));
        $this->assertSame('0h 05min', CollectionStats::formatViewingDuration(5));
        $this->assertSame('1h 00min', CollectionStats::formatViewingDuration(60));
        $this->assertSame('2h 30min', CollectionStats::formatViewingDuration(150));
        $this->assertSame('23h 59min', CollectionStats::formatViewingDuration(1439));
    }

    public function testFormatViewingDurationOneDayOrMore(): void
    {
        $this->assertSame('1j 0h 00min', CollectionStats::formatViewingDuration(1440));
        $this->assertSame('5j 5h 07min', CollectionStats::formatViewingDuration(7507));
    }
}
