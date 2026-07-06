<?php
/**
 * Lectures bibliothèque et catalogue BD (listes, fiches, recherche).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdLibraryQuery
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSeriesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $query = ''
    ): array {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        $params = [
            'tome_stat' => $statut === LibraryStatut::WISHLIST
                ? LibraryStatut::WISHLIST
                : LibraryStatut::COLLECTION,
            'domain_series' => MediaDomain::BD,
            'domain_oeuvre' => MediaDomain::BD,
        ];

        [$seriesStatutSql, $seriesStatutParams] = $this->seriesLibraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $seriesStatutParams);

        $where = [
            's.media_domain = :domain_series',
            $seriesStatutSql,
        ];

        $query = trim($query);
        if ($query !== '') {
            $pattern = LikePattern::containsFragment($query);
            $where[] = '(LOWER(s.titre) LIKE LOWER(:series_q) ESCAPE \'\\\''
                . ' OR LOWER(COALESCE(s.editeur, \'\')) LIKE LOWER(:series_q_ed) ESCAPE \'\\\')';
            $params['series_q'] = $pattern;
            $params['series_q_ed'] = $pattern;
        }

        $order = BdCatalogSql::seriesOrderClause($sortBy, $sortDir);
        $ownedOnly = $statut !== LibraryStatut::WISHLIST;
        $ownedSql = $ownedOnly ? ' AND ' . BdCatalogSql::sqlTomePossessedCondition('b') : '';
        if ($statut === LibraryStatut::WISHLIST) {
            $tomeScopeSql = ' AND b.user_id = :tome_scope_user_id';
            $params['tome_scope_user_id'] = $userId;
        } else {
            $tomeScopeSql = ' AND b.foyer_id = :tome_scope_foyer_id';
            $params['tome_scope_foyer_id'] = $foyerId;
        }

        $sql = 'SELECT s.*,
                    (SELECT COUNT(*) FROM oeuvre_bd ob_cat WHERE ob_cat.series_id = s.id) AS catalog_tome_count,
                    COUNT(DISTINCT CASE WHEN b.statut = :tome_stat' . $tomeScopeSql . $ownedSql . ' THEN b.id END) AS possessed_tome_count,
                    MAX(CASE WHEN b.statut = :tome_stat' . $tomeScopeSql . $ownedSql . ' THEN ob.tome_numero END) AS last_tome_numero,
                    ' . SeriesPoster::sqlFirstVolumePosterSubquery(MediaDomain::BD) . ' AS first_volume_poster_url,
                    MAX(CASE WHEN TRIM(o.poster_url) != \'\' THEN o.poster_url END) AS latest_poster_url
                FROM series s
                INNER JOIN series_bibliotheque sb ON sb.series_id = s.id
                LEFT JOIN oeuvre_bd ob ON ob.series_id = s.id
                LEFT JOIN oeuvres o ON o.id = ob.oeuvre_id AND o.media_domain = :domain_oeuvre
                LEFT JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY s.id
                ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(BdCatalogSql::filterParamsForSql($sql, $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['catalog_tome_count'] = (int) ($row['catalog_tome_count'] ?? 0);
            $row['possessed_tome_count'] = (int) ($row['possessed_tome_count'] ?? 0);
            $row['tome_count'] = $row['possessed_tome_count'];
            $row['kind'] = BdSeriesMetadata::kindFromSeries($row);
            $row['kind_label'] = BdKind::label($row['kind']);
            $row = SeriesPoster::enrichSeries($row);
        }
        unset($row);

        return $rows;
    }

    public function countSeriesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $query = ''
    ): int {
        return count($this->listSeriesInLibrary($userId, $foyerId, $statut, 'titre', 'asc', $query));
    }

    public function countTomesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        if (!BdRepository::isAvailable()) {
            return 0;
        }

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge(['domain_oeuvre' => MediaDomain::BD], $statutParams);

        $where = [$statutSql];
        if ($statut !== LibraryStatut::WISHLIST) {
            $where[] = BdCatalogSql::sqlTomePossessedCondition('b');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
             INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id
             WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(BdCatalogSql::filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }

    public function countCatalogTomesForSeries(int $seriesId): int
    {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM oeuvre_bd WHERE series_id = ?');
        $stmt->execute([$seriesId]);

        return (int) $stmt->fetchColumn();
    }

    public function countPossessedTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null
    ): int {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $statut = $statut !== null ? LibraryStatut::normalize($statut) : LibraryStatut::COLLECTION;
        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge([
            'series_id' => $seriesId,
            'domain_oeuvre' => MediaDomain::BD,
        ], $statutParams);

        $where = [
            'ob.series_id = :series_id',
            $statutSql,
        ];
        if ($statut !== LibraryStatut::WISHLIST) {
            $where[] = BdCatalogSql::sqlTomePossessedCondition('b');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(BdCatalogSql::filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }

    public function maxTomeNumeroForSeries(int $seriesId): int
    {
        if ($seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(tome_numero) FROM oeuvre_bd WHERE series_id = ?'
        );
        $stmt->execute([$seriesId]);

        return (int) $stmt->fetchColumn();
    }

    public function maxTomeOrdreForSeries(int $seriesId): float
    {
        if ($seriesId <= 0) {
            return 0.0;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(tome_ordre) FROM oeuvre_bd WHERE series_id = ?'
        );
        $stmt->execute([$seriesId]);

        return (float) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findStandardTomeBySeriesAndNumero(int $seriesId, int $tomeNumero): ?array
    {
        if ($seriesId <= 0 || $tomeNumero < 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . BdCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ob.series_id = ? AND ob.tome_numero = ? AND ob.est_hors_serie = 0'
            . ' AND o.media_domain = ? LIMIT 1'
        );
        $stmt->execute([$seriesId, $tomeNumero, MediaDomain::BD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateCatalogRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'tome',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?string $possessionFilter = null
    ): array {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge([
            'series_id' => $seriesId,
            'domain' => MediaDomain::BD,
            'history_user_id' => $userId,
        ], $statutParams);

        $where = [
            'ob.series_id = :series_id',
            'o.media_domain = :domain',
            $statutSql,
        ];

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = BdCatalogSql::bdSearchSqlConditions($searchQuery, 'series_tome');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $this->appendPossessionFilterToWhere($where, $statut, $possessionFilter);

        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $order = match ($sortBy) {
            'titre' => 'o.titre COLLATE FRENCH_NOCASE ' . $direction,
            'annee' => 'o.annee ' . $direction . ', ob.tome_ordre ASC',
            'read_at' => 'derniere_lecture IS NULL ASC, derniere_lecture ' . $direction,
            'note' => 'note_max IS NULL ASC, note_max ' . $direction,
            default => 'ob.tome_ordre ' . $direction . ', o.titre COLLATE FRENCH_NOCASE ASC',
        };

        $sql = 'SELECT ' . BdCatalogSql::selectBdRow() . BdCatalogSql::selectBdHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' INNER JOIN series s ON s.id = ob.series_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(BdCatalogSql::filterParamsForSql($sql, $params));

        return array_map([BdRowMapper::class, 'hydrateBdRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        ?string $possessionFilter = null
    ): int {
        return count($this->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            'tome',
            'asc',
            $searchQuery,
            $possessionFilter
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalogSeries(
        string $query,
        int $userId,
        int $foyerId,
        int $limit = 25
    ): array {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $pattern = LikePattern::containsFragment($query);

        $sql = 'SELECT s.id, s.titre, s.poster_url, s.editeur, s.tags,
                    (SELECT COUNT(*) FROM oeuvre_bd ob WHERE ob.series_id = s.id) AS catalog_tome_count,
                    EXISTS (
                        SELECT 1 FROM series_bibliotheque sb
                        WHERE sb.series_id = s.id
                          AND sb.statut = :collection_stat
                          AND sb.foyer_id = :foyer_id
                    ) AS in_collection
                FROM series s
                WHERE s.media_domain = :domain
                  AND LOWER(s.titre) LIKE LOWER(:pattern) ESCAPE \'\\\'
                ORDER BY s.titre COLLATE FRENCH_NOCASE
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'collection_stat' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
            'domain' => MediaDomain::BD,
            'pattern' => $pattern,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['in_collection'] = (int) ($row['in_collection'] ?? 0) === 1;
            $row['catalog_tome_count'] = (int) ($row['catalog_tome_count'] ?? 0);
            $row['kind_label'] = BdSeriesMetadata::kindLabelFromSeries($row);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findCatalogTomeBySeriesAndNumero(int $seriesId, int $tomeNumero): ?array
    {
        if ($seriesId <= 0 || $tomeNumero < 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . BdCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ob.series_id = ? AND ob.tome_numero = ? AND o.media_domain = ? LIMIT 1'
        );
        $stmt->execute([$seriesId, $tomeNumero, MediaDomain::BD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateCatalogRow($row) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?BdListFilter $filter = null
    ): array {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        if (!BdCatalogSql::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = BdCatalogSql::sortOrderExpression($sortBy);
        $readAtSort = $sortBy === 'read_at';

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :bd_domain',
            $userWhere,
        ];
        $params['bd_domain'] = MediaDomain::BD;
        $params['history_user_id'] = $userId;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = BdCatalogSql::bdSearchSqlConditions($searchQuery, 'q');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        ($filter ?? BdListFilter::empty())->applyToSql($where, $params);

        $sql = 'SELECT ' . BdCatalogSql::selectBdRow() . BdCatalogSql::selectBdHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ' . implode(' AND ', $where);
        if ($readAtSort) {
            $sql .= ' ORDER BY derniere_lecture IS NULL ASC, derniere_lecture ' . $direction;
        } else {
            $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        }
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([BdRowMapper::class, 'hydrateBdRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $searchQuery = '',
        ?BdListFilter $filter = null
    ): int {
        if (!BdRepository::isAvailable()) {
            return 0;
        }

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :bd_domain',
            $userWhere,
        ];
        $params['bd_domain'] = MediaDomain::BD;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = BdCatalogSql::bdSearchSqlConditions($searchQuery, 'q_count');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        ($filter ?? BdListFilter::empty())->applyToSql($where, $params);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!BdRepository::isAvailable() || $bibId <= 0) {
            return null;
        }

        $params = [
            'bib_id' => $bibId,
            'bd_domain' => MediaDomain::BD,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
            'history_user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT ' . BdCatalogSql::selectBdRow() . BdCatalogSql::selectBdHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE b.id = :bib_id'
            . ' AND o.media_domain = :bd_domain'
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateBdRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        if (!BdRepository::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . BdCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE o.id = ? AND o.media_domain = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::BD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateCatalogRow($row) : null;
    }

    /**
     * Tous les tomes catalogue d’une série, triés par ordre de lecture.
     *
     * @return list<array<string, mixed>>
     */
    public function listCatalogTomesForSeries(int $seriesId): array
    {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ' . BdCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ob.series_id = ? AND o.media_domain = ?'
            . ' ORDER BY ob.tome_ordre ASC, ob.tome_numero ASC, o.titre COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([$seriesId, MediaDomain::BD]);

        return array_map(
            [BdRowMapper::class, 'hydrateCatalogRow'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        );
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if (!BdRepository::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $params = [
            'oeuvre_id' => $oeuvreId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
            'bd_domain' => MediaDomain::BD,
        ];

        $stmt = $this->db->prepare(
            'SELECT b.id FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' WHERE b.oeuvre_id = :oeuvre_id'
            . ' AND o.media_domain = :bd_domain'
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' ORDER BY b.id DESC LIMIT 1'
        );
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $params = ['bd_domain' => MediaDomain::BD];
        $where = ['o.media_domain = :bd_domain'];

        $query = trim($query);
        if ($query !== '') {
            [$searchSql, $searchParams] = BdCatalogSql::bdSearchSqlConditions($query, 'q_cat');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT ' . BdCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([BdRowMapper::class, 'hydrateCatalogRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<string> */
    public function listKnownGenres(int $limit = 40): array
    {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query(
            'SELECT DISTINCT ob.genre FROM oeuvre_bd ob'
            . ' INNER JOIN oeuvres o ON o.id = ob.oeuvre_id'
            . ' WHERE o.media_domain = \'' . MediaDomain::BD . '\''
            . ' AND ob.genre != \'\''
            . ' ORDER BY ob.genre COLLATE FRENCH_NOCASE'
            . ' LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }

        $genres = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $genre = trim((string) ($row['genre'] ?? ''));
            if ($genre !== '') {
                $genres[] = $genre;
            }
        }

        return $genres;
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function seriesLibraryStatutFilter(?string $statut, int $userId, int $foyerId): array
    {
        $statut = $statut !== null ? LibraryStatut::normalize($statut) : null;

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                '(sb.statut = :sb_collection AND sb.foyer_id = :sb_foyer_id)',
                [
                    'sb_collection' => LibraryStatut::COLLECTION,
                    'sb_foyer_id' => $foyerId,
                ],
            ];
        }

        if ($statut === LibraryStatut::WISHLIST) {
            return [
                '(sb.statut = :sb_wishlist AND sb.user_id = :sb_user_id)',
                [
                    'sb_wishlist' => LibraryStatut::WISHLIST,
                    'sb_user_id' => $userId,
                ],
            ];
        }

        return [
            '((sb.statut = :sb_collection_scope AND sb.foyer_id = :sb_foyer_scope)
              OR (sb.statut = :sb_wishlist_scope AND sb.user_id = :sb_user_scope))',
            [
                'sb_collection_scope' => LibraryStatut::COLLECTION,
                'sb_wishlist_scope' => LibraryStatut::WISHLIST,
                'sb_foyer_scope' => $foyerId,
                'sb_user_scope' => $userId,
            ],
        ];
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function libraryStatutFilter(?string $statut, int $userId, int $foyerId): array
    {
        $statut = $statut !== null ? LibraryStatut::normalize($statut) : null;

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                '(b.statut = :collection_filter AND b.foyer_id = :foyer_id)',
                [
                    'collection_filter' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                ],
            ];
        }

        if ($statut === LibraryStatut::WISHLIST) {
            return [
                '(b.statut = :wishlist_filter AND b.user_id = :user_id)',
                [
                    'wishlist_filter' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                ],
            ];
        }

        return [
            '((b.statut = :collection_scope AND b.foyer_id = :foyer_id)
              OR (b.statut = :wishlist_scope AND b.user_id = :user_id))',
            [
                'collection_scope' => LibraryStatut::COLLECTION,
                'wishlist_scope' => LibraryStatut::WISHLIST,
                'foyer_id' => $foyerId,
                'user_id' => $userId,
            ],
        ];
    }

    /** @param list<string> $where */
    private function appendPossessionFilterToWhere(
        array &$where,
        ?string $statut,
        ?string $possessionFilter
    ): void {
        if ($possessionFilter === null || $possessionFilter === '') {
            return;
        }

        $possessionFilter = BdRepository::normalizePossessionFilter($possessionFilter);
        if ($possessionFilter === BdRepository::FILTER_HORS_SERIE) {
            $where[] = 'ob.est_hors_serie = 1';

            return;
        }

        if ($statut !== LibraryStatut::COLLECTION) {
            return;
        }

        if ($possessionFilter === BdRepository::POSSESSION_OWNED) {
            $where[] = BdCatalogSql::sqlTomePossessedCondition('b');
        } elseif ($possessionFilter === BdRepository::POSSESSION_UNOWNED) {
            $where[] = 'NOT ' . BdCatalogSql::sqlTomePossessedCondition('b');
        }
    }
}
