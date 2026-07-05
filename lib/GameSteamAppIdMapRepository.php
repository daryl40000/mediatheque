<?php
/**
 * Correspondance persistante AppID Steam → œuvre catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameSteamAppIdMapRepository
{
    public const SOURCE_MANUAL = 'manual';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameSchema::steamAppIdMapTableExists();
    }

    public function findOeuvreIdByAppId(int $steamAppid): ?int
    {
        if (!self::isAvailable() || $steamAppid <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id FROM game_steam_appid_map WHERE steam_appid = ? LIMIT 1'
        );
        $stmt->execute([$steamAppid]);
        $oeuvreId = $stmt->fetchColumn();

        return $oeuvreId !== false ? (int) $oeuvreId : null;
    }

    /**
     * @return array<int, int> appid => oeuvre_id
     */
    public function buildAppIdIndex(): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = $this->db->query(
            'SELECT steam_appid, oeuvre_id FROM game_steam_appid_map WHERE steam_appid > 0 AND oeuvre_id > 0'
        );
        if ($stmt === false) {
            return [];
        }

        $index = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $appid = (int) ($row['steam_appid'] ?? 0);
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($appid > 0 && $oeuvreId > 0) {
                $index[$appid] = $oeuvreId;
            }
        }

        return $index;
    }

    /**
     * @return true|string
     */
    public function upsert(int $steamAppid, int $oeuvreId, int $userId, string $source = self::SOURCE_MANUAL): bool|string
    {
        if (!self::isAvailable()) {
            return 'Table de correspondance Steam non disponible (migration en cours).';
        }

        if ($steamAppid <= 0) {
            return 'AppID Steam invalide.';
        }

        if ($oeuvreId <= 0) {
            return 'Fiche catalogue invalide.';
        }

        if ((new GameRepository())->findCatalogByOeuvreId($oeuvreId) === null) {
            return 'Ce jeu n’existe pas dans le catalogue.';
        }

        $source = trim($source) !== '' ? trim($source) : self::SOURCE_MANUAL;

        $this->db->prepare(
            'INSERT INTO game_steam_appid_map (steam_appid, oeuvre_id, mapped_by_user_id, source, mapped_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))
             ON CONFLICT(steam_appid) DO UPDATE SET
                oeuvre_id = excluded.oeuvre_id,
                mapped_by_user_id = excluded.mapped_by_user_id,
                source = excluded.source,
                mapped_at = datetime(\'now\')'
        )->execute([
            $steamAppid,
            $oeuvreId,
            max(0, $userId),
            $source,
        ]);

        return true;
    }

    public function deleteByAppId(int $steamAppid): void
    {
        if (!self::isAvailable() || $steamAppid <= 0) {
            return;
        }

        $this->db->prepare('DELETE FROM game_steam_appid_map WHERE steam_appid = ?')
            ->execute([$steamAppid]);
    }

    /** Après fusion catalogue : rediriger les liens de la fiche supprimée. */
    public function reassignOnOeuvreMerge(int $keepOeuvreId, int $removeOeuvreId): void
    {
        if (!self::isAvailable() || $keepOeuvreId <= 0 || $removeOeuvreId <= 0 || $keepOeuvreId === $removeOeuvreId) {
            return;
        }

        $this->db->prepare(
            'UPDATE game_steam_appid_map SET oeuvre_id = ? WHERE oeuvre_id = ?'
        )->execute([$keepOeuvreId, $removeOeuvreId]);
    }
}
