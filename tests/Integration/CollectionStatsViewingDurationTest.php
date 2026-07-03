<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CollectionStats;
use Moncine\FilmRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;

final class CollectionStatsViewingDurationTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
    }

    public function testTotalViewingMinutesCountsRewatches(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Durée Stats', 'Réal Stats', ['duree_min' => 90]);
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION);
        $this->assertIsInt($filmId);

        $history = new HistoriqueRepository();
        $history->recordViewing($filmId, '2024-01-10');
        $history->recordViewing($filmId, '2025-06-01');

        $stats = (new CollectionStats())->getDashboard();
        $this->assertSame(180, (int) ($stats['viewing_minutes_total'] ?? 0));
        $this->assertSame('3h 00min', (string) ($stats['viewing_duration_label'] ?? ''));
    }

    public function testTopAdoredFilmsListsFilmsWithBestRessentiAdore(): void
    {
        $this->loginAsAdmin();
        $oeuvreId = $this->seedCatalogOeuvre('Film Coup de Coeur', 'Réal Adore');
        $filmId = (new FilmRepository())->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION);
        $this->assertIsInt($filmId);

        $history = new HistoriqueRepository();
        $history->recordViewing($filmId, '2024-01-10', 5);
        $history->recordViewing($filmId, '2024-06-01', 3);

        $stats = (new CollectionStats())->getDashboard();
        $this->assertSame(1, (int) ($stats['coups_de_coeur_count'] ?? 0));
        $top = $stats['coups_de_coeur'] ?? [];
        $this->assertCount(1, $top);
        $this->assertSame('Film Coup de Coeur', $top[0]['titre'] ?? '');
        $this->assertSame(5, (int) ($top[0]['best_note'] ?? 0));
    }
}
