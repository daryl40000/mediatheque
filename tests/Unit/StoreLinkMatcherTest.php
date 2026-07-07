<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\StoreLinkMatcher;
use PHPUnit\Framework\TestCase;

final class StoreLinkMatcherTest extends TestCase
{
    public function testExactTitleGivesFullConfidence(): void
    {
        $confidence = StoreLinkMatcher::confidence('Hades', 'Hades');

        $this->assertSame(1.0, $confidence);
    }

    public function testBestMatchPicksHighestConfidence(): void
    {
        $result = StoreLinkMatcher::bestMatch('Hades', [
            ['title' => 'Other Game', 'slug' => 'other'],
            ['title' => 'Hades', 'slug' => 'hades'],
        ]);

        $this->assertNotNull($result['best']);
        $this->assertSame('hades', $result['best']['slug']);
        $this->assertGreaterThanOrEqual(StoreLinkMatcher::AUTO_VERIFY_THRESHOLD, $result['confidence']);
    }

    public function testSkipsCandidatesWithoutSlug(): void
    {
        $result = StoreLinkMatcher::bestMatch('Hades', [
            ['title' => 'Hades', 'slug' => ''],
        ]);

        $this->assertNull($result['best']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function testEditionMismatchLowersConfidence(): void
    {
        $base = StoreLinkMatcher::confidence('Hades', 'Hades');
        $edition = StoreLinkMatcher::confidence('Hades', 'Hades — Definitive Edition');

        $this->assertLessThan($base, $edition);
    }
}
