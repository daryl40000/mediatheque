<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameGenre;
use PHPUnit\Framework\TestCase;

final class GameGenreTest extends TestCase
{
    public function testParseAndSerializeGenres(): void
    {
        $this->assertSame(['Action-RPG', 'FPS'], GameGenre::parseList('Action-RPG, FPS, action-rpg'));
        $this->assertSame('Action-RPG, FPS', GameGenre::serializeList(['Action-RPG', 'FPS', 'action-rpg']));
        $this->assertSame('Action-RPG, FPS', GameGenre::normalizeFromPost(['Action-RPG', 'FPS', 'action-rpg']));
        $this->assertSame('Aventure', GameGenre::normalizeInput('Aventure; Aventure'));
    }

    public function testListContainsTagIgnoresPositionAndSpacing(): void
    {
        $stored = 'Aventure, RPG, Simulation';

        $this->assertTrue(GameGenre::listContainsTag($stored, 'aventure'));
        $this->assertTrue(GameGenre::listContainsTag($stored, 'RPG'));
        $this->assertTrue(GameGenre::listContainsTag($stored, 'simulation'));
        $this->assertFalse(GameGenre::listContainsTag($stored, 'fps'));
    }
}
