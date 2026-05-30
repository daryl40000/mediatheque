<?php
/**
 * Envies agrégées des membres d’un groupe famille (foyer).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GroupWishlistRepository
{
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
        'styles' => 'o.styles COLLATE FRENCH_NOCASE',
        'votes' => 'vote_count',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<int> */
    public function memberUserIdsForFoyer(int $foyerId): array
    {
        if ($foyerId <= 0) {
            return [];
        }

        if (FamilyGroupService::isAvailable()) {
            $stmt = $this->db->prepare(
                'SELECT user_id FROM group_members WHERE foyer_id = ? ORDER BY user_id ASC'
            );
            $stmt->execute([$foyerId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        }

        $stmt = $this->db->prepare(
            'SELECT id FROM utilisateurs WHERE foyer_id = ? AND actif = 1 ORDER BY id ASC'
        );
        $stmt->execute([$foyerId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function canShowGroupView(int $foyerId): bool
    {
        return count($this->memberUserIdsForFoyer($foyerId)) > 1;
    }

    public function countDistinctOeuvres(int $foyerId): int
    {
        $memberIds = $this->memberUserIdsForFoyer($foyerId);
        if ($memberIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT oeuvre_id) FROM bibliotheque
             WHERE statut = ? AND user_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([LibraryStatut::WISHLIST], $memberIds));

        return (int) $stmt->fetchColumn();
    }

    /**
     * Œuvres demandées par au moins un membre du groupe (agrégées).
     *
     * @return list<array<string, mixed>>
     */
    public function findAggregated(
        int $foyerId,
        int $currentUserId,
        string $sortBy = 'votes',
        string $sortDir = 'desc',
        string $searchQuery = ''
    ): array {
        $memberIds = $this->memberUserIdsForFoyer($foyerId);
        if ($memberIds === []) {
            return [];
        }

        if (!isset(self::SORT_COLUMNS[$sortBy])) {
            $sortBy = 'votes';
        }
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $orderExpr = self::SORT_COLUMNS[$sortBy];

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $params = array_merge($memberIds, [LibraryStatut::WISHLIST]);

        $whereParts = [
            'b.user_id IN (' . $placeholders . ')',
            'b.statut = ?',
        ];

        $searchWhere = $this->searchOeuvreWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }

        CatalogSchema::applyMediaDomainFilter($whereParts, $params);

        $sql = 'SELECT o.id AS oeuvre_id, o.titre, o.titre_original, o.realisateur, o.annee,
                       o.nationalite, o.styles, o.poster_url, o.moncine_kind,
                       COUNT(DISTINCT b.user_id) AS vote_count
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                WHERE ' . implode(' AND ', $whereParts) . '
                GROUP BY b.oeuvre_id
                ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $oeuvreIds = array_map(static fn (array $r): int => (int) ($r['oeuvre_id'] ?? 0), $rows);
        $votersByOeuvre = $this->fetchVotersByOeuvre($memberIds, $oeuvreIds);
        $myIds = $this->fetchMyWishlistIds($currentUserId, $oeuvreIds);

        foreach ($rows as &$row) {
            $oid = (int) ($row['oeuvre_id'] ?? 0);
            $row['id'] = $myIds[$oid] ?? 0;
            $row['in_my_wishlist'] = isset($myIds[$oid]);
            $row['voters'] = $votersByOeuvre[$oid] ?? [];
            $row['vote_count'] = (int) ($row['vote_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<int> $memberIds
     * @param list<int> $oeuvreIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function fetchVotersByOeuvre(array $memberIds, array $oeuvreIds): array
    {
        if ($memberIds === [] || $oeuvreIds === []) {
            return [];
        }

        $mPh = implode(',', array_fill(0, count($memberIds), '?'));
        $oPh = implode(',', array_fill(0, count($oeuvreIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT b.oeuvre_id, u.id AS user_id, u.nom, u.prenom, u.pseudo
             FROM bibliotheque b
             INNER JOIN utilisateurs u ON u.id = b.user_id
             WHERE b.statut = ?
               AND b.user_id IN ($mPh)
               AND b.oeuvre_id IN ($oPh)
             ORDER BY u.pseudo COLLATE FRENCH_NOCASE, u.nom COLLATE FRENCH_NOCASE"
        );
        $stmt->execute(array_merge(
            [LibraryStatut::WISHLIST],
            $memberIds,
            $oeuvreIds
        ));

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $oid = (int) ($row['oeuvre_id'] ?? 0);
            if ($oid <= 0) {
                continue;
            }
            $out[$oid][] = $row;
        }

        return $out;
    }

    /**
     * @param list<int> $oeuvreIds
     * @return array<int, int> oeuvre_id => bibliotheque id
     */
    private function fetchMyWishlistIds(int $userId, array $oeuvreIds): array
    {
        if ($userId <= 0 || $oeuvreIds === []) {
            return [];
        }

        $oPh = implode(',', array_fill(0, count($oeuvreIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT oeuvre_id, id FROM bibliotheque
             WHERE user_id = ? AND statut = ? AND oeuvre_id IN ($oPh)"
        );
        $stmt->execute(array_merge([$userId, LibraryStatut::WISHLIST], $oeuvreIds));

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) ($row['oeuvre_id'] ?? 0)] = (int) ($row['id'] ?? 0);
        }

        return $map;
    }

    /**
     * @param array<string, string> $params
     */
    private function searchOeuvreWhereSql(string $searchQuery, array &$params): string
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return '';
        }

        $pattern = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
        $params['group_wish_q'] = $pattern;

        $fields = [
            'o.titre',
            'o.titre_original',
            'o.realisateur',
            'o.acteur_1',
            'o.acteur_2',
            'o.acteur_3',
            'o.styles',
        ];

        $parts = [];
        foreach ($fields as $field) {
            $parts[] = 'LOWER(' . $field . ') LIKE LOWER(:group_wish_q) ESCAPE \'\\\'';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }
}
