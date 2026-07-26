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
