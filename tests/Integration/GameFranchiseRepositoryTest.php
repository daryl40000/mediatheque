<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameFranchiseRepository;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GameFranchiseRepositoryTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testListAndFindFranchiseGames(): void
    {
        if (!GameFranchiseRepository::isAvailable()) {
            $this->markTestSkipped('Colonne franchise absente.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $franchiseRepo = new GameFranchiseRepository();

        $bib1 = $gameRepo->createWithLibrary([
            'titre' => 'Witcher 3 Franchise Test',
            'annee' => 2015,
            'platform' => 'pc',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bib1);

        $bib2 = $gameRepo->createWithLibrary([
            'titre' => 'Witcher 2 Franchise Test',
            'annee' => 2011,
            'platform' => 'pc',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bib2);

        $franchiseRepo->assignGamesToFranchise([(int) $bib2, (int) $bib1], 'The Witcher', $foyerId);

        $list = $franchiseRepo->listFranchisesWithCounts($foyerId);
        $this->assertNotEmpty($list);
        $witcher = null;
        foreach ($list as $item) {
            if ($item['franchise'] === 'The Witcher') {
                $witcher = $item;
                break;
            }
        }
        $this->assertNotNull($witcher);
        $this->assertSame(2, $witcher['game_count']);
        $this->assertArrayHasKey('poster_url', $witcher);

        $games = $franchiseRepo->findByFranchise($foyerId, $userId, 'The Witcher');
        $this->assertCount(2, $games);
        $this->assertSame('Witcher 2 Franchise Test', $games[0]['titre']);
        $this->assertSame('Witcher 3 Franchise Test', $games[1]['titre']);

        $oeuvreId1 = (int) ($games[0]['oeuvre_id'] ?? 0);
        $catalogGames = $franchiseRepo->listCatalogByFranchise('The Witcher', $oeuvreId1);
        $this->assertCount(1, $catalogGames);
        $this->assertSame('Witcher 3 Franchise Test', $catalogGames[0]['titre']);
    }

    public function testRenameFranchiseInCollection(): void
    {
        if (!GameFranchiseRepository::isAvailable()) {
            $this->markTestSkipped('Colonne franchise absente.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $franchiseRepo = new GameFranchiseRepository();

        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'GTA V Franchise Test',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $franchiseRepo->assignGamesToFranchise([(int) $bibId], 'Grand Theft Auto', $foyerId);

        $result = $franchiseRepo->renameFranchise('Grand Theft Auto', 'GTA', $foyerId);
        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['updated']);

        $games = $franchiseRepo->findByFranchise($foyerId, $userId, 'GTA');
        $this->assertCount(1, $games);
        $this->assertSame('GTA V Franchise Test', $games[0]['titre']);
    }

    public function testListKnownSagasReturnsDistinctCatalogNames(): void
    {
        if (!GameFranchiseRepository::isAvailable()) {
            $this->markTestSkipped('Colonne franchise absente.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();
        $franchiseRepo = new GameFranchiseRepository();

        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Saga Alpha Test Game',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $franchiseRepo->assignGamesToFranchise([(int) $bibId], 'Saga Alpha Test', $foyerId);

        $known = $franchiseRepo->listKnownSagas();
        $this->assertContains('Saga Alpha Test', $known);
    }
}
