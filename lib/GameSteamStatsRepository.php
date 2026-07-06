<?php
/**
 * Temps de jeu Steam synchronisé (par entrée bibliothèque).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameSteamStatsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameSchema::steamStatsTableExists();
    }

    public static function selectListExtrasSql(): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        return ', gss.playtime_minutes AS steam_playtime_minutes, gss.last_played_unix AS steam_last_played_unix, gss.steam_appid AS library_steam_appid';
    }

    public static function listJoinSql(): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        return ' LEFT JOIN game_steam_stats gss ON gss.bibliotheque_id = b.id';
    }

    public function upsert(int $bibId, int $steamAppid, int $playtimeMinutes, int $lastPlayedUnix): void
    {
        if (!self::isAvailable() || $bibId <= 0) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO game_steam_stats (bibliotheque_id, steam_appid, playtime_minutes, last_played_unix, synced_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))
             ON CONFLICT(bibliotheque_id) DO UPDATE SET
                steam_appid = excluded.steam_appid,
                playtime_minutes = excluded.playtime_minutes,
                last_played_unix = excluded.last_played_unix,
                synced_at = datetime(\'now\')'
        )->execute([
            $bibId,
            max(0, $steamAppid),
            max(0, $playtimeMinutes),
            max(0, $lastPlayedUnix),
        ]);
    }
}
