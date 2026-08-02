<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameCollectionStats;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GameCollectionStatsRemakesTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testRemakeCardAndPairsWithOwnedAndUnownedCovers(): void
    {
        if (!GameRepository::hasRemakeColumns()) {
            $this->markTestSkipped('Colonnes remakes non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();
        $suffix = uniqid('remake_stats_', true);

        // Original possédé, remake catalogue non possédé → paire avec remake grisé.
        $originalBibId = $repo->createWithLibrary([
            'titre' => 'Original Possede ' . $suffix,
            'platform' => GamePlatform::PS5,
            'annee' => 1996,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($originalBibId);
        $originalGame = $repo->findByBibId($originalBibId, $userId, $foyerId);
        $this->assertNotNull($originalGame);
        $originalOeuvreId = (int) $originalGame['oeuvre_id'];

        $unownedRemakeOeuvreId = $repo->createCatalogOnly([
            'titre' => 'Remake Non Possede ' . $suffix,
            'platform' => GamePlatform::PS5,
            'annee' => 2019,
            'is_remake' => true,
            'original_game_oeuvre_id' => $originalOeuvreId,
        ]);
        $this->assertIsInt($unownedRemakeOeuvreId);

        // Remake possédé, original catalogue non possédé → original grisé.
        $catalogOriginalOeuvreId = $repo->createCatalogOnly([
            'titre' => 'Original Catalogue ' . $suffix,
            'platform' => GamePlatform::PC,
            'annee' => 2001,
        ]);
        $this->assertIsInt($catalogOriginalOeuvreId);

        $ownedRemakeBibId = $repo->createWithLibrary([
            'titre' => 'Remake Possede ' . $suffix,
            'platform' => GamePlatform::PC,
            'annee' => 2020,
            'is_remake' => true,
            'original_game_oeuvre_id' => $catalogOriginalOeuvreId,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($ownedRemakeBibId);

        $stats = new GameCollectionStats();
        $dashboard = $stats->getDashboard($userId, $foyerId);
        $this->assertGreaterThanOrEqual(2, (int) ($dashboard['remake_count'] ?? 0));

        $pairs = $stats->listRemakePairs($userId, $foyerId);
        $byRemakeTitle = [];
        foreach ($pairs as $pair) {
            $title = (string) ($pair['remake']['titre'] ?? '');
            $byRemakeTitle[$title] = $pair;
        }

        $this->assertArrayHasKey('Remake Non Possede ' . $suffix, $byRemakeTitle);
        $unownedPair = $byRemakeTitle['Remake Non Possede ' . $suffix];
        $this->assertFalse($unownedPair['remake']['in_library']);
        $this->assertNotNull($unownedPair['original']);
        $this->assertTrue($unownedPair['original']['in_library']);
        $this->assertNotSame('', (string) $unownedPair['remake']['url']);
        $this->assertStringContainsString('oeuvre-jeu.php', (string) $unownedPair['remake']['url']);

        $this->assertArrayHasKey('Remake Possede ' . $suffix, $byRemakeTitle);
        $ownedPair = $byRemakeTitle['Remake Possede ' . $suffix];
        $this->assertTrue($ownedPair['remake']['in_library']);
        $this->assertNotNull($ownedPair['original']);
        $this->assertFalse($ownedPair['original']['in_library']);
        $this->assertStringContainsString('jeu.php', (string) $ownedPair['remake']['url']);
    }
}
