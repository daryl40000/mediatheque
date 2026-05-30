<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\BibliothequeRepository;
use Moncine\FilmRepository;
use Moncine\FoyerRepository;
use Moncine\LibraryStatut;
use Moncine\OeuvreRepository;
use Moncine\Tests\Support\MoncineTestCase;

final class PersonSearchTest extends MoncineTestCase
{
    public function testFindByPersonneIncludesCatalogAndLibraryPresence(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->assertGreaterThan(0, $foyerId);

        $repo = new FilmRepository();
        $this->assertTrue($repo->usesCatalogModel());

        $sharedDirector = 'Réalisateur Partagé Test';
        $ownedId = $this->seedCatalogOeuvre('Film Possédé Personne', $sharedDirector, ['acteur_1' => 'Acteur A']);
        $wishId = $this->seedCatalogOeuvre('Film Envie Personne', $sharedDirector, ['acteur_1' => 'Acteur B']);
        $catalogOnlyId = $this->seedCatalogOeuvre('Film Catalogue Seul', $sharedDirector, ['acteur_1' => 'Acteur C']);

        $bib = new BibliothequeRepository();
        $bib->insert($adminId, $foyerId, $ownedId, ['statut' => LibraryStatut::COLLECTION]);
        $bib->insert($adminId, $foyerId, $wishId, ['statut' => LibraryStatut::WISHLIST]);

        $results = $repo->findByPersonne($sharedDirector);
        $this->assertCount(3, $results);

        $byOeuvre = [];
        foreach ($results as $row) {
            $byOeuvre[(int) ($row['oeuvre_id'] ?? 0)] = (string) ($row['library_presence'] ?? '');
        }

        $this->assertSame(LibraryStatut::COLLECTION, $byOeuvre[$ownedId] ?? '');
        $this->assertSame(LibraryStatut::WISHLIST, $byOeuvre[$wishId] ?? '');
        $this->assertSame('none', $byOeuvre[$catalogOnlyId] ?? '');

        $suggestions = $repo->distinctPersonnes(500);
        $this->assertContains($sharedDirector, $suggestions);
    }

    public function testDistinctPersonnesIncludesCatalogWithoutOwnedCopy(): void
    {
        $this->loginAsAdmin();
        $uniqueDirector = 'Réalisateur Catalogue Seul ' . bin2hex(random_bytes(3));
        $this->seedCatalogOeuvre('Œuvre Orpheline', $uniqueDirector);

        $oeuvre = (new OeuvreRepository())->findByTitreAndRealisateur('Œuvre Orpheline', $uniqueDirector);
        $this->assertNotNull($oeuvre);

        $suggestions = (new FilmRepository())->distinctPersonnes(500);
        $this->assertContains($uniqueDirector, $suggestions);
    }
}
