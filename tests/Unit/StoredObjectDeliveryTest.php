<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\StoredObjectDelivery;
use PHPUnit\Framework\TestCase;

final class StoredObjectDeliveryTest extends TestCase
{
    public function testInlineSafeMimeTypes(): void
    {
        $this->assertTrue(StoredObjectDelivery::isInlineSafe('application/pdf'));
        $this->assertTrue(StoredObjectDelivery::isInlineSafe('image/jpeg'));
        $this->assertFalse(StoredObjectDelivery::isInlineSafe('text/html'));
        $this->assertFalse(StoredObjectDelivery::isInlineSafe('application/javascript'));
    }
}
