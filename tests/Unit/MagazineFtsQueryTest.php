<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineFtsQuery;
use PHPUnit\Framework\TestCase;

final class MagazineFtsQueryTest extends TestCase
{
    public function testMatchExpressionBuildsPrefixQuery(): void
    {
        $this->assertSame(
            '"gran"* AND "turismo"*',
            MagazineFtsQuery::matchExpression('gran turismo')
        );
        $this->assertSame('', MagazineFtsQuery::matchExpression('   '));
        $this->assertSame('"pc"*', MagazineFtsQuery::matchExpression('PC'));
    }
}
