<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CollectionViewMode;
use Moncine\FilmCollectionPagination;
use PHPUnit\Framework\TestCase;

final class FilmCollectionPaginationTest extends TestCase
{
    public function testPerPageByViewMode(): void
    {
        $this->assertSame(56, FilmCollectionPagination::perPage(CollectionViewMode::GRID));
        $this->assertSame(0, FilmCollectionPagination::perPage(CollectionViewMode::SHELF));
        $this->assertSame(100, FilmCollectionPagination::perPage(CollectionViewMode::LIST));
    }

    public function testShelfShowsEntireCollectionOnOnePage(): void
    {
        $this->assertFalse(FilmCollectionPagination::usesPagination(CollectionViewMode::SHELF));
        $this->assertTrue(FilmCollectionPagination::usesPagination(CollectionViewMode::GRID));

        $resolved = FilmCollectionPagination::resolve(5, 240, CollectionViewMode::SHELF);

        $this->assertSame(1, $resolved['page']);
        $this->assertSame(240, $resolved['perPage']);
        $this->assertSame(0, $resolved['offset']);
        $this->assertSame(1, $resolved['totalPages']);
    }

    public function testResolveClampsPageAndComputesOffset(): void
    {
        $resolved = FilmCollectionPagination::resolve(99, 120, CollectionViewMode::LIST);

        $this->assertSame(2, $resolved['page']);
        $this->assertSame(100, $resolved['perPage']);
        $this->assertSame(100, $resolved['offset']);
        $this->assertSame(2, $resolved['totalPages']);
    }
}
