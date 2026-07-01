<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\BdPrintListService;
use Moncine\LibraryStatut;
use PHPUnit\Framework\TestCase;

final class BdPrintListServiceTest extends TestCase
{
    public function testFilterSummaryCollection(): void
    {
        $summary = BdPrintListService::filterSummary([
            'statut' => LibraryStatut::COLLECTION,
            'searchQuery' => '',
        ]);

        $this->assertStringContainsString('Collection du foyer', $summary);
    }

    public function testSortSummaryTome(): void
    {
        $summary = BdPrintListService::sortSummary('tome', 'asc');

        $this->assertStringContainsString('Tome', $summary);
        $this->assertStringContainsString('croissant', $summary);
    }
}
