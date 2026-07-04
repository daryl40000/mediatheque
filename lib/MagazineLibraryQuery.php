<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazineLibraryQuery {
    private ?MagazineSearchSql $searchSqlCache = null;
    public function __construct(private readonly PDO $db) {}
    private function searchSql(): MagazineSearchSql { return $this->searchSqlCache ??= new MagazineSearchSql($this); }
    /**
     * Recherche des séries magazines au catalogue (pour ajout à la bibliothèque).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalogSeries(
        string $query,
        int $userId,
        int $foyerId,
        int $limit = 25
    ): array {
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $pattern = LikePattern::containsFragment($query);

        $sql = 'SELECT s.id, s.titre, s.publication_type, s.poster_url, s.editeur,
                    (SELECT COUNT(*) FROM oeuvre_magazine om WHERE om.series_id = s.id) AS catalog_issue_count,
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
            'domain' => MediaDomain::MAGAZINE,
            'pattern' => $pattern,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['in_collection'] = (int) ($row['in_collection'] ?? 0) === 1;
            $row['catalog_issue_count'] = (int) ($row['catalog_issue_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }
    /**
     * Recherche des numéros catalogue d’une série (autocomplétion à l’ajout).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalogIssues(
        int $seriesId,
        string $query,
        int $userId,
        int $foyerId,
        int $limit = 25
    ): array {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $pattern = LikePattern::containsFragment($query);

        $sql = 'SELECT o.id AS oeuvre_id, o.titre, o.poster_url, o.annee,
                    om.series_id, om.numero, om.numero_ordre, om.date_parution,
                    om.est_hors_serie, om.sommaire,
                    s.titre AS series_titre, s.publication_type
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN series s ON s.id = om.series_id
                WHERE om.series_id = :series_id
                  AND (
                    LOWER(TRIM(om.numero)) LIKE LOWER(:pattern_num) ESCAPE \'\\\'
                    OR LOWER(COALESCE(om.date_parution, \'\')) LIKE LOWER(:pattern_date) ESCAPE \'\\\'
                    OR LOWER(COALESCE(om.sommaire, \'\')) LIKE LOWER(:pattern_som) ESCAPE \'\\\'
                  )
                ORDER BY om.numero_ordre ASC, om.numero COLLATE FRENCH_NOCASE ASC
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'domain' => MediaDomain::MAGAZINE,
            'series_id' => $seriesId,
            'pattern_num' => $pattern,
            'pattern_date' => $pattern,
            'pattern_som' => $pattern,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            $bibId = $this->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
            $row['in_library'] = $bibId !== null && $bibId > 0;
            $row['library_bib_id'] = $bibId ?? 0;
        }
        unset($row);

        return $rows;
    }
    /**
     * Séries présentes dans la collection ou les envies de l’utilisateur.
     *
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
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        $params = [
            'issue_stat' => $statut === LibraryStatut::WISHLIST
                ? LibraryStatut::WISHLIST
                : LibraryStatut::COLLECTION,
            'domain_series' => MediaDomain::MAGAZINE,
            'domain_oeuvre' => MediaDomain::MAGAZINE,
        ];

        [$seriesStatutSql, $seriesStatutParams] = $this->seriesLibraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $seriesStatutParams);

        $where = [
            's.media_domain = :domain_series',
            $seriesStatutSql,
        ];

        if (trim($query) !== '') {
            $where[] = $this->searchSql()->seriesGlobalSearchFilterSql(trim($query), $userId, $foyerId, $statut, $params);
        }

        $order = MagazineCatalogSql::seriesOrderClause($sortBy, $sortDir);
        $ownedOnly = $statut !== LibraryStatut::WISHLIST;
        $ownedSql = $ownedOnly ? ' AND ' . MagazineCatalogSql::sqlIssuePossessedCondition('b', 'om') : '';

        // Séries suivies (series_bibliotheque) + numéros optionnels (LEFT JOIN).
        $sql = 'SELECT s.*,
                    COUNT(DISTINCT CASE WHEN b.statut = :issue_stat' . $ownedSql . ' THEN b.id END) AS issue_count,
                    MAX(CASE WHEN b.statut = :issue_stat' . $ownedSql . ' THEN om.numero_ordre END) AS last_numero_ordre,
                    MAX(CASE WHEN b.statut = :issue_stat' . $ownedSql . ' THEN om.date_parution END) AS last_date_parution,
                    ' . SeriesPoster::sqlFirstVolumePosterSubquery(MediaDomain::MAGAZINE) . ' AS first_volume_poster_url,
                    MAX(CASE WHEN TRIM(o.poster_url) != \'\' THEN o.poster_url END) AS latest_poster_url
                FROM series s
                INNER JOIN series_bibliotheque sb ON sb.series_id = s.id
                LEFT JOIN oeuvre_magazine om ON om.series_id = s.id
                LEFT JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain_oeuvre
                LEFT JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY s.id
                ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row = SeriesPoster::enrichSeries($row);
        }
        unset($row);

        return $rows;
    }
    public function countSeriesInLibrary(int $userId, int $foyerId, ?string $statut = null, string $query = ''): int
    {
        return count($this->listSeriesInLibrary($userId, $foyerId, $statut, 'titre', 'asc', $query));
    }
    public function countIssuesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        if (!MagazineRepository::isAvailable()) {
            return 0;
        }

        $params = [
            'domain_oeuvre' => MediaDomain::MAGAZINE,
        ];

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $statutParams);

        $where = [$statutSql];
        if ($statut === LibraryStatut::COLLECTION) {
            $where[] = MagazineCatalogSql::sqlIssuePossessedCondition('b', 'om');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }
    public function collectionPdfStats(int $userId, int $foyerId): array
    {
        if (!MagazineRepository::isAvailable() || $foyerId <= 0 || !StoredObjectRepository::tableExists()) {
            return ['count' => 0, 'total_bytes' => 0];
        }

        $sql = 'SELECT COUNT(DISTINCT b.id), COALESCE(SUM(so.size_bytes), 0)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
                INNER JOIN stored_objects so ON so.id = om.stored_object_id
                WHERE b.statut = :collection AND b.foyer_id = :foyer_id
                  AND om.stored_object_id IS NOT NULL AND om.stored_object_id > 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'domain_oeuvre' => MediaDomain::MAGAZINE,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_NUM);

        return [
            'count' => (int) ($row[0] ?? 0),
            'total_bytes' => (int) ($row[1] ?? 0),
        ];
    }
    public function listIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'numero_ordre',
        string $sortDir = 'desc',
        string $searchQuery = '',
        string $possessionFilter = MagazineRepository::POSSESSION_ALL,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }

        [$where, $params, $statutNorm] = $this->issuesForSeriesFilterContext(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $searchQuery,
            $possessionFilter
        );

        $order = MagazineCatalogSql::issueOrderClause($sortBy, $sortDir);
        $inWishlistSelect = $statutNorm === LibraryStatut::COLLECTION
            ? ', (SELECT COUNT(*) FROM bibliotheque bw
                  WHERE bw.oeuvre_id = o.id
                    AND bw.statut = :in_wishlist_statut
                    AND bw.user_id = :in_wishlist_user) AS in_wishlist'
            : ', 0 AS in_wishlist';

        $sql = 'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.numero, om.numero_ordre, om.date_parution, om.sommaire, om.pages,
                    om.est_hors_serie, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type' . $inWishlistSelect . '
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN series s ON s.id = om.series_id
                INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order;

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, (int) ($offset ?? 0));
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function searchIssuesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        int $limit = 30
    ): array {
        $searchQuery = trim($searchQuery);
        if (!MagazineRepository::isAvailable() || $searchQuery === '') {
            return [];
        }

        $statutNorm = $statut !== null ? LibraryStatut::normalize($statut) : null;
        $params = [
            'domain' => MediaDomain::MAGAZINE,
            'domain_series' => MediaDomain::MAGAZINE,
            'in_wishlist_user' => $userId,
            'in_wishlist_statut' => LibraryStatut::WISHLIST,
        ];

        [$seriesStatutSql, $seriesStatutParams] = $this->seriesLibraryStatutFilter($statut, $userId, $foyerId);
        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        [$searchSql, $searchParams] = $this->searchSql()->issueGlobalSearchFilterSql($searchQuery);
        if ($searchSql === '') {
            return [];
        }

        $params = array_merge($params, $seriesStatutParams, $statutParams, $searchParams);

        $where = [
            's.media_domain = :domain_series',
            $seriesStatutSql,
            $statutSql,
            $searchSql,
        ];
        if ($statutNorm === LibraryStatut::COLLECTION) {
            $where[] = MagazineCatalogSql::sqlIssuePossessedCondition('b', 'om');
        }

        $limit = max(1, min($limit, 50));
        $inWishlistSelect = $statutNorm === LibraryStatut::COLLECTION
            ? ', (SELECT COUNT(*) FROM bibliotheque bw
                  WHERE bw.oeuvre_id = o.id
                    AND bw.statut = :in_wishlist_statut
                    AND bw.user_id = :in_wishlist_user) AS in_wishlist'
            : ', 0 AS in_wishlist';

        $sql = 'SELECT b.id AS bib_id, b.statut, b.support_physique, b.created_at AS bib_created_at,
                    o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.numero, om.numero_ordre, om.date_parution, om.sommaire, om.pages,
                    om.est_hors_serie, om.stored_object_id,
                    s.id AS series_id, s.titre AS series_titre, s.publication_type' . $inWishlistSelect . '
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN series s ON s.id = om.series_id
                INNER JOIN series_bibliotheque sb ON sb.series_id = s.id
                INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY om.date_parution DESC, om.numero_ordre DESC, b.id DESC
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function countIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        string $possessionFilter = MagazineRepository::POSSESSION_ALL
    ): int {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        [$where, $params] = $this->issuesForSeriesFilterContext(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $searchQuery,
            $possessionFilter
        );

        $sql = 'SELECT COUNT(*)
                FROM oeuvre_magazine om
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN series s ON s.id = om.series_id
                INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute(MagazineCatalogSql::filterParamsForSql($sql, $params));

        return (int) ($stmt->fetchColumn() ?: 0);
    }
    /**
     * Filtres communs liste / comptage des numéros d’une série.
     *
     * @return array{0: list<string>, 1: array<string, mixed>, 2: ?string}
     */
    private function issuesForSeriesFilterContext(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut,
        string $searchQuery,
        string $possessionFilter
    ): array {
        $statutNorm = $statut !== null ? LibraryStatut::normalize($statut) : null;
        $possessionFilter = MagazineRepository::normalizePossessionFilter($possessionFilter);

        $params = [
            'series_id' => $seriesId,
            'domain' => MediaDomain::MAGAZINE,
            'in_wishlist_user' => $userId,
            'in_wishlist_statut' => LibraryStatut::WISHLIST,
        ];

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $statutParams);

        $where = ['om.series_id = :series_id', $statutSql];
        $this->appendPossessionFilterToWhere($where, $statutNorm, $possessionFilter);
        [$searchSql, $searchParams] = $this->searchSql()->issueGlobalSearchFilterSql($searchQuery);
        if ($searchSql !== '') {
            $where[] = $searchSql;
            $params = array_merge($params, $searchParams);
        }

        return [$where, $params, $statutNorm];
    }
    public function findIssueByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!MagazineRepository::isAvailable() || $bibId <= 0) {
            return null;
        }

        $params = [
            'bib_id' => $bibId,
            'user_id' => $userId,
            'foyer_id' => $foyerId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'wishlist_check' => LibraryStatut::WISHLIST,
            'domain' => MediaDomain::MAGAZINE,
        ];

        $stmt = $this->db->prepare(
            'SELECT b.id AS bib_id, b.statut, b.support_physique, b.user_id, b.foyer_id,
                    o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.series_id, om.numero, om.numero_ordre, om.date_parution, om.sommaire,
                    om.pages, om.est_hors_serie, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type, s.editeur, s.issn, s.poster_url AS series_poster_url,
                    s.tags AS series_tags,
                    (SELECT COUNT(*) FROM bibliotheque bw
                     WHERE bw.oeuvre_id = o.id
                       AND bw.statut = :wishlist_check
                       AND bw.user_id = :user_id) AS in_wishlist
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
             INNER JOIN series s ON s.id = om.series_id
             WHERE b.id = :bib_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
    public function maxNumeroOrdreForSeries(int $seriesId): float
    {
        if ($seriesId <= 0) {
            return 0.0;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(numero_ordre) FROM oeuvre_magazine WHERE series_id = ?'
        );
        $stmt->execute([$seriesId]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }
    public function resolveIssueBibIdForRedirect(int $oeuvreId, int $userId, int $foyerId, int $fallbackBibId = 0): int
    {
        if ($oeuvreId <= 0) {
            return $fallbackBibId;
        }

        $bibRepo = new BibliothequeRepository();
        $collection = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::COLLECTION);
        if ($collection !== null) {
            return (int) ($collection['id'] ?? 0);
        }

        $wishlist = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::WISHLIST);
        if ($wishlist !== null) {
            return (int) ($wishlist['id'] ?? 0);
        }

        return $fallbackBibId;
    }
    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        $bibId = $this->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId);
        if ($bibId <= 0) {
            return null;
        }

        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);

        return $issue !== null ? $bibId : null;
    }
    public function findCatalogIssueBySeriesNumero(
        int $seriesId,
        string $numero,
        ?bool $horsSerie = null,
        ?int $excludeOeuvreId = null
    ): ?array {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return null;
        }

        $numero = trim($numero);
        if ($numero === '') {
            return null;
        }

        $sql = 'SELECT o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.series_id, om.numero, om.numero_ordre, om.date_parution,
                    om.est_hors_serie
             FROM oeuvre_magazine om
             INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = ?
             WHERE om.series_id = ? AND LOWER(TRIM(om.numero)) = LOWER(?)';
        $params = [MediaDomain::MAGAZINE, $seriesId, $numero];

        if ($horsSerie !== null) {
            $sql .= ' AND om.est_hors_serie = ?';
            $params[] = $horsSerie ? 1 : 0;
        }
        if ($excludeOeuvreId !== null && $excludeOeuvreId > 0) {
            $sql .= ' AND om.oeuvre_id != ?';
            $params[] = $excludeOeuvreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
    public function findCatalogIssueByOeuvreId(int $oeuvreId): ?array
    {
        if (!MagazineRepository::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . MagazineCatalogSql::selectCatalogIssueRow() . '
             FROM oeuvres o
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
             INNER JOIN series s ON s.id = om.series_id
             WHERE o.id = ? AND o.media_domain = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::MAGAZINE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
    /** @return array{0: string, 1: array<string, int|string>} */
    public function seriesLibraryStatutFilter(?string $statut, int $userId, int $foyerId): array
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
    /** @param array<string, int|string> $params */
    public function libraryStatutFilter(?string $statut, int $userId, int $foyerId): array
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
    private function appendPossessionFilterToWhere(array &$where, ?string $statut, string $possessionFilter): void
    {
        $possessionFilter = MagazineRepository::normalizePossessionFilter($possessionFilter);
        if ($possessionFilter === MagazineRepository::FILTER_HORS_SERIE) {
            $where[] = 'om.est_hors_serie = 1';

            return;
        }

        if ($statut !== LibraryStatut::COLLECTION) {
            return;
        }

        if ($possessionFilter === MagazineRepository::POSSESSION_OWNED) {
            $where[] = MagazineCatalogSql::sqlIssuePossessedCondition('b', 'om');
        } elseif ($possessionFilter === MagazineRepository::POSSESSION_UNOWNED) {
            $where[] = 'NOT ' . MagazineCatalogSql::sqlIssuePossessedCondition('b', 'om');
        }
    }
}
