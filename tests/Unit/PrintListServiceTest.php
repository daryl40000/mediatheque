<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\ContentKindFilter;
use Moncine\PrintListHelper;
use Moncine\PrintListService;
use Moncine\WishlistScope;
use PHPUnit\Framework\TestCase;

final class PrintListServiceTest extends TestCase
{
    public function testCollectionParamsFromQuery(): void
    {
        $params = PrintListService::collectionParamsFromQuery([
            'sort' => 'annee',
            'dir' => 'desc',
            'q' => '  matrix  ',
            'kind' => 'film',
        ]);

        $this->assertSame('annee', $params['sortBy']);
        $this->assertSame('desc', $params['sortDir']);
        $this->assertSame('matrix', $params['query']);
        $this->assertSame(ContentKindFilter::FILM, $params['kindFilter']);
    }

    public function testWishlistParamsDefaultsToVotesForGroupWithoutSort(): void
    {
        $params = PrintListService::wishlistParamsFromQuery(
            ['scope' => WishlistScope::GROUP],
            true
        );

        $this->assertTrue($params['isGroupScope']);
        $this->assertSame('votes', $params['sortBy']);
        $this->assertSame('desc', $params['sortDir']);
    }

    public function testWishlistParamsFallsBackToMineWhenGroupUnavailable(): void
    {
        $params = PrintListService::wishlistParamsFromQuery(
            ['scope' => WishlistScope::GROUP],
            false
        );

        $this->assertFalse($params['isGroupScope']);
        $this->assertSame(WishlistScope::MINE, $params['scope']);
    }

    public function testSortSummary(): void
    {
        $this->assertSame('Titre (croissant)', PrintListHelper::sortSummary('titre', 'asc'));
    }

    public function testRowLimitTruncates(): void
    {
        $rows = array_fill(0, PrintListService::MAX_ROWS + 10, ['id' => 1]);
        $ref = new \ReflectionClass(PrintListService::class);
        $method = $ref->getMethod('applyRowLimit');
        $method->setAccessible(true);
        $result = $method->invoke(null, $rows);

        $this->assertTrue($result['truncated']);
        $this->assertSame(PrintListService::MAX_ROWS, count($result['rows']));
        $this->assertSame(PrintListService::MAX_ROWS + 10, $result['total']);
    }
}
