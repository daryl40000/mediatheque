<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\CollectionViewMode;
use PHPUnit\Framework\TestCase;

final class CollectionViewModeTest extends TestCase
{
    public function testNormalizeShelfMode(): void
    {
        $this->assertSame(CollectionViewMode::SHELF, CollectionViewMode::normalize('shelf'));
        $this->assertSame(CollectionViewMode::SHELF, CollectionViewMode::normalize('SHELF'));
    }

    public function testQueryValueForShelf(): void
    {
        $this->assertSame('shelf', CollectionViewMode::queryValue(CollectionViewMode::SHELF));
        $this->assertNull(CollectionViewMode::queryValue(CollectionViewMode::LIST));
    }

    public function testGameChoicesIncludeBibliotheque(): void
    {
        $this->assertArrayHasKey(CollectionViewMode::SHELF, CollectionViewMode::choices());
        $this->assertSame(CollectionViewMode::choices(), CollectionViewMode::gameChoices());
    }

    public function testCollectionShelfSpineHeightIsFixed(): void
    {
        $this->assertSame(190, \Moncine\View::collectionShelfSpineHeightPx());
        $this->assertSame(190, \Moncine\View::gameShelfSpineHeightPx());
    }
}
