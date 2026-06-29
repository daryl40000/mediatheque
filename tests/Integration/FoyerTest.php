<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\CatalogFilmRepository;
use Moncine\FilmRepository;
use Moncine\FoyerRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class FoyerTest extends MoncineTestCase
{
    public function testSharedCollectionAndPersonalHistory(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $this->assertGreaterThan(0, $foyerId);

        $oeuvreId = $this->seedCatalogOeuvre('Film partagé');
        $bib = new BibliothequeRepository();
        $libraryId = $bib->insert($adminId, $foyerId, $oeuvreId, ['statut' => LibraryStatut::COLLECTION]);
        $this->assertGreaterThan(0, $libraryId);

        (new HistoriqueRepository())->recordViewing($libraryId, '2024-01-10', 8);

        Auth::logout();
        $this->startSession();

        $userRepo = new UtilisateurRepository();
        $memberId = $userRepo->create(
            'Membre Test',
            'membre@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);
        $this->assertTrue(Auth::login('membre@test.local', 'TestPass123!') === true);

        $shared = (new CatalogFilmRepository())->findById($libraryId);
        $this->assertNotNull($shared);

        $hist = new HistoriqueRepository();
        $this->assertFalse($hist->wasEverSeen($libraryId));
        $hist->recordViewing($libraryId, '2024-02-01', 6);
        $this->assertSame(6, $hist->getNoteSur10($libraryId));
    }

    public function testWishlistRemainsPersonal(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $oeuvreId = $this->seedCatalogOeuvre('Film envie');

        $bib = new BibliothequeRepository();
        $bib->insert($adminId, $foyerId, $oeuvreId, ['statut' => LibraryStatut::WISHLIST]);

        Auth::logout();
        $this->startSession();

        $memberId = (new UtilisateurRepository())->create(
            'Autre membre',
            'autre@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);
        Auth::login('autre@test.local', 'TestPass123!');

        $entry = $bib->findByOeuvreId(
            $oeuvreId,
            $memberId,
            $foyerId,
            LibraryStatut::WISHLIST
        );
        $this->assertNull($entry);
    }

    public function testMemberCanOpenFilmsAndWishlistPages(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $oeuvreId = $this->seedCatalogOeuvre('Film liste');
        (new BibliothequeRepository())->insert(
            $adminId,
            $foyerId,
            $oeuvreId,
            ['statut' => LibraryStatut::COLLECTION]
        );

        Auth::logout();
        $this->startSession();

        $memberId = (new UtilisateurRepository())->create(
            'Membre liste',
            'liste@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);
        Auth::login('liste@test.local', 'TestPass123!');

        $films = (new FilmRepository())->findAll();
        $this->assertCount(1, $films);
        $this->assertNull($films[0]['note_max'] ?? null);
        $this->assertNull($films[0]['note_foyer_moy'] ?? null);

        $wishlist = (new FilmRepository())->findAllWishlist();
        $this->assertSame([], $wishlist);
    }

    public function testFoyerAverageRatingAcrossMembers(): void
    {
        $adminId = $this->loginAsAdmin();
        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($adminId);
        $oeuvreId = $this->seedCatalogOeuvre('Film noté');
        $libraryId = (new BibliothequeRepository())->insert(
            $adminId,
            $foyerId,
            $oeuvreId,
            ['statut' => LibraryStatut::COLLECTION]
        );

        (new HistoriqueRepository())->recordViewing($libraryId, '2024-01-10', 8);

        Auth::logout();
        $this->startSession();

        $memberId = (new UtilisateurRepository())->create(
            'Noteur',
            'noteur@test.local',
            'TestPass123!',
            UserRole::USER,
            $foyerId
        );
        $this->assertIsInt($memberId);
        Auth::login('noteur@test.local', 'TestPass123!');
        (new HistoriqueRepository())->recordViewing($libraryId, '2024-02-01', 6);

        $films = (new FilmRepository())->findAll();
        $this->assertCount(1, $films);
        $this->assertSame(6, (int) ($films[0]['note_max'] ?? 0));
        $this->assertSame(7.0, (float) ($films[0]['note_foyer_moy'] ?? 0));

        $hist = new HistoriqueRepository();
        $this->assertSame(7.0, $hist->getFoyerAverageNote($libraryId));
    }

    public function testEnsurePersonalFoyerForSoloUser(): void
    {
        $this->loginAsAdmin();
        Auth::logout();
        $this->startSession();

        $userId = (new UtilisateurRepository())->create(
            'Solo Test',
            'solo-foyer@test.local',
            'TestPass123!',
            UserRole::USER,
            0
        );
        $this->assertIsInt($userId);

        $foyerRepo = new FoyerRepository();
        $this->assertSame(0, $foyerRepo->currentFoyerIdForUser($userId));

        $foyerId = $foyerRepo->ensurePersonalFoyerForUser($userId);
        $this->assertGreaterThan(0, $foyerId);
        $this->assertSame($foyerId, $foyerRepo->currentFoyerIdForUser($userId));

        $oeuvreId = $this->seedCatalogOeuvre('Film solo foyer');
        $libraryId = (new BibliothequeRepository())->insert(
            $userId,
            0,
            $oeuvreId,
            ['statut' => LibraryStatut::COLLECTION]
        );
        $this->assertGreaterThan(0, $libraryId);
    }
}
