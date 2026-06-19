<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\IgdbClient;
use Moncine\IgdbGenreMap;
use PHPUnit\Framework\TestCase;

final class IgdbGenreMapTest extends TestCase
{
    public function testTranslateKnownGenre(): void
    {
        $this->assertSame('Aventure', IgdbGenreMap::translateOne('Adventure'));
        $this->assertSame('RPG', IgdbGenreMap::translateOne('Role-playing (RPG)'));
    }

    public function testTranslateListUsesFrenchLabels(): void
    {
        $result = IgdbGenreMap::translateList(['Shooter', 'Adventure']);
        $this->assertSame('FPS, Aventure', $result);
    }

    public function testUnknownGenreKeptAsIs(): void
    {
        $this->assertSame('Roguelike', IgdbGenreMap::translateOne('Roguelike'));
    }
}

final class IgdbClientParseTest extends TestCase
{
    public function testParseNumericId(): void
    {
        $this->assertSame(1942, IgdbClient::parseIdFromInput('1942'));
    }

    public function testParseUrlWithTrailingId(): void
    {
        $this->assertSame(123, IgdbClient::parseIdFromInput('https://www.igdb.com/games/foo-bar-123'));
    }

    public function testParseInvalidInput(): void
    {
        $this->assertNull(IgdbClient::parseIdFromInput(''));
        $this->assertNull(IgdbClient::parseIdFromInput('abc'));
    }

    public function testCoverUrlFromImageId(): void
    {
        $url = IgdbClient::coverUrlFromImageId('co1wyy');
        $this->assertStringContainsString('co1wyy', $url);
        $this->assertStringStartsWith('https://images.igdb.com/', $url);
    }

    public function testYearFromTimestamp(): void
    {
        $this->assertSame(2015, IgdbClient::yearFromTimestamp(1431993600));
        $this->assertSame(0, IgdbClient::yearFromTimestamp(0));
    }
}
