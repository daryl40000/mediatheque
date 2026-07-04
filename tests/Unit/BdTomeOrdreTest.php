<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\BdCatalogWriter;
use Moncine\BdTomeOrdre;
use Moncine\Database;
use Moncine\SchemaMigrator;
use PHPUnit\Framework\TestCase;

final class BdTomeOrdreTest extends TestCase
{
    public function testResolveUsesTomeNumeroWhenOrdreMissing(): void
    {
        $this->assertSame(3.0, BdTomeOrdre::resolve([
            'tome_numero' => 3,
            'est_hors_serie' => false,
        ], 0));
    }

    public function testResolveAddsHalfForHorsSerie(): void
    {
        $this->assertSame(5.5, BdTomeOrdre::resolve([
            'tome_numero' => 5,
            'tome_ordre' => 5,
            'est_hors_serie' => true,
        ], 0));
    }

    public function testPrepareCatalogTomeFieldsUsesExplicitTomeZero(): void
    {
        (new SchemaMigrator(Database::getInstance()))->runPendingMigrations();

        $fields = (new BdCatalogWriter(Database::getInstance()))->prepareCatalogTomeFields([
            'tome_numero' => 0,
            'est_hors_serie' => false,
        ], 0, null);

        $this->assertSame(0, $fields['tome_numero']);
        $this->assertSame(0.0, $fields['tome_ordre']);
    }
}
