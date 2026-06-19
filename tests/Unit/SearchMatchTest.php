<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\SearchMatch;
use PHPUnit\Framework\TestCase;

final class SearchMatchTest extends TestCase
{
    public function testFoldIgnoresAccentsAndCase(): void
    {
        $this->assertSame('demineur', SearchMatch::fold('Démineur'));
        $this->assertSame('elden ring', SearchMatch::fold('ELDEN RING'));
    }

    public function testMatchesAccentInsensitive(): void
    {
        $this->assertTrue(SearchMatch::matches('Démon Souls', 'demon'));
        $this->assertTrue(SearchMatch::matches('Gran Turismo 7', 'gran turismo'));
    }

    public function testMatchesOneTypoPerWord(): void
    {
        $this->assertTrue(SearchMatch::matches('Elden Ring', 'eldn ring'));
        $this->assertTrue(SearchMatch::matches('Resident Evil', 'residant evil'));
        $this->assertFalse(SearchMatch::matches('Elden Ring', 'zelda'));
    }

    public function testFilterRankLimitPrefersCloserMatch(): void
    {
        $rows = [
            ['id' => 1, 'titre' => 'Elden Ring'],
            ['id' => 2, 'titre' => 'Elder Scrolls'],
            ['id' => 3, 'titre' => 'Zelda'],
        ];

        $filtered = SearchMatch::filterRankLimit(
            $rows,
            'elden',
            static fn (array $row): string => (string) ($row['titre'] ?? ''),
            2
        );

        $this->assertCount(2, $filtered);
        $this->assertSame(1, $filtered[0]['id']);
    }
}
