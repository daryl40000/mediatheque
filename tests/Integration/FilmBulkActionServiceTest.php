<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Service\FilmBulkActionService;
use Moncine\SupportPhysique;
use Moncine\Tests\Support\MoncineTestCase;

/**
 * Tests du service avec la vraie base (FilmRepository est final, pas de mock PHPUnit).
 *
 * Scénarios regroupés dans un seul test : le cache foyer de UserContext survit au reset SQLite
 * entre deux méthodes de test et provoquerait des erreurs de clé étrangère.
 */
final class FilmBulkActionServiceTest extends MoncineTestCase
{
    private FilmRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::FILM);
        $this->loginAsAdmin();
        $this->repo = new FilmRepository();
    }

    public function testBulkActionsWithRealRepository(): void
    {
        $service = new FilmBulkActionService($this->repo);

        $bib1 = $this->createFilm('Bulk Saga 1');
        $bib2 = $this->createFilm('Bulk Saga 2');

        $sagaResult = $service->handleBulkAction('assign_saga', [$bib1, $bib2], [
            'saga_new' => 'Ma Saga Bulk',
            'saga_ordre_start' => 3,
        ]);
        $this->assertSame(2, $sagaResult['bulk_ok']);
        $this->assertStringContainsString('Ma Saga Bulk', (string) $sagaResult['bulk_msg']);
        $this->assertSame('Ma Saga Bulk', $sagaResult['saga_name']);

        $film1 = $this->repo->findById($bib1);
        $this->assertNotNull($film1);
        $this->assertSame('Ma Saga Bulk', trim((string) ($film1['saga'] ?? '')));
        $this->assertSame(3, (int) ($film1['saga_ordre'] ?? 0));

        $bibSupport = $this->createFilm('Bulk Support Clear');
        $this->repo->updateFilmsSupportPhysique([$bibSupport], SupportPhysique::DVD);

        $supportResult = $service->handleBulkAction('set_support', [$bibSupport], [
            'bulk_support_physique' => '',
        ]);
        $this->assertSame(1, $supportResult['bulk_ok']);
        $this->assertStringContainsString('Non renseigné', (string) $supportResult['bulk_msg']);

        $filmSupport = $this->repo->findById($bibSupport);
        $this->assertNotNull($filmSupport);
        $this->assertSame('', trim((string) ($filmSupport['support_physique'] ?? '')));

        $bibDel1 = $this->createFilm('Bulk Delete 1');
        $bibDel2 = $this->createFilm('Bulk Delete 2');

        $deleteResult = $service->handleBulkAction('delete_films', [$bibDel1, $bibDel2], []);
        $this->assertSame(2, $deleteResult['bulk_ok']);
        $this->assertStringContainsString('supprimé', (string) $deleteResult['bulk_msg']);
        $this->assertNull($this->repo->findById($bibDel1));
        $this->assertNull($this->repo->findById($bibDel2));
    }

    private function createFilm(string $titre): int
    {
        $bibId = $this->repo->createManual([
            'titre' => $titre,
            'realisateur' => 'Test',
            'annee' => 2020,
        ], LibraryStatut::COLLECTION);
        $this->assertIsInt($bibId);

        return $bibId;
    }
}
