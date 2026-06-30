<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\BdPhysicalSupport;
use Moncine\BdPossession;
use PHPUnit\Framework\TestCase;

final class BdPossessionTest extends TestCase
{
    public function testIsPossessedWhenSupportValid(): void
    {
        $row = ['support_physique' => BdPhysicalSupport::ALBUM];
        $this->assertTrue(BdPossession::isPossessed($row));
        $this->assertSame('Album', BdPossession::possessionStatusLabel($row));
    }

    public function testIsNotPossessedWhenSupportEmpty(): void
    {
        $row = ['support_physique' => ''];
        $this->assertFalse(BdPossession::isPossessed($row));
        $this->assertSame('Non possédé', BdPossession::possessionStatusLabel($row));
    }
}
