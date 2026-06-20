<?php
/**
 * Franchises / sagas jeux vidéo (champ catalogue oeuvre_jeu.franchise, ordre personnel saga_ordre).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameFranchiseRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameRepository::isAvailable() && GameSchema::hasIgdbMetadataColumns();
    }

    /**
     * @return list<array{franchise: string, game_count: int, poster_url: string}>
     */
    public function listFranchisesWithCounts(int $foyerId): array
    {
        if (!self::isAvailable() || $foyerId <= 0) {
            return [];
        }

        $params = [
            'foyer_id' => $foyerId,
            'statut' => LibraryStatut::COLLECTION,
            'game_domain' => MediaDomain::JEU,
        ];

        $stmt = $this->db->prepare(
            'SELECT oj.franchise, COUNT(*) AS game_count,
                (
                    SELECT o_first.poster_url
                    FROM bibliotheque b_first
                    INNER JOIN oeuvres o_first ON o_first.id = b_first.oeuvre_id
                    INNER JOIN oeuvre_jeu oj_first ON oj_first.oeuvre_id = o_first.id
                    WHERE b_first.foyer_id = :foyer_id
                      AND b_first.statut = :statut
                      AND o_first.media_domain = :game_domain
                      AND oj_first.franchise = oj.franchise
                    ORDER BY
                        CASE WHEN o_first.annee > 0 THEN o_first.annee ELSE 9999 END ASC,
                        o_first.titre COLLATE FRENCH_NOCASE ASC
                    LIMIT 1
                ) AS poster_url
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE b.foyer_id = :foyer_id
               AND b.statut = :statut
               AND o.media_domain = :game_domain
               AND TRIM(oj.franchise) != \'\'
             GROUP BY oj.franchise
             ORDER BY oj.franchise COLLATE FRENCH_NOCASE'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['franchise'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'franchise' => $name,
                'game_count' => (int) ($row['game_count'] ?? 0),
                'poster_url' => trim((string) ($row['poster_url'] ?? '')),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public function distinctFranchises(int $foyerId): array
    {
        $out = [];
        foreach ($this->listFranchisesWithCounts($foyerId) as $item) {
            $out[] = $item['franchise'];
        }

        return $out;
    }

    /**
     * Sagas déjà utilisées dans le catalogue (autocomplétion des formulaires).
     *
     * @return list<string>
     */
    public function listKnownSagas(int $limit = 120): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 300));
        $stmt = $this->db->query(
            'SELECT franchise FROM oeuvre_jeu WHERE TRIM(franchise) != \'\'
             ORDER BY franchise COLLATE FRENCH_NOCASE ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $known = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string) ($row['franchise'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($known[$key])) {
                $known[$key] = $name;
            }
        }

        $names = array_values($known);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($names, 0, $limit);
    }

    /**
     * Jeux de la collection appartenant à une saga (tri : année, puis titre).
     *
     * @return list<array<string, mixed>>
     */
    public function findByFranchise(int $foyerId, int $userId, string $franchise): array
    {
        $franchise = trim($franchise);
        if (!self::isAvailable() || $foyerId <= 0 || $franchise === '') {
            return [];
        }

        $params = [
            'foyer_id' => $foyerId,
            'statut' => LibraryStatut::COLLECTION,
            'game_domain' => MediaDomain::JEU,
            'franchise' => $franchise,
            'history_user_id' => $userId,
            'foyer_id_rating' => $foyerId,
        ];

        $sql = 'SELECT ' . self::selectGameRowWithOrder() . self::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE b.foyer_id = :foyer_id'
            . '   AND b.statut = :statut'
            . '   AND o.media_domain = :game_domain'
            . '   AND oj.franchise = :franchise'
            . ' ORDER BY'
            . '   CASE WHEN o.annee > 0 THEN o.annee ELSE 9999 END ASC,'
            . '   o.titre COLLATE FRENCH_NOCASE ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([GameRowMapper::class, 'hydrateGameRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Associe des jeux à une saga (oeuvre_jeu.franchise).
     *
     * @param list<int> $bibIds
     */
    public function assignGamesToFranchise(array $bibIds, string $franchise, int $foyerId): int
    {
        $franchise = trim($franchise);
        if (!self::isAvailable() || $franchise === '' || $bibIds === [] || $foyerId <= 0) {
            return 0;
        }

        $lookup = $this->db->prepare(
            'SELECT oeuvre_id FROM bibliotheque
             WHERE id = :bib_id AND foyer_id = :foyer_id AND statut = :statut'
        );
        $updateCatalog = $this->db->prepare(
            'UPDATE oeuvre_jeu SET franchise = :franchise WHERE oeuvre_id = :oeuvre_id'
        );

        $updated = 0;
        foreach ($bibIds as $bibId) {
            $bibId = (int) $bibId;
            if ($bibId <= 0) {
                continue;
            }

            $lookup->execute([
                'bib_id' => $bibId,
                'foyer_id' => $foyerId,
                'statut' => LibraryStatut::COLLECTION,
            ]);
            $oeuvreId = (int) $lookup->fetchColumn();
            if ($oeuvreId <= 0) {
                continue;
            }

            $updateCatalog->execute([
                'franchise' => $franchise,
                'oeuvre_id' => $oeuvreId,
            ]);
            if ($updateCatalog->rowCount() > 0) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @return array{ok: true, updated: int}|array{ok: false, error: string}
     */
    public function renameFranchise(string $oldName, string $newName, int $foyerId): array
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if (!self::isAvailable()) {
            return ['ok' => false, 'error' => 'Module franchises indisponible.'];
        }
        if ($oldName === '') {
            return ['ok' => false, 'error' => 'Saga introuvable.'];
        }
        if ($newName === '') {
            return ['ok' => false, 'error' => 'Le nouveau nom ne peut pas être vide.'];
        }
        if ($oldName === $newName) {
            return ['ok' => true, 'updated' => 0];
        }
        if ($foyerId <= 0) {
            return ['ok' => false, 'error' => 'Foyer invalide.'];
        }

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque b
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = b.oeuvre_id
             WHERE b.foyer_id = ? AND b.statut = ? AND oj.franchise = ?'
        );
        $countStmt->execute([$foyerId, LibraryStatut::COLLECTION, $oldName]);
        if ((int) $countStmt->fetchColumn() === 0) {
            return ['ok' => false, 'error' => 'Aucun jeu n’utilise cette saga.'];
        }

        $stmt = $this->db->prepare(
            'UPDATE oeuvre_jeu SET franchise = :new_name
             WHERE franchise = :old_name
               AND oeuvre_id IN (
                   SELECT oeuvre_id FROM bibliotheque
                   WHERE foyer_id = :foyer_id AND statut = :statut
               )'
        );
        $stmt->execute([
            'new_name' => $newName,
            'old_name' => $oldName,
            'foyer_id' => $foyerId,
            'statut' => LibraryStatut::COLLECTION,
        ]);

        return ['ok' => true, 'updated' => $stmt->rowCount()];
    }

    /**
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public static function parseBulkGameIds(array $post): array
    {
        $ids = [];
        foreach ((array) ($post['game_ids'] ?? []) as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function selectGameRowWithOrder(): string
    {
        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at, b.saga_ordre,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' oj.studio, oj.editeur, oj.genre, oj.platform, oj.is_digital'
            . (GameSchema::hasEditionColumns() ? ', oj.physical_supports, oj.digital_stores' : '')
            . GameRelations::selectColumns()
            . (GameSchema::hasIgdbColumns() ? ', oj.igdb_id, oj.igdb_enriched_at' : '')
            . (GameSchema::hasIgdbMetadataColumns()
                ? ', oj.franchise, oj.game_mode, oj.theme, oj.alternative_names'
                : '')
            . (GameSchema::hasTestedOnLinuxColumn()
                ? ', b.tested_on_linux' . (GameSchema::hasLinuxNotSupportedColumn() ? ', b.linux_not_supported' : '')
                : '');
    }

    private static function selectGameHistoryExtras(): string
    {
        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_session,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND h.note IS NOT NULL AND h.note >= 1) AS note_max,'
            . CatalogSchema::foyerAverageNoteSubquery('b.id', ':foyer_id_rating');
    }
}
