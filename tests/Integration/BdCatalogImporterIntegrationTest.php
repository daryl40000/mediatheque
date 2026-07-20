<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BdCatalogImporter;
use Moncine\BdRepository;
use Moncine\MediaDomain;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class BdCatalogImporterIntegrationTest extends MoncineTestCase
{
    public function testImportCreatesSeriesAndTomes(): void
    {
        $this->loginAsAdmin();
        $this->assertTrue(BdRepository::isAvailable());

        $csv = "serie;kind;tome_numero;titre;annee;scenariste\n"
            . "Test Import BD;bd;1;Tome Un;2001;Auteur A\n"
            . "Test Import BD;bd;2;Tome Deux;2002;Auteur A\n";
        $tmp = tempnam(sys_get_temp_dir(), 'bdcsv');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, $csv);

        $result = (new BdCatalogImporter())->importFromPath($tmp, [
            'dry_run' => false,
            'skip_existing' => true,
        ]);
        unlink($tmp);

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['series_created']);
        $this->assertSame(2, $result['tomes_created']);

        $series = (new SeriesRepository())->findByTitre('Test Import BD', MediaDomain::BD);
        $this->assertNotNull($series);
        $seriesId = (int) $series['id'];

        $repo = new BdRepository();
        $this->assertNotNull($repo->findCatalogTomeId($seriesId, 1, false));
        $this->assertNotNull($repo->findCatalogTomeId($seriesId, 2, false));

        // Second import : skip
        $tmp2 = tempnam(sys_get_temp_dir(), 'bdcsv');
        $this->assertNotFalse($tmp2);
        file_put_contents($tmp2, $csv);
        $second = (new BdCatalogImporter())->importFromPath($tmp2, ['skip_existing' => true]);
        unlink($tmp2);

        $this->assertSame(0, $second['tomes_created']);
        $this->assertSame(2, $second['tomes_skipped']);
        $this->assertSame(1, $second['series_reused']);
    }
}
