<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Repository\SqlNamedParams;
use PHPUnit\Framework\TestCase;

final class SqlNamedParamsTest extends TestCase
{
    public function testKeepsOnlyPlaceholdersPresentInSql(): void
    {
        $sql = 'SELECT * FROM t WHERE a = :alpha AND b = :beta';
        $params = [
            'alpha' => 1,
            'beta' => 2,
            'unused' => 3,
        ];

        $this->assertSame(
            ['alpha' => 1, 'beta' => 2],
            SqlNamedParams::filter($sql, $params)
        );
    }

    public function testReturnsEmptyWhenNoPlaceholders(): void
    {
        $this->assertSame([], SqlNamedParams::filter('SELECT 1', ['x' => 1]));
    }

    public function testIgnoresMissingParamKeys(): void
    {
        $sql = 'SELECT * FROM t WHERE a = :alpha AND b = :beta';
        $this->assertSame(
            ['alpha' => 'ok'],
            SqlNamedParams::filter($sql, ['alpha' => 'ok'])
        );
    }

    public function testDeduplicatesRepeatedPlaceholders(): void
    {
        $sql = 'SELECT * FROM t WHERE a = :q OR b = :q';
        $this->assertSame(
            ['q' => 'x'],
            SqlNamedParams::filter($sql, ['q' => 'x', 'other' => 1])
        );
    }
}
