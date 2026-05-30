<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LikePattern;
use PHPUnit\Framework\TestCase;

final class LikePatternTest extends TestCase
{
    public function testEscapeLiteralWildcards(): void
    {
        $this->assertSame('\\%', LikePattern::escapeLiteral('%'));
        $this->assertSame('\\_', LikePattern::escapeLiteral('_'));
        $this->assertSame('\\\\', LikePattern::escapeLiteral('\\'));
    }

    public function testContainsFragmentEscapesPercent(): void
    {
        $this->assertSame('%\\%%', LikePattern::containsFragment('%'));
        $this->assertSame('', LikePattern::containsFragment(''));
        $this->assertSame('%abc%', LikePattern::containsFragment('abc'));
    }
}
