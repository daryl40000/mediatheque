<?php
/**
 * Lectures bibliothèque et catalogue films (listes, recherche, filtres, exports).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FilmLibraryQuery
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

    public function findAll(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $statut = LibraryStatut::COLLECTION,
        string $kindFilter = '',
        ?int $limit = null,
        int $offset = 0
    ): array {
        if (!FilmCatalogSql::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = FilmCatalogSql::sortOrderExpression($sortBy);

        $sql = 'SELECT ' . CatalogSchema::selectFilmRow() . FilmCatalogSql::collectionRatingSelectSql()
             . ' FROM ' . CatalogSchema::JOIN;

        $params = [];
        $whereParts = $this->collectionWhereParts($searchQuery, $statut, $kindFilter, $params);

        $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }
        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . max(0, $offset);
        }

        FilmCatalogSql::appendCollectionRatingParams($params, $this->userId());
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countCollectionFiltered(
        string $searchQuery = '',
        string $kindFilter = '',
        string $statut = LibraryStatut::COLLECTION
    ): int {
        $params = [];
        $whereParts = $this->collectionWhereParts($searchQuery, $statut, $kindFilter, $params);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . CatalogSchema::JOIN . ' WHERE ' . implode(' AND ', $whereParts)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function collectionWhereParts(
        string $searchQuery,
        string $statut,
        string $kindFilter,
        array &$params
    ): array {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), $statut);
        $whereParts = [$userWhere];

        $searchWhere = FilmCatalogSql::collectionSearchWhereSql($searchQuery, $params);
        if ($searchWhere !== '') {
            $whereParts[] = $searchWhere;
        }

        $kindWhere = ContentKindFilter::sqlWhere($kindFilter, 'o.', $params);
        if ($kindWhere !== '') {
            $whereParts[] = $kindWhere;
        }

        CatalogSchema::applyMediaDomainFilter($whereParts, $params);

        return $whereParts;
    }

    public function findAllWishlist(
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        string $kindFilter = ''
    ): array {
        return $this->findAll($sortBy, $sortDir, $searchQuery, LibraryStatut::WISHLIST, $kindFilter);
    }

    public function findAllForExport(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), LibraryStatut::COLLECTION);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT h.date_vue FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_vue,
                (SELECT h.note FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_note
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findAllLibraryForExport(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), null);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT h.date_vue FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_vue,
                (SELECT h.note FROM historique h
                 WHERE h.film_id = b.id AND h.user_id = :history_user_id
                 ORDER BY h.date_vue DESC, h.id DESC LIMIT 1) AS derniere_note
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY b.statut COLLATE FRENCH_NOCASE, o.titre COLLATE FRENCH_NOCASE'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function countLibraryEntries(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque
             WHERE (foyer_id = ? AND statut = ?) OR (user_id = ? AND statut = ?)'
        );
        $stmt->execute([
            $this->foyerId(),
            LibraryStatut::COLLECTION,
            $this->userId(),
            LibraryStatut::WISHLIST,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findAllRandomOrder(): array
    {
        [$userWhere, $params] = CatalogSchema::libraryFilter($this->foyerId(), $this->userId(), LibraryStatut::COLLECTION);

        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                (SELECT MAX(h.date_vue) FROM historique h WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_vue
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY RANDOM()'
        );
        $params['history_user_id'] = $this->userId();
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return $this->bibliotheque->countByStatut($this->userId(), $this->foyerId(), LibraryStatut::COLLECTION);
    }

    public function countWishlist(): int
    {
        return $this->bibliotheque->countByStatut($this->userId(), $this->foyerId(), LibraryStatut::WISHLIST);
    }

    public function findById(int $id): ?array
    {
        return $this->bibliotheque->findById($id, $this->userId(), $this->foyerId());
    }

    public function findByTitreAndRealisateur(string $titre, string $realisateur): ?array
    {
        $oeuvre = $this->oeuvres->findByTitreAndRealisateur($titre, $realisateur);
        if ($oeuvre === null) {
            return null;
        }

        $library = $this->bibliotheque->findByOeuvreId((int) $oeuvre['id'], $this->userId(), $this->foyerId());
        if ($library === null) {
            return null;
        }

        return $this->findById((int) $library['id']);
    }

    public function searchCatalogOeuvres(string $query, int $limit = 20): array
    {
        $rows = $this->oeuvres->searchByTitrePrefix($query, $limit);
        $userId = $this->userId();
        $out = [];

        foreach ($rows as $oeuvre) {
            $oeuvreId = (int) ($oeuvre['id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $library = $this->bibliotheque->findByOeuvreId($oeuvreId, $userId, $this->foyerId());
            $libraryStatut = $library !== null
                ? (string) ($library['statut'] ?? LibraryStatut::COLLECTION)
                : '';

            $out[] = [
                'id' => $oeuvreId,
                'label' => self::formatCatalogOeuvreLabel($oeuvre),
                'titre' => (string) ($oeuvre['titre'] ?? ''),
                'titre_original' => (string) ($oeuvre['titre_original'] ?? ''),
                'realisateur' => (string) ($oeuvre['realisateur'] ?? ''),
                'annee' => (int) ($oeuvre['annee'] ?? 0),
                'styles' => (string) ($oeuvre['styles'] ?? ''),
                'nationalite' => (string) ($oeuvre['nationalite'] ?? ''),
                'duree_min' => (int) ($oeuvre['duree_min'] ?? 0),
                'duree' => FilmManualEdit::dureeForInput((int) ($oeuvre['duree_min'] ?? 0)),
                'acteur_1' => (string) ($oeuvre['acteur_1'] ?? ''),
                'acteur_2' => (string) ($oeuvre['acteur_2'] ?? ''),
                'acteur_3' => (string) ($oeuvre['acteur_3'] ?? ''),
                'poster_url' => (string) ($oeuvre['poster_url'] ?? ''),
                'synopsis' => (string) ($oeuvre['synopsis'] ?? ''),
                'tmdb_id' => (int) ($oeuvre['tmdb_id'] ?? 0),
                'moncine_kind' => MoncineContentKind::normalize((string) ($oeuvre['moncine_kind'] ?? '')),
                'content_kind' => MoncineContentKind::toFormValue(
                    (string) ($oeuvre['moncine_kind'] ?? MoncineContentKind::FILM),
                    (string) ($oeuvre['tmdb_media_type'] ?? ''),
                    (string) ($oeuvre['tmdb_tv_kind'] ?? '')
                ),
                'in_library' => $library !== null,
                'library_statut' => $libraryStatut,
                'library_statut_label' => $libraryStatut !== ''
                    ? LibraryStatut::label($libraryStatut)
                    : '',
                'saga' => CatalogSchema::hasOeuvreSagaColumns()
                    ? trim((string) ($oeuvre['saga'] ?? ''))
                    : '',
                'saga_ordre' => CatalogSchema::hasOeuvreSagaColumns()
                    ? max(0, (int) ($oeuvre['saga_ordre'] ?? 0))
                    : 0,
            ];
        }

        return $out;
    }

    public static function formatCatalogOeuvreLabel(array $oeuvre): string
    {
        $titre = trim((string) ($oeuvre['titre'] ?? ''));
        $realisateur = trim((string) ($oeuvre['realisateur'] ?? ''));
        $annee = (int) ($oeuvre['annee'] ?? 0);

        $label = $titre;
        if ($realisateur !== '') {
            $label .= ' — ' . $realisateur;
        }
        if ($annee > 0) {
            $label .= ' (' . $annee . ')';
        }

        return $label;
    }

    public function findBySaga(string $saga): array
    {
        $saga = trim($saga);
        if ($saga === '') {
            return [];
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'saga' => $saga,
        ];
        $sagaFilter = CatalogSchema::hasOeuvreSagaColumns()
            ? 'o.saga = :saga'
            : 'b.saga = :saga';
        $orderBy = CatalogSchema::hasOeuvreSagaColumns()
            ? 'CASE WHEN o.saga_ordre > 0 THEN o.saga_ordre ELSE 999999 END ASC'
            : 'CASE WHEN b.saga_ordre > 0 THEN b.saga_ordre ELSE 999999 END ASC';
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . FilmCatalogSql::collectionRatingSelectSql() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id
               AND b.statut = :catalog_statut
               AND ' . $sagaFilter
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY
                ' . $orderBy . ',
                o.titre COLLATE FRENCH_NOCASE ASC'
        );
        FilmCatalogSql::appendCollectionRatingParams($params, $this->userId());
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findBySupportPhysique(string $supportKey): array
    {
        if (!SupportPhysique::isValid($supportKey)) {
            return [];
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
            'support' => $supportKey,
        ];
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . FilmCatalogSql::collectionRatingSelectSql() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id
               AND b.statut = :catalog_statut
               AND b.support_physique = :support'
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY o.titre COLLATE FRENCH_NOCASE'
        );
        FilmCatalogSql::appendCollectionRatingParams($params, $this->userId());
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findByTmdbId(int $tmdbId): ?array
    {
        if ($tmdbId <= 0) {
            return null;
        }

        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_user_id' => $this->userId(),
            'tmdb_id' => $tmdbId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
        ];
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE (
                    (b.foyer_id = :catalog_foyer_id AND b.statut = :collection)
                    OR (b.user_id = :catalog_user_id AND b.statut = :wishlist)
                 ) AND o.tmdb_id = :tmdb_id'
            . CatalogSchema::sqlMediaDomainAnd('o', $params) . '
             ORDER BY CASE WHEN b.statut = :collection THEN 0 ELSE 1 END, b.id ASC
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function distinctSupportPhysique(): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT b.support_physique FROM bibliotheque b
             WHERE b.foyer_id = ? AND b.statut = ? AND TRIM(b.support_physique) != ""
             ORDER BY b.support_physique COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$this->foyerId(), LibraryStatut::COLLECTION]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row['support_physique'] ?? '');
            if (SupportPhysique::isValid($key)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public function distinctNationalites(): array
    {
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT o.nationalite FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND TRIM(o.nationalite) != ""'
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $countries = [];
        foreach ($rows as $row) {
            $label = TmdbCountries::formatNationaliteList((string) ($row['nationalite'] ?? ''));
            if ($label !== '') {
                $countries[$label] = true;
            }
        }

        $list = array_keys($countries);
        usort($list, static fn (string $a, string $b): int => strcoll($a, $b));

        return $list;
    }

    public function distinctStyles(): array
    {
        $params = [
            'catalog_foyer_id' => $this->foyerId(),
            'catalog_statut' => LibraryStatut::COLLECTION,
        ];
        $stmt = $this->db->prepare(
            'SELECT o.styles FROM ' . CatalogSchema::JOIN . '
             WHERE b.foyer_id = :catalog_foyer_id AND b.statut = :catalog_statut AND o.styles != ""'
            . CatalogSchema::sqlMediaDomainAnd('o', $params)
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $styles = [];
        foreach ($rows as $row) {
            foreach (FilmRepository::splitStyles((string) $row['styles']) as $style) {
                $styles[$style] = true;
            }
        }
        $list = array_keys($styles);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

}
