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
use Moncine\MediaDomain;
use Moncine\RessentiNote;
use Moncine\SocialRessentiService;
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
        $this->assertSame(3, $hist->getBestRessentiScore($libraryId));
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

        $wishlist = (new FilmRepository())->findAllWishlist();
        $this->assertSame([], $wishlist);
    }

    public function testSocialRessentisAcrossMembers(): void
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
        $this->assertSame(3, (int) ($films[0]['note_max'] ?? 0));

        $social = (new SocialRessentiService())->listAroundOeuvre(
            $oeuvreId,
            MediaDomain::FILM,
            $memberId,
            $foyerId
        );
        $scores = array_column($social['foyer'], 'ressenti_score', 'user_id');
        $this->assertSame(RessentiNote::scoreFromLegacyTen(8), $scores[$adminId] ?? null);
        $this->assertSame(3, $scores[$memberId] ?? null);
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
        // create() garantit déjà un foyer personnel.
        $existingFoyerId = $foyerRepo->currentFoyerIdForUser($userId);
        $this->assertGreaterThan(0, $existingFoyerId);

        $foyerId = $foyerRepo->ensurePersonalFoyerForUser($userId);
        $this->assertSame($existingFoyerId, $foyerId);
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
