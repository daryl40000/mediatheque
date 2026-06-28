<?php
/**
 * Requêtes jeux liés (extensions, remakes) — bibliothèque et catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameLinkedGamesQuery
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, poster_url:string, platform_short:string, display_label:string}>
     */
    public function listLibraryExtensions(int $baseOeuvreId, int $userId, int $foyerId): array
    {
        return $this->listLibraryRelated(
            'is_extension',
            'base_game_oeuvre_id',
            'base_id',
            $baseOeuvreId,
            $userId,
            $foyerId,
            'o.annee ASC, o.titre COLLATE FRENCH_NOCASE ASC'
        );
    }

    /**
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, poster_url:string, platform_short:string, display_label:string}>
     */
    public function listLibraryRemakes(int $originalOeuvreId, int $userId, int $foyerId): array
    {
        return $this->listLibraryRelated(
            'is_remake',
            'original_game_oeuvre_id',
            'original_id',
            $originalOeuvreId,
            $userId,
            $foyerId,
            'o.annee ASC, o.titre COLLATE FRENCH_NOCASE ASC'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCatalogExtensions(int $baseOeuvreId, string $selectCatalogRow, callable $hydrateCatalogRow): array
    {
        return $this->listCatalogRelated(
            'is_extension',
            'base_game_oeuvre_id',
            $baseOeuvreId,
            'o.annee ASC, o.titre COLLATE FRENCH_NOCASE ASC',
            $selectCatalogRow,
            $hydrateCatalogRow
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCatalogRemakes(int $originalOeuvreId, string $selectCatalogRow, callable $hydrateCatalogRow): array
    {
        return $this->listCatalogRelated(
            'is_remake',
            'original_game_oeuvre_id',
            $originalOeuvreId,
            'o.annee ASC, o.titre COLLATE FRENCH_NOCASE ASC',
            $selectCatalogRow,
            $hydrateCatalogRow
        );
    }

    /**
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, poster_url:string, platform_short:string, display_label:string}>
     */
    private function listLibraryRelated(
        string $flagColumn,
        string $fkColumn,
        string $paramName,
        int $parentOeuvreId,
        int $userId,
        int $foyerId,
        string $orderBy
    ): array {
        if ($parentOeuvreId <= 0) {
            return [];
        }

        $params = [
            $paramName => $parentOeuvreId,
            'domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, oj.platform'
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = :domain'
            . ' AND oj.' . $flagColumn . ' = 1'
            . ' AND oj.' . $fkColumn . ' = :' . $paramName
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' ORDER BY ' . $orderBy
        );
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = GameRowMapper::hydrateLinkedLibraryGameRow($row);
        }

        return $out;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $hydrateCatalogRow
     * @return list<array<string, mixed>>
     */
    private function listCatalogRelated(
        string $flagColumn,
        string $fkColumn,
        int $parentOeuvreId,
        string $orderBy,
        string $selectCatalogRow,
        callable $hydrateCatalogRow
    ): array {
        if ($parentOeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ' . $selectCatalogRow
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = ? AND oj.' . $flagColumn . ' = 1 AND oj.' . $fkColumn . ' = ?'
            . ' ORDER BY ' . $orderBy
        );
        $stmt->execute([MediaDomain::JEU, $parentOeuvreId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $hydrateCatalogRow($row);
        }

        return $out;
    }
}
