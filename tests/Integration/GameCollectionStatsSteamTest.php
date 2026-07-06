<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\GameCollectionStats;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\GameSteamStatsRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SchemaMigrator;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UserContext;

final class GameCollectionStatsSteamTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SchemaMigrator(\Moncine\Database::getInstance()))->runPendingMigrations();
        MediaContext::set(MediaDomain::JEU);
        $this->loginAsAdmin();
    }

    public function testSteamPlaytimeDashboard(): void
    {
        if (!GameSteamStatsRepository::isAvailable()) {
            $this->markTestSkipped('Table game_steam_stats non disponible.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();
        $steamRepo = new GameSteamStatsRepository();

        $suffix = uniqid('steam_stats_', true);

        $bibIdA = $repo->createWithLibrary([
            'titre' => 'Steam Stats Game A ' . $suffix,
            'platform' => GamePlatform::PC,
            'annee' => 2020,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibIdA);

        $bibIdB = $repo->createWithLibrary([
            'titre' => 'Steam Stats Game B ' . $suffix,
            'platform' => GamePlatform::PC,
            'annee' => 2021,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibIdB);

        $steamRepo->upsert($bibIdA, 100, 150, 0);
        $steamRepo->upsert($bibIdB, 200, 600, 0);

        $stats = (new GameCollectionStats())->getDashboard($userId, $foyerId);

        $this->assertSame(750, $stats['playtime_minutes_total']);
        $this->assertSame(750, $stats['steam_playtime_minutes_total']);
        $this->assertSame('12h 30min', $stats['playtime_duration_label']);
        $this->assertSame('12h 30min', $stats['steam_playtime_duration_label']);
        $this->assertCount(2, $stats['top_played_games']);
        $this->assertStringContainsString('Steam Stats Game B', $stats['top_played_games'][0]['titre']);
        $this->assertSame('10 h', $stats['top_played_games'][0]['playtime_label']);
        $this->assertStringContainsString('Steam Stats Game A', $stats['top_played_games'][1]['titre']);
        $this->assertSame('2 h 30 min', $stats['top_played_games'][1]['playtime_label']);
    }

    public function testManualPlaytimeIncludedInDashboard(): void
    {
        if (!\Moncine\GameSchema::hasManualPlaytimeColumn()) {
            $this->markTestSkipped('Colonne manual_playtime_minutes absente.');
        }

        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $repo = new GameRepository();

        $suffix = uniqid('manual_pt_', true);

        $bibId = $repo->createWithLibrary([
            'titre' => 'Battle.net Manual Playtime Test ' . $suffix,
            'platform' => GamePlatform::PC,
            'annee' => 2024,
        ], LibraryStatut::COLLECTION, $userId, $foyerId);
        $this->assertIsInt($bibId);

        $result = $repo->updateLibraryExemplaire($bibId, [
            'owned_platforms' => [GamePlatform::PC],
            'manual_playtime_hours' => 42,
            'manual_playtime_minutes_part' => 15,
        ], $userId, $foyerId);
        $this->assertTrue($result === true);

        $game = $repo->findByBibId($bibId, $userId, $foyerId);
        $this->assertNotNull($game);
        $this->assertSame(2535, (int) ($game['manual_playtime_minutes'] ?? 0));
        $this->assertSame('42 h 15 min', (string) ($game['playtime_label'] ?? ''));

        $stats = (new GameCollectionStats())->getDashboard($userId, $foyerId);
        $this->assertSame(2535, $stats['playtime_minutes_total']);
        $this->assertSame(0, $stats['steam_playtime_minutes_total']);
        $this->assertNotEmpty($stats['top_played_games']);
        $this->assertStringContainsString('Battle.net Manual Playtime Test', $stats['top_played_games'][0]['titre']);
    }
}
