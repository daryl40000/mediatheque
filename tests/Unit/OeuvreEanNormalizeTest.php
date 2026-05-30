<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\OeuvreEanRepository;
use Moncine\View;
use PHPUnit\Framework\TestCase;

final class OeuvreEanNormalizeTest extends TestCase
{
    public function testNormalizeEanRemovesSpacesAndDashes(): void
    {
        $this->assertSame(
            '3760061234567',
            OeuvreEanRepository::normalizeEan('3 760 061 234 567')
        );
        $this->assertSame(
            '3760061234567',
            OeuvreEanRepository::normalizeEan('376-006-1234567')
        );
    }

    public function testFormatEanNeverAddsSpaces(): void
    {
        $this->assertSame('3760061234567', View::formatEan('3760061234567'));
        $this->assertSame('3760061234567', View::formatEan('3 760 061 234 567'));
        $this->assertSame('12345678', View::formatEan('1234 5678'));
    }
}
