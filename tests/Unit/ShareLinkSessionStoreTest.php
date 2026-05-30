<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ShareLinkSessionStore;
use Moncine\Tests\Support\MoncineTestCase;

final class ShareLinkSessionStoreTest extends MoncineTestCase
{
    public function testRememberAndGetUrl(): void
    {
        $this->loginAsAdmin();
        ShareLinkSessionStore::resetForTests();

        ShareLinkSessionStore::remember(42, 'https://example.test/partage.php?t=x');
        $this->assertSame('https://example.test/partage.php?t=x', ShareLinkSessionStore::get(42));
        $this->assertNull(ShareLinkSessionStore::get(99));
    }
}
