<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\CatalogFilmRepository;
use Moncine\FilmLibraryQuery;
use Moncine\FilmPersonQuery;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

/**
 * Smoke tests sur les extractions Phase B films (requêtes + création).
 */
final class FilmLibraryQueryTest extends MoncineTestCase
{
    public function testFindAllAndCountOnEmptyCollection(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);

        $query = new FilmLibraryQuery(\Moncine\Database::getInstance());
        $this->assertSame([], $query->findAll());
        $this->assertSame(0, $query->count());
        $this->assertSame(0, $query->countWishlist());
        $this->assertSame(0, $query->countCollectionFiltered());
    }

    public function testCreateManualThenFindByTitreViaFacade(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);

        $repo = new CatalogFilmRepository();
        $result = $repo->createManual([
            'titre' => 'Film Phase D Query',
            'realisateur' => 'Réalisateur D',
            'annee' => 2024,
            'support_physique' => 'bluray',
        ], LibraryStatut::COLLECTION);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        $found = $repo->findByTitreAndRealisateur('Film Phase D Query', 'Réalisateur D');
        $this->assertNotNull($found);
        $this->assertSame('Film Phase D Query', $found['titre'] ?? null);
        $this->assertSame(1, $repo->count());

        $list = $repo->findAll('titre', 'asc', 'Phase D');
        $this->assertCount(1, $list);
    }

    public function testSearchCatalogOeuvresReturnsCreatedOeuvre(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);

        $oeuvreId = (new OeuvreRepository())->insert([
            'titre' => 'Catalogue Search Phase D',
            'realisateur' => 'Chercheur',
            'annee' => 2021,
            'media_domain' => MediaDomain::FILM,
        ]);
        $this->assertGreaterThan(0, $oeuvreId);

        $query = new FilmLibraryQuery(\Moncine\Database::getInstance());
        $results = $query->searchCatalogOeuvres('Catalogue Search', 10);
        $this->assertNotEmpty($results);
        $labels = array_map(
            static fn (array $row): string => (string) ($row['titre'] ?? ''),
            $results
        );
        $this->assertContains('Catalogue Search Phase D', $labels);
    }

    public function testFormatCatalogOeuvreLabel(): void
    {
        $label = FilmLibraryQuery::formatCatalogOeuvreLabel([
            'titre' => 'Alien',
            'realisateur' => 'Ridley Scott',
            'annee' => 1979,
        ]);
        $this->assertSame('Alien — Ridley Scott (1979)', $label);
    }

    public function testPersonQueryEmptyReturnsEmpty(): void
    {
        $this->loginAsAdmin();
        $persons = new FilmPersonQuery(\Moncine\Database::getInstance());
        $this->assertSame([], $persons->findByPersonne(''));
        $this->assertSame([], $persons->findByPersonne('   '));
    }

    public function testCreatorRejectsEmptyTitle(): void
    {
        $this->loginAsAdmin();
        MediaContext::set(MediaDomain::FILM);

        $repo = new CatalogFilmRepository();
        $result = $repo->createManual(['titre' => ''], LibraryStatut::COLLECTION);
        $this->assertIsString($result);
        $this->assertStringContainsString('titre', strtolower($result));
    }
}
