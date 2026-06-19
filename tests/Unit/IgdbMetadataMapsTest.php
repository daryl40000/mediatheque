<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\IgdbAlternativeNameFilter;
use Moncine\IgdbGameModeMap;
use Moncine\IgdbThemeMap;
use PHPUnit\Framework\TestCase;

final class IgdbMetadataMapsTest extends TestCase
{
    public function testGameModeTranslation(): void
    {
        $this->assertSame('Solo', IgdbGameModeMap::translateOne('Single player'));
        $this->assertSame('Coopératif, Multijoueur', IgdbGameModeMap::translateList(['Co-operative', 'Multiplayer']));
    }

    public function testThemeTranslation(): void
    {
        $this->assertSame('Monde ouvert', IgdbThemeMap::translateOne('Open world'));
        $this->assertSame('Horreur, Science-fiction', IgdbThemeMap::translateList(['Horror', 'Science fiction']));
    }

    public function testAcronymFilterKeepsShortFormsOnly(): void
    {
        $this->assertTrue(IgdbAlternativeNameFilter::isAcronym('GTA'));
        $this->assertTrue(IgdbAlternativeNameFilter::isAcronym('TLoZ'));
        $this->assertFalse(IgdbAlternativeNameFilter::isAcronym('Grand Theft Auto'));
        $this->assertFalse(IgdbAlternativeNameFilter::isAcronym('A'));

        $this->assertSame(
            'GTA, FF',
            IgdbAlternativeNameFilter::serializeAcronyms(['GTA', 'Grand Theft Auto', 'FF'], 'Grand Theft Auto V')
        );
    }
}
