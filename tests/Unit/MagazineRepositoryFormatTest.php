<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineRepository;
use PHPUnit\Framework\TestCase;

final class MagazineRepositoryFormatTest extends TestCase
{
    public function testFormatPdfStorageGigabytes(): void
    {
        $this->assertSame('0 Go', MagazineRepository::formatPdfStorageGigabytes(0));

        $oneGigabyte = 1024 ** 3;
        $this->assertSame('1,0 Go', MagazineRepository::formatPdfStorageGigabytes($oneGigabyte));

        $small = 100 * 1024;
        $this->assertSame('0 Mo', MagazineRepository::formatPdfStorageGigabytes($small));
    }
}
