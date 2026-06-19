<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameTitle;
use PHPUnit\Framework\TestCase;

final class GameTitleTest extends TestCase
{
    public function testDisplayTitlePrefersFrench(): void
    {
        $row = ['titre' => 'Le Parrain', 'titre_original' => 'The Godfather'];
        $this->assertSame('Le Parrain', GameTitle::displayTitle($row));
    }

    public function testDisplayTitleFallsBackToEnglish(): void
    {
        $row = ['titre' => '', 'titre_original' => 'The Witcher 3'];
        $this->assertSame('The Witcher 3', GameTitle::displayTitle($row));
    }

    public function testLookupTitlePrefersFrenchForIgdbSearch(): void
    {
        $row = ['titre' => 'Elden Ring', 'titre_original' => 'Elden Ring'];
        $this->assertSame('Elden Ring', GameTitle::lookupTitle($row));
    }

    public function testSearchTextIncludesBothTitles(): void
    {
        $this->assertSame(
            'Le Parrain The Godfather',
            GameTitle::searchText(['titre' => 'Le Parrain', 'titre_original' => 'The Godfather'])
        );
    }
}
