<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\RessentiNote;
use PHPUnit\Framework\TestCase;

final class RessentiNoteTest extends TestCase
{
    public function testScoreFromLegacyTen(): void
    {
        $this->assertSame(1, RessentiNote::scoreFromLegacyTen(1));
        $this->assertSame(1, RessentiNote::scoreFromLegacyTen(2));
        $this->assertSame(3, RessentiNote::scoreFromLegacyTen(6));
        $this->assertSame(5, RessentiNote::scoreFromLegacyTen(10));
    }

    public function testParseInputAcceptsKeyAndScore(): void
    {
        $this->assertSame(['ok' => true, 'score' => 5], RessentiNote::parseInput('adore'));
        $this->assertSame(['ok' => true, 'score' => 4], RessentiNote::parseInput('4'));
        $this->assertSame(['ok' => true, 'score' => null], RessentiNote::parseInput(''));
    }

    public function testParseInputConvertsLegacyTen(): void
    {
        $parsed = RessentiNote::parseInput('9');
        $this->assertTrue($parsed['ok']);
        $this->assertSame(5, $parsed['score']);
    }

    public function testAllLevelsUseRasterIconsWhenFilesPresent(): void
    {
        foreach (RessentiNote::orderedKeys() as $key) {
            $this->assertTrue(RessentiNote::hasRasterIcon($key), 'PNG manquant pour ' . $key);
            $html = RessentiNote::iconSvg($key);
            $this->assertStringContainsString('ressenti-icon-img', $html);
            $this->assertStringContainsString('/assets/icons/ressenti/' . $key . '.png', $html);
            $this->assertSame('/assets/icons/ressenti/' . $key . '.png', RessentiNote::iconUrl($key));
        }
    }

    public function testUnknownKeyFallsBackToSvg(): void
    {
        $html = RessentiNote::iconSvg('inconnu');
        $this->assertStringContainsString('<svg', $html);
        $this->assertNull(RessentiNote::iconUrl('inconnu'));
    }
}
