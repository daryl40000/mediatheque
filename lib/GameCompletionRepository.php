<?php
/**
 * Fin de partie : enregistrement des jeux terminés (date, plusieurs fois).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCompletionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameSchema::completionTableExists();
    }

    /** Sous-requêtes SQL pour listes jeux (dernière fin + nombre de fins). */
    public static function selectListExtrasSql(): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        return ','
            . ' (SELECT MAX(gc.completed_at) FROM game_completion gc'
            . '  WHERE gc.bibliotheque_id = b.id AND gc.user_id = :history_user_id) AS derniere_completion,'
            . ' (SELECT COUNT(*) FROM game_completion gc'
            . '  WHERE gc.bibliotheque_id = b.id AND gc.user_id = :history_user_id) AS completion_count'
            . GameSteamStatsRepository::selectListExtrasSql();
    }

    public function recordCompletion(int $bibId, int $userId, string $completedAt): int
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Fonctionnalité « jeu terminé » non disponible (migration en cours).');
        }

        if (!$this->libraryEntryExists($bibId, $userId)) {
            throw new \RuntimeException('Ce jeu est introuvable dans votre bibliothèque.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO game_completion (bibliotheque_id, user_id, completed_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$bibId, $userId, $completedAt]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array{id: int, completed_at: string}> */
    public function listForGame(int $bibId, int $userId): array
    {
        if (!self::isAvailable() || $bibId <= 0 || $userId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT id, completed_at FROM game_completion
             WHERE bibliotheque_id = ? AND user_id = ?
             ORDER BY completed_at DESC, id DESC'
        );
        $stmt->execute([$bibId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForGame(int $bibId, int $userId): int
    {
        if (!self::isAvailable() || $bibId <= 0 || $userId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM game_completion WHERE bibliotheque_id = ? AND user_id = ?'
        );
        $stmt->execute([$bibId, $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function lastCompletedAt(int $bibId, int $userId): ?string
    {
        if (!self::isAvailable() || $bibId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(completed_at) FROM game_completion WHERE bibliotheque_id = ? AND user_id = ?'
        );
        $stmt->execute([$bibId, $userId]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null && $value !== '' ? (string) $value : null;
    }

    /** Jeux distincts terminés au moins une fois (collection du foyer, hors extensions). */
    public function countDistinctFinishedInCollection(int $userId, int $foyerId): int
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return 0;
        }

        $params = [
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];

        $extensionSql = GameRepository::hasExtensionColumns()
            ? ' AND (oj.is_extension IS NULL OR oj.is_extension = 0)'
            : '';

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT b.id)
             FROM game_completion gc
             INNER JOIN bibliotheque b ON b.id = gc.bibliotheque_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE gc.user_id = :user_id
               AND o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id' . $extensionSql
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Nombre total de fins enregistrées (toutes reprises incluses). */
    public function countTotalCompletions(int $userId, int $foyerId): int
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return 0;
        }

        $params = [
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM game_completion gc
             INNER JOIN bibliotheque b ON b.id = gc.bibliotheque_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE gc.user_id = :user_id
               AND o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function libraryEntryExists(int $bibId, int $userId): bool
    {
        if ($bibId <= 0) {
            return false;
        }

        $foyerId = UserContext::currentFoyerId();
        $stmt = $this->db->prepare(
            'SELECT 1 FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.id = ?
               AND o.media_domain = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([
            $bibId,
            MediaDomain::JEU,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
