<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameDigitalStore;
use Moncine\GameListFilter;
use Moncine\GamePhysicalSupport;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\GameSchema;
use Moncine\GameSteamStatsRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazineRepository;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SchemaMigrator;
use Moncine\SeriesRepository;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GameRepositoryTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testCreateGameAndListInCollection(): void
    {
        $this->assertTrue(GameRepository::isAvailable());

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Elden Ring Test',
            'annee' => 2022,
            'studio' => 'FromSoftware',
            'platform' => GamePlatform::PS5,
            'genre' => 'Action-RPG',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $games = $repo->listInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $games);
        $this->assertSame('Elden Ring Test', $games[0]['titre']);
        $this->assertSame('PS5', $games[0]['platform_short']);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $this->assertStringContainsString('Elden Ring Test', (string) $game['display_label']);
    }

    public function testSearchCatalogForAutocomplete(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $repo->createWithLibrary([
            'titre' => 'Gran Turismo 7 Test',
            'annee' => 2022,
            'platform' => GamePlatform::PS5,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $results = $repo->searchCatalog('Gran Turismo', 10);
        $this->assertNotEmpty($results);
        $this->assertSame('Gran Turismo 7 Test', $results[0]['titre']);

        $accentResults = $repo->searchCatalog('gran turismo', 10);
        $this->assertNotEmpty($accentResults);
        $this->assertSame('Gran Turismo 7 Test', $accentResults[0]['titre']);

        $typoResults = $repo->searchCatalog('Gran Turisno', 10);
        $this->assertNotEmpty($typoResults);
        $this->assertSame('Gran Turismo 7 Test', $typoResults[0]['titre']);
    }

    public function testSearchCatalogMatchesAcronym(): void
    {
        if (!GameRepository::hasIgdbMetadataColumns()) {
            $this->markTestSkipped('Colonnes métadonnées IGDB absentes.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'The Legend of Zelda Breath of the Wild Acronym Test',
            'annee' => 2017,
            'platform' => GamePlatform::SWITCH,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);

        $update = $repo->updateCatalogByOeuvreId((int) $game['oeuvre_id'], [
            'titre' => (string) $game['titre'],
            'alternative_names' => 'BotW, TLoZ',
        ]);
        $this->assertTrue($update);

        $byAcronym = $repo->searchCatalog('BotW', 10);
        $this->assertNotEmpty($byAcronym);
        $this->assertSame(
            'The Legend of Zelda Breath of the Wild Acronym Test',
            $byAcronym[0]['titre']
        );

        $collection = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            'TLoZ'
        );
        $this->assertCount(1, $collection);
        $this->assertSame('The Legend of Zelda Breath of the Wild Acronym Test', $collection[0]['titre']);
    }

    public function testCollectionStatsDashboard(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $repo->createWithLibrary([
            'titre' => 'Stats Digital Test',
            'platform' => GamePlatform::PC,
            'genre' => 'FPS, Action-RPG',
            'annee' => 2020,
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $repo->createWithLibrary([
            'titre' => 'Stats Physical Test',
            'platform' => GamePlatform::PS5,
            'genre' => 'Action-RPG',
            'annee' => 2022,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $repo->createWithLibrary([
            'titre' => 'Stats Wishlist Test',
            'platform' => GamePlatform::SWITCH,
        ], LibraryStatut::WISHLIST, $userId, $foyerId);

        $stats = (new \Moncine\GameCollectionStats())->getDashboard($userId, $foyerId);

        $this->assertSame(2, $stats['collection_count']);
        $this->assertSame(1, $stats['wishlist_count']);
        $this->assertSame(1, $stats['digital_count']);
        $this->assertSame(1, $stats['physical_count']);
        $this->assertNotEmpty($stats['platform_breakdown']['items']);
        $this->assertNotEmpty($stats['genre_breakdown']['items']);
    }

    public function testListKnownGenresReusesCatalogTags(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $repo->createWithLibrary([
            'titre' => 'Genre Catalog Test A',
            'genre' => 'Roguelike, FPS',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $repo->createWithLibrary([
            'titre' => 'Genre Catalog Test B',
            'genre' => 'fps',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);

        $known = $repo->listKnownGenres();
        $this->assertContains('Roguelike', $known);
        $this->assertContains('FPS', $known);
    }

    public function testSavePosterKeepsLocalPathForExistingLocalUrl(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Poster Local Test',
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $oeuvreId = (int) $game['oeuvre_id'];

        $localPath = '/posters/' . $oeuvreId . '.png';
        $repo->updatePosterUrl($oeuvreId, $localPath);
        $repo->savePoster($oeuvreId, $localPath, null);

        $updated = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($updated);
        $this->assertSame($localPath, (string) ($updated['poster_url'] ?? ''));
    }

    public function testUpdateCatalogWithEditions(): void
    {
        if (!GameRepository::hasEditionColumns()) {
            $this->markTestSkipped('Colonnes éditions non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Edition Update Test',
            'platform' => GamePlatform::PC,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $payload = GameRepository::editionPayloadFromPost([
            'platform' => GamePlatform::PC,
            'physical_supports' => [GamePhysicalSupport::CD_DVD],
            'is_digital' => '1',
            'digital_pc_stores' => [GameDigitalStore::STEAM],
            'digital_store_url' => [
                GameDigitalStore::STEAM => 'https://store.steampowered.com/app/999/',
            ],
        ]);

        $result = $repo->updateCatalog($bibId, array_merge([
            'titre' => 'Edition Update Test',
            'platform' => GamePlatform::PC,
            'studio' => 'Studio Test',
            'editeur' => '',
            'genre' => 'FPS',
            'annee' => 2020,
            'synopsis' => '',
        ], $payload), $userId, $foyerId);
        $this->assertSame(true, $result);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $this->assertContains('CD / DVD', $game['physical_support_labels'] ?? []);
        $this->assertNotEmpty($game['digital_store_list'] ?? []);
    }

    public function testMagazineGameLinkListsCoverage(): void
    {
        if (!MagazineGameLink::isAvailable()) {
            $this->markTestSkipped('Pont magazine ↔ jeu non disponible.');
        }

        MediaContext::set(MediaDomain::JEU);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameRepo = new GameRepository();

        $bibId = $gameRepo->createWithLibrary([
            'titre' => 'Horizon Forbidden West Test',
            'platform' => GamePlatform::PS5,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);
        $game = $gameRepo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $oeuvreId = (int) $game['oeuvre_id'];

        MediaContext::set(MediaDomain::MAGAZINE);
        $seriesId = (new SeriesRepository())->create([
            'titre' => 'PC Jeux Lien Test',
            'publication_type' => PublicationType::MENSUEL,
        ], MediaDomain::MAGAZINE);
        $this->assertIsInt($seriesId);

        $magRepo = new MagazineRepository();
        $issueBibId = $magRepo->createIssueWithLibrary($seriesId, [
            'numero' => '99',
            'numero_ordre' => 99,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($issueBibId);

        $issue = $magRepo->findIssueByBibId($issueBibId, $userId, $foyerId);
        $this->assertNotNull($issue);

        $seriesData = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $this->assertNotNull($seriesData);

        $subjectRepo = new MagazineSubjectRepository();
        $prepared = $subjectRepo->prepareSubjectForIssue(
            MagazineSubject::TEST,
            'Horizon Forbidden West Test',
            'PS5',
            $seriesData,
            $issue,
            2022
        );
        $this->assertIsArray($prepared);

        $subject = $subjectRepo->findOrCreate(
            (string) $prepared['category'],
            (string) $prepared['label'],
            (string) $prepared['detail'],
            (int) $prepared['parution_year']
        );
        $this->assertNotNull($subject);
        $subjectId = (int) ($subject['id'] ?? 0);
        $this->assertTrue($subjectRepo->attachToOeuvre((int) $issue['oeuvre_id'], $subjectId) === true);

        $link = new MagazineGameLink();
        $result = $link->setSubjectCatalogLink($subjectId, $oeuvreId);
        $this->assertSame(true, $result);

        $coverage = $link->listMagazineCoverageForGame($oeuvreId, $userId, $foyerId);
        $this->assertCount(1, $coverage);
        $this->assertSame('PC Jeux Lien Test', $coverage[0]['series_titre']);
    }

    public function testSortableColumnsIncludeSupport(): void
    {
        $columns = GameRepository::sortableColumns();
        $this->assertContains('support', $columns);
        $this->assertContains('platform', $columns);
        $this->assertTrue(GameRepository::isValidSortColumn('genre'));
        $this->assertTrue(GameRepository::isValidSortColumn('platform'));
        if (GameSchema::hasIgdbMetadataColumns()) {
            $this->assertContains('franchise', $columns);
            $this->assertTrue(GameRepository::isValidSortColumn('franchise'));
        }
        if (GameSteamStatsRepository::isAvailable()) {
            $this->assertContains('steam_playtime', $columns);
            $this->assertTrue(GameRepository::isValidSortColumn('steam_playtime'));
        }
    }

    public function testPromoteWishlistToCollectionAndDelete(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Promote Test Game',
            'platform' => GamePlatform::SWITCH,
        ], LibraryStatut::WISHLIST, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $wishlist = $repo->listInLibrary($userId, $foyerId, LibraryStatut::WISHLIST);
        $this->assertCount(1, $wishlist);

        $this->assertTrue($repo->promoteToCollection($bibId, $userId, $foyerId));
        $this->assertSame([], $repo->listInLibrary($userId, $foyerId, LibraryStatut::WISHLIST));

        $promoted = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($promoted);
        $this->assertSame(LibraryStatut::COLLECTION, $promoted['statut'] ?? '');

        $collection = $repo->listInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertCount(1, $collection);
        $collectionId = (int) $collection[0]['id'];
        $this->assertSame($bibId, $collectionId);

        (new HistoriqueRepository())->recordViewing($collectionId, '2024-06-01', 9);
        $games = $repo->listInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $this->assertSame(5, (int) ($games[0]['note_max'] ?? 0));

        $this->assertTrue($repo->deleteById($collectionId, $userId, $foyerId));
        $this->assertSame([], $repo->listInLibrary($userId, $foyerId, LibraryStatut::COLLECTION));
    }

    public function testFormatAddedAt(): void
    {
        $this->assertSame('16-05-2024', GameRepository::formatAddedAt('2024-05-16 10:00:00'));
        $this->assertSame('', GameRepository::formatAddedAt(''));
    }

    public function testCollectionStatsExcludeExtensions(): void
    {
        if (!GameRepository::hasExtensionColumns()) {
            $this->markTestSkipped('Colonnes extensions non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $baseBibId = $repo->createWithLibrary([
            'titre' => 'Base Game Stats Test',
            'platform' => GamePlatform::PC,
            'genre' => 'Aventure',
            'annee' => 1998,
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($baseBibId);

        $baseGame = $repo->findByBibId($baseBibId, $userId, $foyerId);
        $this->assertNotNull($baseGame);

        $extensionBibId = $repo->createWithLibrary([
            'titre' => 'Extension Stats Test',
            'platform' => GamePlatform::PC,
            'genre' => 'Aventure',
            'annee' => 1999,
            'is_extension' => true,
            'base_game_oeuvre_id' => (int) $baseGame['oeuvre_id'],
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($extensionBibId);

        $stats = (new \Moncine\GameCollectionStats())->getDashboard($userId, $foyerId);

        $this->assertSame(1, $stats['collection_count']);
        $this->assertSame(1, $stats['extension_count']);
        $this->assertSame(1, $stats['digital_count']);

        $baseOnly = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::excludingExtensions()
        );
        $this->assertCount(1, $baseOnly);
        $this->assertSame('Base Game Stats Test', $baseOnly[0]['titre']);

        $extensionsOnly = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forExtensionsOnly()
        );
        $this->assertCount(1, $extensionsOnly);
        $this->assertSame('Extension Stats Test', $extensionsOnly[0]['titre']);

        $byPlatform = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forPlatform(GamePlatform::PC)
        );
        $this->assertCount(1, $byPlatform);

        $byGenre = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forGenre('aventure')
        );
        $this->assertCount(1, $byGenre);
        $this->assertSame('Base Game Stats Test', $byGenre[0]['titre']);

        $byDecade = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forDecade(1990)
        );
        $this->assertCount(1, $byDecade);
        $this->assertSame('Base Game Stats Test', $byDecade[0]['titre']);
    }

    public function testGenreFilterMatchesEveryTagInMultiGenreGame(): void
    {
        if (!GameRepository::hasExtensionColumns()) {
            $this->markTestSkipped('Colonnes extensions non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Multi Genre Filter Test',
            'platform' => GamePlatform::PC,
            'genre' => 'Aventure, RPG, Simulation',
            'annee' => 2005,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        foreach (['aventure', 'rpg', 'simulation'] as $genreKey) {
            $games = $repo->listInLibrary(
                $userId,
                $foyerId,
                LibraryStatut::COLLECTION,
                'titre',
                'asc',
                '',
                GameListFilter::forGenre($genreKey)
            );
            $this->assertCount(1, $games, 'Filtre genre « ' . $genreKey . ' »');
            $this->assertSame('Multi Genre Filter Test', $games[0]['titre']);
        }
    }

    public function testRemakeLinksBetweenOriginalAndRemake(): void
    {
        if (!GameRepository::hasRemakeColumns()) {
            $this->markTestSkipped('Colonnes remakes non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $originalBibId = $repo->createWithLibrary([
            'titre' => 'Resident Evil Original Test',
            'platform' => GamePlatform::PLAYSTATION,
            'genre' => 'Horreur',
            'annee' => 1996,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($originalBibId);

        $originalGame = $repo->findByBibId($originalBibId, $userId, $foyerId);
        $this->assertNotNull($originalGame);

        $remakeBibId = $repo->createWithLibrary([
            'titre' => 'Resident Evil Remake Test',
            'platform' => GamePlatform::GAMECUBE,
            'genre' => 'Horreur',
            'annee' => 2002,
            'is_remake' => true,
            'original_game_oeuvre_id' => (int) $originalGame['oeuvre_id'],
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($remakeBibId);

        $remakeGame = $repo->findByBibId($remakeBibId, $userId, $foyerId);
        $this->assertNotNull($remakeGame);
        $this->assertTrue($remakeGame['is_remake']);
        $this->assertSame((int) $originalGame['oeuvre_id'], (int) $remakeGame['original_game_oeuvre_id']);

        $remakes = $repo->listRemakesForOriginalGame((int) $originalGame['oeuvre_id'], $userId, $foyerId);
        $this->assertCount(1, $remakes);
        $this->assertSame($remakeBibId, $remakes[0]['bib_id']);
        $this->assertStringContainsString('2002', $remakes[0]['display_label']);

        $catalogRemakes = $repo->listCatalogRemakesForOriginalGame((int) $originalGame['oeuvre_id']);
        $this->assertCount(1, $catalogRemakes);
        $this->assertSame('Resident Evil Remake Test', $catalogRemakes[0]['titre']);

        $bothTypesError = GameRepository::validateGameRelationFlags([
            'is_extension' => true,
            'base_game_oeuvre_id' => 1,
            'is_remake' => true,
            'original_game_oeuvre_id' => 2,
        ]);
        $this->assertNotNull($bothTypesError);
    }

    public function testGameCompletionRecordedAndListed(): void
    {
        if (!\Moncine\GameCompletionRepository::isAvailable()) {
            $this->markTestSkipped('Table game_completion absente.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();
        $completionRepo = new \Moncine\GameCompletionRepository();

        $bibId = $repo->createWithLibrary([
            'titre' => 'Completion Test Game',
            'platform' => GamePlatform::SWITCH,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $completionRepo->recordCompletion($bibId, $userId, '2024-03-15');
        $completionRepo->recordCompletion($bibId, $userId, '2025-01-10');

        $this->assertSame(2, $completionRepo->countForGame($bibId, $userId));
        $this->assertSame('2025-01-10', $completionRepo->lastCompletedAt($bibId, $userId));

        $games = $repo->listInLibrary($userId, $foyerId, LibraryStatut::COLLECTION);
        $row = null;
        foreach ($games as $game) {
            if ((int) ($game['id'] ?? 0) === $bibId) {
                $row = $game;
                break;
            }
        }
        $this->assertNotNull($row);
        $this->assertSame('10-01-2025', (string) ($row['finished_at_label'] ?? ''));
        $this->assertSame(2, (int) ($row['completion_count'] ?? 0));

        $sortedDesc = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'finished_at',
            'desc'
        );
        $this->assertNotEmpty($sortedDesc);

        $sortedAsc = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'finished_at',
            'asc'
        );
        $this->assertNotEmpty($sortedAsc);
    }

    public function testListFilterByPhysicalAndDigitalSupport(): void
    {
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $physicalBibId = $repo->createWithLibrary([
            'titre' => 'Physical Support Filter Test',
            'platform' => GamePlatform::PC,
            'is_digital' => false,
            'physical_supports' => GamePhysicalSupport::CD_DVD,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($physicalBibId);

        $digitalBibId = $repo->createWithLibrary([
            'titre' => 'Digital Support Filter Test',
            'platform' => GamePlatform::PC,
            'is_digital' => true,
            'digital_stores' => json_encode([['store' => 'steam', 'url' => '']], JSON_UNESCAPED_UNICODE),
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($digitalBibId);

        $physicalGames = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forSupport(GameListFilter::SUPPORT_PHYSICAL)
        );
        $this->assertCount(1, $physicalGames);
        $this->assertSame('Physical Support Filter Test', $physicalGames[0]['titre']);

        $digitalGames = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forSupport(GameListFilter::SUPPORT_DIGITAL)
        );
        $this->assertCount(1, $digitalGames);
        $this->assertSame('Digital Support Filter Test', $digitalGames[0]['titre']);
    }

    public function testListFilterByDigitalStoreAndPlatformKind(): void
    {
        if (!GameRepository::hasEditionColumns()) {
            $this->markTestSkipped('Colonnes éditions non disponibles.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $steamBibId = $repo->createWithLibrary([
            'titre' => 'Steam Filter Test',
            'platform' => GamePlatform::PC,
            'digital_stores' => json_encode([['store' => 'steam', 'url' => '']], JSON_UNESCAPED_UNICODE),
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($steamBibId);

        $epicBibId = $repo->createWithLibrary([
            'titre' => 'Epic Filter Test',
            'platform' => GamePlatform::PC,
            'digital_stores' => json_encode([['store' => 'epic', 'url' => '']], JSON_UNESCAPED_UNICODE),
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($epicBibId);

        $switchBibId = $repo->createWithLibrary([
            'titre' => 'Switch Filter Test',
            'platform' => GamePlatform::SWITCH,
            'digital_stores' => json_encode([['store' => 'eshop', 'url' => '']], JSON_UNESCAPED_UNICODE),
            'is_digital' => true,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($switchBibId);

        $steamGames = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            GameListFilter::forDigitalStore(GameDigitalStore::STEAM)
        );
        $this->assertCount(1, $steamGames);
        $this->assertSame('Steam Filter Test', $steamGames[0]['titre']);

        $consoleGames = $repo->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            'titre',
            'asc',
            '',
            new GameListFilter(platformKind: 'console')
        );
        $titles = array_map(static fn (array $row): string => (string) ($row['titre'] ?? ''), $consoleGames);
        $this->assertContains('Switch Filter Test', $titles);
        $this->assertNotContains('Steam Filter Test', $titles);
    }
}
