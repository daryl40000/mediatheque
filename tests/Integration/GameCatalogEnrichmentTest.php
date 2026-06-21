<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameCatalogEnrichment;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\OeuvreRepository;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GameCatalogEnrichmentTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testKeepPosterPreservesExistingJaquette(): void
    {
        if (!GameRepository::hasIgdbColumns()) {
            $this->markTestSkipped('Colonnes IGDB absentes.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Jeu Keep Poster Test',
            'annee' => 2020,
            'poster_url' => '/posters/existing-keep.jpg',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $oeuvreId = (int) $game['oeuvre_id'];

        (new GameCatalogEnrichment())->updateEnrichmentMetadata($oeuvreId, [
            'poster_url' => 'https://images.igdb.com/igdb/image/upload/t_cover_big/new.jpg',
            'studio' => 'Studio IGDB',
            'annee' => 2021,
        ], false, true);

        $updated = $repo->findCatalogByOeuvreId($oeuvreId);
        $this->assertNotNull($updated);
        $this->assertSame('/posters/existing-keep.jpg', $updated['poster_url']);
        $this->assertSame('Studio IGDB', $updated['studio']);
        $this->assertSame(2020, (int) $updated['annee']);
    }

    public function testWithoutKeepPosterReplacesJaquetteWhenIgdbProvidesOne(): void
    {
        if (!GameRepository::hasIgdbColumns()) {
            $this->markTestSkipped('Colonnes IGDB absentes.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Jeu Replace Poster Test',
            'annee' => 2019,
            'poster_url' => '/posters/old.jpg',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $oeuvreId = (int) $game['oeuvre_id'];

        $remotePoster = 'https://images.igdb.com/igdb/image/upload/t_cover_big/replacement.jpg';
        (new GameCatalogEnrichment())->updateEnrichmentMetadata($oeuvreId, [
            'poster_url' => $remotePoster,
        ], false, false);

        $updated = (new OeuvreRepository())->findByIdForAdmin($oeuvreId);
        $this->assertNotNull($updated);
        $this->assertNotSame('/posters/old.jpg', $updated['poster_url']);
        $this->assertNotSame('', trim((string) $updated['poster_url']));
    }
}
