<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\StoreLinkEnricher;
use PHPUnit\Framework\TestCase;

final class StoreLinkEnricherTest extends TestCase
{
    public function testIsAvailableReturnsBool(): void
    {
        $this->assertIsBool(StoreLinkEnricher::isAvailable());
    }

    public function testEnrichOeuvreRejectsInvalidId(): void
    {
        $enricher = new StoreLinkEnricher();
        $result = $enricher->enrichOeuvre(0, ['gog']);

        $this->assertSame(0, $result['linked']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testEnrichBatchRejectsEmptyStores(): void
    {
        $enricher = new StoreLinkEnricher();
        $result = $enricher->enrichBatch([], 5);

        $this->assertSame(0, $result['processed']);
        $this->assertNotEmpty($result['errors']);
    }
}
