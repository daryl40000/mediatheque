<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Repository\SortColumnHelper;
use PHPUnit\Framework\TestCase;

final class SortColumnHelperTest extends TestCase
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'titre' => 'o.titre',
        'annee' => 'o.annee',
        'note' => 'note_max',
    ];

    public function testResolveKeepsKnownColumn(): void
    {
        $this->assertSame('annee', SortColumnHelper::resolve('annee', self::COLUMNS));
    }

    public function testResolveFallsBackToDefault(): void
    {
        $this->assertSame('titre', SortColumnHelper::resolve('inconnu', self::COLUMNS));
        $this->assertSame('note', SortColumnHelper::resolve('inconnu', self::COLUMNS, 'note'));
    }

    public function testExpressionReturnsSqlFragment(): void
    {
        $this->assertSame('o.annee', SortColumnHelper::expression('annee', self::COLUMNS));
        $this->assertSame('o.titre', SortColumnHelper::expression('xyz', self::COLUMNS));
    }

    public function testDirectionNormalizesAscDesc(): void
    {
        $this->assertSame('ASC', SortColumnHelper::direction('asc'));
        $this->assertSame('ASC', SortColumnHelper::direction('ASC'));
        $this->assertSame('DESC', SortColumnHelper::direction('desc'));
        $this->assertSame('DESC', SortColumnHelper::direction(' DESC '));
        $this->assertSame('ASC', SortColumnHelper::direction('autre'));
    }
}
