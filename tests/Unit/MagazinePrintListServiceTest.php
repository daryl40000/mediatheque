<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\LibraryStatut;
use Moncine\MagazinePrintListService;
use Moncine\MagazineRepository;
use PHPUnit\Framework\TestCase;

final class MagazinePrintListServiceTest extends TestCase
{
    public function testParamsFromQueryDefaults(): void
    {
        $params = MagazinePrintListService::paramsFromQuery([
            'series_id' => '12',
        ]);

        $this->assertSame(LibraryStatut::COLLECTION, $params['statut']);
        $this->assertSame('numero_ordre', $params['sortBy']);
        $this->assertSame('desc', $params['sortDir']);
        $this->assertSame('', $params['searchQuery']);
        $this->assertSame(MagazineRepository::POSSESSION_ALL, $params['possessionFilter']);
    }

    public function testFilterSummaryIncludesPossession(): void
    {
        $summary = MagazinePrintListService::filterSummary([
            'statut' => LibraryStatut::COLLECTION,
            'searchQuery' => '',
            'possessionFilter' => MagazineRepository::POSSESSION_UNOWNED,
        ]);

        $this->assertStringContainsString('non possédés', $summary);
    }
}
