<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ImportPostersZip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportPostersZipTest extends TestCase
{
    #[DataProvider('entryNameProvider')]
    public function testParsePosterEntryName(?int $expectedId, ?string $entry): void
    {
        if ($expectedId === null) {
            $this->assertNull(ImportPostersZip::parsePosterEntryName((string) $entry));

            return;
        }

        $parsed = ImportPostersZip::parsePosterEntryName((string) $entry);
        $this->assertNotNull($parsed);
        $this->assertSame($expectedId, $parsed[0]);
    }

    /** @return list<array{0: int|null, 1: string}> */
    public static function entryNameProvider(): array
    {
        return [
            [42, 'posters/42.jpg'],
            [7, 'posters/7.png'],
            [1, '1.webp'],
            [99, './posters/99.jpeg'],
            [null, 'posters/not-a-number.jpg'],
            [null, '../etc/passwd'],
            [null, 'posters/42.txt'],
            [null, 'readme.txt'],
        ];
    }
}
