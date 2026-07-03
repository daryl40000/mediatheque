<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameCatalogUpdater;
use Moncine\GamePlatform;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\SchemaMigrator;

final class GameCatalogUpdaterTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        $this->loginAsAdmin();
    }

    public function testUpdateByOeuvreIdRejectsEmptyTitle(): void
    {
        $repo = new \Moncine\GameRepository();
        $bibId = $repo->createWithLibrary([
            'titre' => 'Jeu Updater Test',
            'platform' => GamePlatform::PC,
        ], \Moncine\LibraryStatut::COLLECTION, \Moncine\UserContext::currentUserId(), \Moncine\UserContext::currentFoyerId());
        $this->assertIsInt($bibId);

        $game = $repo->findByBibId($bibId, \Moncine\UserContext::currentUserId(), \Moncine\UserContext::currentFoyerId());
        $this->assertNotNull($game);
        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);

        $result = (new GameCatalogUpdater(\Moncine\Database::getInstance()))->updateByOeuvreId($oeuvreId, [
            'titre' => '',
            'titre_original' => '',
            'platform' => GamePlatform::PC,
        ]);

        $this->assertSame('Le titre est obligatoire.', $result);
    }
}
