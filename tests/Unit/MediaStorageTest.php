<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MediaStorage;
use PHPUnit\Framework\TestCase;

final class MediaStorageTest extends TestCase
{
    public function testRelativeAndAbsolutePaths(): void
    {
        $rel = MediaStorage::relativePath('object', 'a', 'b.pdf');
        $this->assertSame('objects/a/b.pdf', $rel);

        $abs = MediaStorage::absolutePath('objects/test.txt');
        $this->assertStringEndsWith('objects/test.txt', $abs);
        $this->assertSame('', MediaStorage::absolutePath('../escape'));
    }
}
