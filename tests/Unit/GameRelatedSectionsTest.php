<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameRelatedSections;
use PHPUnit\Framework\TestCase;

final class GameRelatedSectionsTest extends TestCase
{
    public function testExtensionShowsBaseGameSection(): void
    {
        $sections = GameRelatedSections::build(
            ['is_extension' => true],
            [
                'oeuvre_id' => 10,
                'library_url' => '/jeu.php?id=5',
                'poster_url' => '/posters/base.jpg',
                'annee' => 2020,
                'titre' => 'Jeu Base',
            ],
            null,
            [],
            [],
            static fn (): string => '',
        );

        $this->assertCount(1, $sections);
        $this->assertSame('Jeu de base', $sections[0]['title']);
        $this->assertSame('/jeu.php?id=5', $sections[0]['items'][0]['url']);
        $this->assertSame('Jeu Base', $sections[0]['items'][0]['titre']);
    }

    public function testRemakeShowsOriginalGameSection(): void
    {
        $sections = GameRelatedSections::build(
            ['is_remake' => true],
            null,
            [
                'oeuvre_id' => 20,
                'library_url' => '/oeuvre-jeu.php?id=20',
                'annee' => 1996,
                'titre' => 'Original',
            ],
            [],
            [],
            static fn (): string => '',
        );

        $this->assertCount(1, $sections);
        $this->assertSame('Jeu d\'origine', $sections[0]['title']);
        $this->assertSame(1996, $sections[0]['items'][0]['annee']);
    }

    public function testBaseGameListsExtensionsAndRemakes(): void
    {
        $sections = GameRelatedSections::build(
            ['is_extension' => false, 'is_remake' => false],
            null,
            null,
            [
                ['bib_id' => 3, 'oeuvre_id' => 30, 'titre' => 'DLC A', 'annee' => 2021, 'poster_url' => null],
            ],
            [
                ['bib_id' => 4, 'oeuvre_id' => 40, 'titre' => 'Remake B', 'annee' => 2022, 'poster_url' => null],
            ],
            static fn (array $row): string => '/jeu.php?id=' . (int) ($row['bib_id'] ?? 0),
        );

        $this->assertCount(2, $sections);
        $this->assertSame('Extensions', $sections[0]['title']);
        $this->assertSame('/jeu.php?id=3', $sections[0]['items'][0]['url']);
        $this->assertSame('Remakes', $sections[1]['title']);
        $this->assertSame('Remake B', $sections[1]['items'][0]['titre']);
    }
}
