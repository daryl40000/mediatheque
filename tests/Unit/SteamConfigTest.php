<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameRowMapper;
use Moncine\SteamConfig;
use PHPUnit\Framework\TestCase;

final class SteamConfigTest extends TestCase
{
    public function testSanitizeSteamIdKeepsDigitsOnly(): void
    {
        $this->assertSame('76561198012345678', SteamConfig::sanitizeSteamId(' 76561198012345678 '));
        $this->assertSame('', SteamConfig::sanitizeSteamId(''));
    }

    public function testIsValidSteamId(): void
    {
        $this->assertTrue(SteamConfig::isValidSteamId('76561198012345678'));
        $this->assertFalse(SteamConfig::isValidSteamId('123'));
        $this->assertFalse(SteamConfig::isValidSteamId(''));
    }

    public function testFormatSteamPlaytimeNeverPlayed(): void
    {
        $this->assertSame('Jamais joué', GameRowMapper::formatSteamPlaytime(0));
        $this->assertSame('45 min', GameRowMapper::formatSteamPlaytime(45));
        $this->assertSame('2 h', GameRowMapper::formatSteamPlaytime(120));
        $this->assertSame('2 h 30 min', GameRowMapper::formatSteamPlaytime(150));
    }
}
