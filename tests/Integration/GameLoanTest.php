<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\Auth;
use Moncine\FriendshipRepository;
use Moncine\GamePhysicalSupport;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\LoanRequestRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\SocialMigration;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;
use Moncine\UserRole;
use Moncine\UtilisateurRepository;

final class GameLoanTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = \Moncine\Database::getInstance();
        (new SchemaMigrator($db))->runPendingMigrations();
        SocialMigration::runIfNeeded($db);
        MediaContext::set(MediaDomain::JEU);
    }

    public function testFriendCanRequestPhysicalGameLoan(): void
    {
        $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        $ownerId = UserContext::currentUserId();

        $repo = new GameRepository();
        $bibId = $repo->createWithLibrary([
            'titre' => 'Jeu Prêt Physique Test',
            'annee' => 2020,
            'platform' => GamePlatform::PS5,
            'physical_supports' => GamePhysicalSupport::CD_DVD,
        ], LibraryStatut::COLLECTION, $ownerId, $foyerId);
        $this->assertIsInt($bibId);

        Auth::logout();
        $this->startSession();

        $users = new UtilisateurRepository();
        $borrowerId = $users->create('Emprunteur Jeu', 'borrower-game@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($borrowerId);
        Auth::login('borrower-game@test.local', 'TestPass123!');

        $friendRepo = new FriendshipRepository();
        $requestId = $friendRepo->sendRequest($borrowerId, $ownerId);
        $this->assertIsInt($requestId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');
        $this->assertTrue($friendRepo->acceptRequest($requestId, $ownerId) === true);

        Auth::logout();
        $this->startSession();
        Auth::login('borrower-game@test.local', 'TestPass123!');

        $loanRequests = new LoanRequestRepository();
        $result = $loanRequests->requestLoan((int) $bibId, $borrowerId, $ownerId);
        $this->assertIsInt($result);
    }

    public function testDigitalOnlyGameLoanIsRejected(): void
    {
        $this->loginAsAdmin();
        $foyerId = UserContext::currentFoyerId();
        $ownerId = UserContext::currentUserId();

        $repo = new GameRepository();
        $bibId = $repo->createWithLibrary([
            'titre' => 'Jeu Démat Seul Test',
            'annee' => 2021,
            'platform' => GamePlatform::PC,
            'physical_supports' => '',
            'digital_stores' => 'steam',
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $ownerId, $foyerId);
        $this->assertIsInt($bibId);

        Auth::logout();
        $this->startSession();

        $users = new UtilisateurRepository();
        $borrowerId = $users->create('Emprunteur Démat', 'borrower-digital@test.local', 'TestPass123!', UserRole::USER);
        $this->assertIsInt($borrowerId);
        Auth::login('borrower-digital@test.local', 'TestPass123!');

        $friendRepo = new FriendshipRepository();
        $requestId = $friendRepo->sendRequest($borrowerId, $ownerId);
        $this->assertIsInt($requestId);

        Auth::logout();
        $this->startSession();
        Auth::login('admin@test.local', 'TestPass123!');
        $friendRepo->acceptRequest($requestId, $ownerId);

        Auth::logout();
        $this->startSession();
        Auth::login('borrower-digital@test.local', 'TestPass123!');

        $result = (new LoanRequestRepository())->requestLoan((int) $bibId, $borrowerId, $ownerId);
        $this->assertIsString($result);
        $this->assertStringContainsString('démat', strtolower((string) $result));
    }
}
