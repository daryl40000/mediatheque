<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\CatalogSchema;
use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;

final class FilmSagaCatalogTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::FILM);
        $this->loginAsAdmin();
    }

    public function testUserInheritsCatalogSagaWhenAddingFilmFromCatalog(): void
    {
        if (!CatalogSchema::hasOeuvreSagaColumns()) {
            $this->markTestSkipped('Colonnes saga catalogue absentes.');
        }

        $adminRepo = new FilmRepository();

        $bib1 = $adminRepo->createManual([
            'titre' => 'Saga Film Alpha 1',
            'realisateur' => 'Test Réalisateur',
            'annee' => 2001,
        ], LibraryStatut::COLLECTION);
        $this->assertIsInt($bib1);

        $bib2 = $adminRepo->createManual([
            'titre' => 'Saga Film Alpha 2',
            'realisateur' => 'Test Réalisateur',
            'annee' => 2003,
        ], LibraryStatut::COLLECTION);
        $this->assertIsInt($bib2);

        $assigned = $adminRepo->assignFilmsToSaga([(int) $bib1, (int) $bib2], 'Saga Alpha Films', 1);
        $this->assertSame(2, $assigned);

        $film2 = $adminRepo->findById((int) $bib2);
        $this->assertNotNull($film2);
        $oeuvreId = (int) ($film2['oeuvre_id'] ?? 0);
        $this->assertGreaterThan(0, $oeuvreId);

        Auth::logout();
        $this->startSession();
        $this->loginAsUser('sagauser@test.local');

        $userRepo = new FilmRepository();
        $added = $userRepo->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION);
        $this->assertIsInt($added);

        $filmsInSaga = $userRepo->findBySaga('Saga Alpha Films');
        $this->assertCount(1, $filmsInSaga);
        $this->assertSame('Saga Film Alpha 2', $filmsInSaga[0]['titre']);
        $this->assertSame('Saga Alpha Films', trim((string) ($filmsInSaga[0]['saga'] ?? '')));

        $sagas = $userRepo->listSagasWithCounts();
        $found = null;
        foreach ($sagas as $item) {
            if ($item['saga'] === 'Saga Alpha Films') {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(1, $found['film_count']);

        $this->assertContains('Saga Alpha Films', $userRepo->listKnownSagas());
    }
}
