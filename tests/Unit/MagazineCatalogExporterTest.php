<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineCatalogExporter;
use Moncine\MagazineRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use PHPUnit\Framework\TestCase;

final class MagazineCatalogExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MediaContext::set(MediaDomain::MAGAZINE);
    }

    public function testExportIncludesSeriesAndIssues(): void
    {
        if (!MagazineRepository::isAvailable()) {
            $this->markTestSkipped('Module magazines indisponible.');
        }

        $titre = 'Export Test ' . uniqid();
        $seriesId = (new SeriesRepository())->create([
            'titre' => $titre,
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $oeuvreId = (new MagazineRepository())->createCatalogIssue($seriesId, [
            'numero' => '9',
            'numero_ordre' => 9,
            'date_parution' => '2021-09-01',
            'series_titre' => $titre,
        ]);
        $this->assertIsInt($oeuvreId);

        $export = (new MagazineCatalogExporter())->exportToArray([$titre]);
        $this->assertSame(1, $export['stats']['series_count'] ?? 0);
        $this->assertSame(1, $export['stats']['issue_count'] ?? 0);
        $this->assertSame($titre, $export['series'][0]['titre'] ?? '');
        $this->assertSame('9', $export['series'][0]['issues'][0]['numero'] ?? '');
    }
}
