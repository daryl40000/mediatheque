<?php
/**
 * Recherche films par personne (réalisateur / acteurs) et liste de noms connus.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmPersonQuery
{
    public function __construct(
        private readonly PDO $db,
        private readonly OeuvreRepository $oeuvres = new OeuvreRepository(),
        private readonly BibliothequeRepository $bibliotheque = new BibliothequeRepository(),
    ) {
    }

    private function userId(): int
    {
        return UserContext::currentUserId();
    }

    private function foyerId(): int
    {
        return UserContext::currentFoyerId();
    }

    public function findByPersonne(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $personId = preg_match('/^\d+$/', $query) ? (int) $query : 0;
        $like = LikePattern::containsFragment($query);
        if ($like === '') {
            return [];
        }

        $nameMatch = 'LOWER(o.realisateur) LIKE LOWER(:like1)
                OR LOWER(o.acteur_1) LIKE LOWER(:like2)
                OR LOWER(o.acteur_2) LIKE LOWER(:like3)
                OR LOWER(o.acteur_3) LIKE LOWER(:like4)';
        $params = [
            'like1' => $like,
            'like2' => $like,
            'like3' => $like,
            'like4' => $like,
            'foyer_id' => $this->foyerId(),
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'user_id' => $this->userId(),
            'history_user_id' => $this->userId(),
        ];

        if ($personId > 0) {
            $where = '(o.realisateur_tmdb_id = :pid1
                    OR o.acteur_1_tmdb_id = :pid2
                    OR o.acteur_2_tmdb_id = :pid3
                    OR o.acteur_3_tmdb_id = :pid4)
                OR (' . $nameMatch . ')';
            $params['pid1'] = $personId;
            $params['pid2'] = $personId;
            $params['pid3'] = $personId;
            $params['pid4'] = $personId;
        } else {
            $where = $nameMatch;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . $this->selectPersonSearchRow() . ',
                (SELECT MAX(h.date_vue) FROM historique h
                 WHERE h.film_id = b_coll.id AND h.user_id = :history_user_id) AS derniere_vue
             FROM oeuvres o
             LEFT JOIN bibliotheque b_coll ON b_coll.oeuvre_id = o.id
                 AND b_coll.foyer_id = :foyer_id AND b_coll.statut = :collection
             LEFT JOIN bibliotheque b_wish ON b_wish.oeuvre_id = o.id
                 AND b_wish.user_id = :user_id AND b_wish.statut = :wishlist
             WHERE (' . $where . ')' . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function distinctPersonnes(int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT DISTINCT name FROM (
                SELECT realisateur AS name FROM oeuvres WHERE TRIM(realisateur) != ""
                UNION SELECT acteur_1 FROM oeuvres WHERE TRIM(acteur_1) != ""
                UNION SELECT acteur_2 FROM oeuvres WHERE TRIM(acteur_2) != ""
                UNION SELECT acteur_3 FROM oeuvres WHERE TRIM(acteur_3) != ""
            ) ORDER BY name COLLATE FRENCH_NOCASE LIMIT ' . $limit;
        $rows = $this->db->query($sql)->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $n = trim((string) ($row['name'] ?? ''));
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return $out;
    }

    private function selectPersonSearchRow(): string
    {
        $parts = [
            'o.id AS oeuvre_id',
            'COALESCE(b_coll.id, b_wish.id, 0) AS id',
            'COALESCE(b_coll.user_id, b_wish.user_id, 0) AS user_id',
            'COALESCE(b_coll.foyer_id, b_wish.foyer_id, 0) AS foyer_id',
            'COALESCE(b_coll.statut, b_wish.statut, \'\') AS statut',
            'CASE WHEN b_coll.id IS NOT NULL THEN \'collection\'
                  WHEN b_wish.id IS NOT NULL THEN \'wishlist\'
                  ELSE \'none\' END AS library_presence',
        ];
        foreach (CatalogSchema::LIBRARY_FIELDS as $field) {
            $parts[] = 'COALESCE(b_coll.' . $field . ', b_wish.' . $field . ', \'\') AS ' . $field;
        }
        foreach (CatalogSchema::OEUVRE_FIELDS as $field) {
            $parts[] = 'o.' . $field;
        }
        $parts[] = 'COALESCE(b_coll.created_at, b_wish.created_at, \'\') AS created_at';

        return implode(', ', $parts);
    }

}
