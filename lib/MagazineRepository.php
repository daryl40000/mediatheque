<?php
/**
 * Numéros de magazines : catalogue (oeuvres + oeuvre_magazine) et collection.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineRepository
{
    public const POSSESSION_ALL = 'all';
    public const POSSESSION_OWNED = 'owned';
    public const POSSESSION_UNOWNED = 'unowned';
    public const FILTER_HORS_SERIE = 'hors_serie';

    /** Numéros affichés par page sur la liste série (8 colonnes × 6 lignes). */
    public const ISSUES_PER_PAGE = 48;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return SeriesRepository::tableExists()
            && self::seriesLibraryTableExists()
            && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    /** Titre catalogue d’un numéro (suffixe HS pour éviter les doublons de titre œuvre). */
    public static function buildCatalogIssueTitle(string $seriesTitre, string $numero, bool $horsSerie = false): string
    {
        $seriesTitre = trim($seriesTitre);
        $numero = trim($numero);
        $base = $seriesTitre !== '' ? $seriesTitre . ' — n°' . $numero : 'n°' . $numero;

        return $horsSerie ? $base . ' (HS)' : $base;
    }

    public static function seriesLibraryTableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series_bibliotheque' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function pdfTextPreviewColumnExists(): bool
    {
        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_magazine)');
        if ($stmt === false) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (($column['name'] ?? '') === 'pdf_text_preview') {
                return true;
            }
        }

        return false;
    }

    public static function normalizePossessionFilter(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return match ($raw) {
            self::POSSESSION_OWNED, 'possede', 'possédé', 'owned' => self::POSSESSION_OWNED,
            self::POSSESSION_UNOWNED, 'non_possede', 'non-possede', 'unowned' => self::POSSESSION_UNOWNED,
            self::FILTER_HORS_SERIE, 'hors-serie', 'hors_série', 'special' => self::FILTER_HORS_SERIE,
            default => self::POSSESSION_ALL,
        };
    }

    /** Ajoute une série à la collection ou aux envies (sans numéro). */
    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        if (!self::seriesLibraryTableExists() || $seriesId <= 0) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $this->db->prepare(
                'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
                 VALUES (?, ?, ?, ?)'
            )->execute([$seriesId, $userId, $foyerId, LibraryStatut::COLLECTION]);

            return true;
        }

        $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        )->execute([$seriesId, $userId, $foyerId, LibraryStatut::WISHLIST]);

        return true;
    }

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
        if (!self::isAvailable()) {
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
     * Ajoute à la collection tous les numéros catalogue d’une série (non possédés, sans papier ni PDF).
     *
     * @return int nombre de numéros nouvellement rattachés
     */
    public function attachCatalogIssuesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        if (!self::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = ? ORDER BY numero_ordre ASC'
        );
        $stmt->execute([$seriesId]);

        $attached = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }

            $result = $this->addFromCatalogOeuvre(
                $oeuvreId,
                LibraryStatut::COLLECTION,
                $userId,
                $foyerId
            );
            if (is_int($result)) {
                $attached++;
            }
        }

        return $attached;
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
        if (!self::isAvailable() || $seriesId <= 0) {
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
        if (!self::isAvailable()) {
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
            $where[] = $this->seriesGlobalSearchFilterSql(trim($query), $userId, $foyerId, $statut, $params);
        }

        $order = $this->seriesOrderClause($sortBy, $sortDir);
        $ownedOnly = $statut !== LibraryStatut::WISHLIST;
        $ownedSql = $ownedOnly ? ' AND ' . $this->sqlIssuePossessedCondition('b', 'om') : '';

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
        $stmt->execute($this->filterParamsForSql($sql, $params));
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

    /** Nombre de numéros (collection : possédés uniquement ; envies : tous). */
    public function countIssuesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        if (!self::isAvailable()) {
            return 0;
        }

        $params = [
            'domain_oeuvre' => MediaDomain::MAGAZINE,
        ];

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge($params, $statutParams);

        $where = [$statutSql];
        if ($statut === LibraryStatut::COLLECTION) {
            $where[] = $this->sqlIssuePossessedCondition('b', 'om');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }

    /**
     * PDF en collection du foyer : nombre de numéros et taille cumulée (stored_objects.size_bytes).
     *
     * @return array{count: int, total_bytes: int}
     */
    public function collectionPdfStats(int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || $foyerId <= 0 || !StoredObjectRepository::tableExists()) {
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

    /** Affiche une taille en Go (ou Mo si très petit). */
    public static function formatPdfStorageGigabytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Go';
        }

        $gigabytes = $bytes / (1024 ** 3);
        if ($gigabytes >= 1) {
            return number_format($gigabytes, 1, ',', ' ') . ' Go';
        }
        if ($gigabytes >= 0.01) {
            return number_format($gigabytes, 2, ',', ' ') . ' Go';
        }

        $megabytes = $bytes / (1024 ** 2);

        return number_format($megabytes, 0, ',', ' ') . ' Mo';
    }

    /**
     * Numéros d’une série dans la bibliothèque.
     *
     * @return list<array<string, mixed>>
     */
    public function listIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'numero_ordre',
        string $sortDir = 'desc',
        string $searchQuery = '',
        string $possessionFilter = self::POSSESSION_ALL,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        if (!self::isAvailable() || $seriesId <= 0) {
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

        $order = $this->issueOrderClause($sortBy, $sortDir);
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
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Numéros de la bibliothèque correspondant à une recherche globale (sommaire, PDF, n°, date).
     *
     * @return list<array<string, mixed>>
     */
    public function searchIssuesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        int $limit = 30
    ): array {
        $searchQuery = trim($searchQuery);
        if (!self::isAvailable() || $searchQuery === '') {
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
        [$searchSql, $searchParams] = $this->issueGlobalSearchFilterSql($searchQuery);
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
            $where[] = $this->sqlIssuePossessedCondition('b', 'om');
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
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Nombre de numéros correspondant aux filtres (même logique que listIssuesForSeries). */
    public function countIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        string $possessionFilter = self::POSSESSION_ALL
    ): int {
        if (!self::isAvailable() || $seriesId <= 0) {
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
        $stmt->execute($this->filterParamsForSql($sql, $params));

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
        $possessionFilter = self::normalizePossessionFilter($possessionFilter);

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
        [$searchSql, $searchParams] = $this->issueGlobalSearchFilterSql($searchQuery);
        if ($searchSql !== '') {
            $where[] = $searchSql;
            $params = array_merge($params, $searchParams);
        }

        return [$where, $params, $statutNorm];
    }

    public function findIssueByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!self::isAvailable() || $bibId <= 0) {
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

    /**
     * Identifiant bibliothèque valide pour afficher la fiche après une action (PDF, papier…).
     * Priorité : collection du foyer, puis envies personnelles.
     */
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

    /** Id bibliothèque pour une fiche catalogue magazine, ou null. */
    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        $bibId = $this->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId);
        if ($bibId <= 0) {
            return null;
        }

        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);

        return $issue !== null ? $bibId : null;
    }

    /**
     * Numéro catalogue existant pour une série (correspondance sur le libellé numéro).
     * Un numéro classique et un hors-série peuvent partager le même libellé (ex. « 1 »).
     *
     * @return array<string, mixed>|null
     */
    public function findCatalogIssueBySeriesNumero(
        int $seriesId,
        string $numero,
        ?bool $horsSerie = null,
        ?int $excludeOeuvreId = null
    ): ?array {
        if (!self::isAvailable() || $seriesId <= 0) {
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

    /**
     * @return string|null message d’erreur si le numéro entre en conflit
     */
    public function validateNumeroForSeries(
        int $seriesId,
        string $numero,
        bool $horsSerie,
        ?int $excludeOeuvreId = null
    ): ?string {
        if ($this->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie, $excludeOeuvreId) !== null) {
            return $horsSerie
                ? 'Un autre hors-série avec ce numéro existe déjà pour cette revue.'
                : 'Ce numéro existe déjà pour cette série.';
        }

        return null;
    }

    /** Décale l’ordre de tri pour les hors-série (ex. 16 → 16.5). */
    private function adjustNumeroOrdreForHorsSerie(float $numeroOrdre, bool $horsSerie): float
    {
        if ($horsSerie && $numeroOrdre > 0 && $numeroOrdre === (float) (int) $numeroOrdre) {
            return $numeroOrdre + 0.5;
        }

        if (
            !$horsSerie
            && $numeroOrdre > 0
            && $numeroOrdre === (float) (int) $numeroOrdre + 0.5
        ) {
            return (float) (int) $numeroOrdre;
        }

        return $numeroOrdre;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $issue
     */
    private function horsSerieFromData(array $data, array $issue): bool
    {
        if (!array_key_exists('est_hors_serie', $data)) {
            return !empty($issue['est_hors_serie']);
        }

        if (is_bool($data['est_hors_serie'])) {
            return $data['est_hors_serie'];
        }

        return FormCheckbox::isChecked(['est_hors_serie' => $data['est_hors_serie']], 'est_hors_serie');
    }

    /** Évite les collisions sur UNIQUE (titre, réalisateur) lors d’une mise à jour catalogue. */
    private function validateCatalogIssueTitleUnique(string $titre, int $oeuvreId): ?string
    {
        $existing = (new OeuvreRepository())->findByTitreRealisateurAndDomain(
            $titre,
            '',
            MediaDomain::MAGAZINE
        );
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $oeuvreId) {
            return 'Une autre fiche catalogue utilise déjà le titre « ' . $titre
                . ' » (œuvre #' . (int) $existing['id']
                . '). Fusionnez les doublons depuis Maintenance catalogue.';
        }

        return null;
    }

    /**
     * Crée un numéro magazine dans le catalogue partagé (sans bibliothèque).
     *
     * @param array<string, mixed> $data
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createCatalogIssue(int $seriesId, array $data): int|string
    {
        if (!self::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = !empty($data['est_hors_serie']);
        $numeroError = $this->validateNumeroForSeries($seriesId, $numero, $horsSerie);
        if ($numeroError !== null) {
            return $numeroError;
        }

        $numeroOrdre = (float) ($data['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero)
                ? (float) $numero
                : $this->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        if ($horsSerie && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $seriesTitre = trim((string) ($data['series_titre'] ?? $series['titre'] ?? ''));
        $titre = self::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);
        $dateParution = trim((string) ($data['date_parution'] ?? ''));
        $annee = max(0, (int) ($data['annee'] ?? 0));
        $posterUrl = SecureUrl::sanitizePosterUrl((string) ($data['poster_url'] ?? ''));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => $annee,
                'synopsis' => '',
                'poster_url' => $posterUrl,
                'media_domain' => MediaDomain::MAGAZINE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId,
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                trim((string) ($data['sommaire'] ?? '')),
                max(0, (int) ($data['pages'] ?? 0)),
                $horsSerie ? 1 : 0,
            ]);

            $this->db->commit();
            MagazineIssueFts::upsert($oeuvreId);

            return $oeuvreId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineRepository::createCatalogIssue: ' . $e->getMessage());

            return 'Impossible d’enregistrer le numéro catalogue.';
        }
    }

    /** Fiche catalogue magazine (indépendamment de la bibliothèque).
     *
     * @return array<string, mixed>|null
     */
    public function findCatalogIssueByOeuvreId(int $oeuvreId): ?array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id, o.titre, o.poster_url,
                    om.series_id, om.numero, om.numero_ordre, om.date_parution, om.sommaire,
                    om.pages, om.est_hors_serie, om.stored_object_id,
                    s.titre AS series_titre, s.publication_type, s.editeur, s.issn,
                    s.poster_url AS series_poster_url, s.tags AS series_tags
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

    /**
     * Ajoute un numéro catalogue à la bibliothèque (sans formulaire détaillé).
     *
     * @return int|string bib_id ou message d’erreur
     */
    public function addFromCatalogOeuvre(int $oeuvreId, string $statut, int $userId, int $foyerId): int|string
    {
        if (!self::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $issue = $this->findCatalogIssueByOeuvreId($oeuvreId);
        if ($issue === null) {
            return 'Ce numéro n’existe pas dans le catalogue.';
        }

        $statut = LibraryStatut::normalize($statut);
        $bibRepo = new BibliothequeRepository();
        $library = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library !== null) {
            $bibId = (int) ($library['id'] ?? 0);
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce numéro existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $foyerId;
            } else {
                $update['foyer_id'] = null;
            }
            $bibRepo->update($bibId, $update);

            return $bibId;
        }

        $seriesId = (int) ($issue['series_id'] ?? 0);
        $hasPdf = (int) ($issue['stored_object_id'] ?? 0) > 0;
        $support = MagazineSupport::formatTagsForStorage(false, $hasPdf);

        $this->db->beginTransaction();
        try {
            $bibId = $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);
            $seriesResult = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($seriesResult !== true) {
                throw new \RuntimeException((string) $seriesResult);
            }

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Impossible d’ajouter le numéro à votre bibliothèque.';
        }
    }

    /**
     * Met à jour un numéro catalogue à partir de son identifiant œuvre (admin).
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data): bool|string
    {
        $issue = $this->findCatalogIssueByOeuvreId($oeuvreId);
        if ($issue === null) {
            return 'Numéro introuvable dans le catalogue.';
        }

        $seriesId = (int) ($issue['series_id'] ?? 0);
        $numero = trim((string) ($data['numero'] ?? $issue['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = $this->horsSerieFromData($data, $issue);
        $wasHorsSerie = !empty($issue['est_hors_serie']);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $seriesTitre = trim((string) ($series['titre'] ?? $issue['series_titre'] ?? ''));
        $titre = self::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $issue['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero)
                ? (float) $numero
                : $this->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        $numeroError = $this->validateNumeroForSeries($seriesId, $numero, $horsSerie, $oeuvreId);
        if ($numeroError !== null) {
            if ($wasHorsSerie && !$horsSerie) {
                return 'Impossible de retirer le hors-série : un numéro classique « '
                    . $numero . ' » existe déjà pour cette revue. '
                    . 'Fusionnez les doublons depuis Maintenance catalogue → Doublons magazines.';
            }

            return $numeroError;
        }

        $numeroOrdre = $this->adjustNumeroOrdreForHorsSerie($numeroOrdre, $horsSerie);

        $dateParution = trim((string) ($data['date_parution'] ?? $issue['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? $issue['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? $issue['pages'] ?? 0));
        $posterUrl = SecureUrl::sanitizePosterUrl(trim((string) ($data['poster_url'] ?? $issue['poster_url'] ?? '')));

        $titleError = $this->validateCatalogIssueTitleUnique($titre, $oeuvreId);
        if ($titleError !== null) {
            return $titleError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'poster_url' => $posterUrl,
            ], ['titre', 'poster_url']);

            $this->db->prepare(
                'UPDATE oeuvre_magazine SET
                    numero = ?, numero_ordre = ?, date_parution = ?, sommaire = ?,
                    pages = ?, est_hors_serie = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                $oeuvreId,
            ]);

            $this->db->commit();
            MagazineIssueFts::upsert($oeuvreId);

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Impossible de mettre à jour le numéro.';
        }
    }

    /**
     * Crée un numéro catalogue + entrée bibliothèque.
     *
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createIssueWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $numero = trim((string) ($data['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = !empty($data['est_hors_serie']);
        $numeroError = $this->validateNumeroForSeries($seriesId, $numero, $horsSerie);
        if ($numeroError !== null) {
            return $numeroError;
        }

        $numeroOrdre = (float) ($data['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = is_numeric($numero) ? (float) $numero : $this->maxNumeroOrdreForSeries($seriesId) + 1;
        }

        if ($horsSerie && $numeroOrdre === (float) (int) $numeroOrdre) {
            $numeroOrdre += 0.5;
        }

        $titre = self::buildCatalogIssueTitle(trim((string) ($series['titre'] ?? '')), $numero, $horsSerie);
        $dateParution = trim((string) ($data['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? 0));
        $hasPaper = !empty($data['support_papier']);
        $hasPdf = isset($data['stored_object_id']) && (int) $data['stored_object_id'] > 0;
        $support = MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf);

        $statut = LibraryStatut::normalize($statut);

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'synopsis' => '',
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::MAGAZINE,
            ]);

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie, stored_object_id
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId,
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                isset($data['stored_object_id']) ? (int) $data['stored_object_id'] : null,
            ]);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);

            $seriesResult = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($seriesResult !== true) {
                throw new \RuntimeException((string) $seriesResult);
            }

            $this->db->commit();

            MagazineIssueFts::upsert($oeuvreId);

            if (MagazineSupport::isPossessed([
                'support_physique' => $support,
                'stored_object_id' => (int) ($data['stored_object_id'] ?? 0),
            ])) {
                $this->clearWishlistEntriesWhenPossessed($oeuvreId);
            }

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineRepository::createIssueWithLibrary: ' . $e->getMessage());

            return 'Impossible d’enregistrer le numéro.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateIssue(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        $numero = trim((string) ($data['numero'] ?? $issue['numero'] ?? ''));
        if ($numero === '') {
            return 'Le numéro est obligatoire.';
        }

        $horsSerie = $this->horsSerieFromData($data, $issue);
        $wasHorsSerie = !empty($issue['est_hors_serie']);

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        $seriesTitre = trim((string) ($series['titre'] ?? $issue['series_titre'] ?? ''));
        $titre = self::buildCatalogIssueTitle($seriesTitre, $numero, $horsSerie);

        $numeroOrdre = (float) ($data['numero_ordre'] ?? $issue['numero_ordre'] ?? 0);
        $numeroError = $this->validateNumeroForSeries($seriesId, $numero, $horsSerie, $oeuvreId);
        if ($numeroError !== null) {
            if ($wasHorsSerie && !$horsSerie) {
                return 'Impossible de retirer le hors-série : un numéro classique « '
                    . $numero . ' » existe déjà pour cette revue. '
                    . 'Fusionnez les doublons depuis Maintenance catalogue → Doublons magazines.';
            }

            return $numeroError;
        }

        $numeroOrdre = $this->adjustNumeroOrdreForHorsSerie($numeroOrdre, $horsSerie);

        $dateParution = trim((string) ($data['date_parution'] ?? $issue['date_parution'] ?? ''));
        $sommaire = trim((string) ($data['sommaire'] ?? $issue['sommaire'] ?? ''));
        $pages = max(0, (int) ($data['pages'] ?? $issue['pages'] ?? 0));
        $posterUrl = trim((string) ($data['poster_url'] ?? $issue['poster_url'] ?? ''));

        $titleError = $this->validateCatalogIssueTitleUnique($titre, $oeuvreId);
        if ($titleError !== null) {
            return $titleError;
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, [
                'titre' => $titre,
                'poster_url' => $posterUrl,
            ], ['titre', 'poster_url']);

            $storedObjectId = null;
            if (array_key_exists('stored_object_id', $data)) {
                $storedObjectId = $data['stored_object_id'] !== null ? (int) $data['stored_object_id'] : null;
            } elseif ((int) ($issue['stored_object_id'] ?? 0) > 0) {
                $storedObjectId = (int) $issue['stored_object_id'];
            }

            $this->db->prepare(
                'UPDATE oeuvre_magazine SET
                    numero = ?, numero_ordre = ?, date_parution = ?, sommaire = ?,
                    pages = ?, est_hors_serie = ?, stored_object_id = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $numero,
                $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire,
                $pages,
                $horsSerie ? 1 : 0,
                $storedObjectId,
                $oeuvreId,
            ]);

            if (array_key_exists('support_papier', $data) || array_key_exists('support_physique', $data)) {
                $hasPaper = array_key_exists('support_papier', $data)
                    ? !empty($data['support_papier'])
                    : MagazineSupport::hasPaper((string) ($issue['support_physique'] ?? ''));
                $effectiveStoredId = $storedObjectId !== null
                    ? (int) $storedObjectId
                    : (int) ($issue['stored_object_id'] ?? 0);
                $hasPdf = $effectiveStoredId > 0;
                $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
                    ->execute([MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf), $bibId]);
            }

            $this->db->commit();

            MagazineIssueFts::upsert($oeuvreId);

            $this->clearWishlistEntriesWhenPossessed($oeuvreId);

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Mise à jour impossible.';
        }
    }

    public function deleteFromLibrary(int $bibId, int $userId, int $foyerId): bool|string
    {
        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        $stmt = $this->db->prepare('DELETE FROM bibliotheque WHERE id = ?');
        $stmt->execute([$bibId]);

        return $stmt->rowCount() > 0 ? true : 'Suppression impossible.';
    }

    /**
     * Retire une série de la bibliothèque (collection du foyer ou envies personnelles).
     * Ne supprime pas les fiches catalogue partagées.
     *
     * @return array{removed_issues: int}|string
     */
    public function removeSeriesFromLibrary(int $seriesId, string $statut, int $userId, int $foyerId): array|string
    {
        if (!self::isAvailable() || $seriesId <= 0) {
            return 'Module magazines non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        if (!$this->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId)) {
            return $statut === LibraryStatut::WISHLIST
                ? 'Cette série n’est pas dans vos envies.'
                : 'Cette série n’est pas dans vos magazines.';
        }

        $this->db->beginTransaction();
        try {
            if ($statut === LibraryStatut::COLLECTION) {
                $deleteIssues = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND foyer_id = :foyer_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = :series_id
                       )'
                );
                $deleteIssues->execute([
                    'statut' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND foyer_id = ?'
                )->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
            } else {
                $deleteIssues = $this->db->prepare(
                    'DELETE FROM bibliotheque
                     WHERE statut = :statut
                       AND user_id = :user_id
                       AND oeuvre_id IN (
                           SELECT oeuvre_id FROM oeuvre_magazine WHERE series_id = :series_id
                       )'
                );
                $deleteIssues->execute([
                    'statut' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                    'series_id' => $seriesId,
                ]);

                $this->db->prepare(
                    'DELETE FROM series_bibliotheque
                     WHERE series_id = ? AND statut = ? AND user_id = ?'
                )->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
            }

            $removedIssues = $deleteIssues->rowCount();
            $this->db->commit();

            return ['removed_issues' => $removedIssues];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('MagazineRepository::removeSeriesFromLibrary: ' . $e->getMessage());

            return 'Impossible de retirer la série de votre bibliothèque.';
        }
    }

    public function isSeriesInLibrary(int $seriesId, string $statut, int $userId, int $foyerId): bool
    {
        if (!self::seriesLibraryTableExists() || $seriesId <= 0) {
            return false;
        }

        $statut = LibraryStatut::normalize($statut);
        if ($statut === LibraryStatut::COLLECTION) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND foyer_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
        }

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Ajoute un numéro non possédé aux envies sans le retirer de la collection.
     *
     * @return true|string
     */
    public function addIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        $issue = $this->findIssueByBibId($bibId, $userId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        if (($issue['statut'] ?? '') !== LibraryStatut::COLLECTION) {
            return 'Action réservée aux numéros de votre collection.';
        }

        if (MagazineSupport::isPossessed($issue)) {
            return 'Ce numéro est déjà possédé (papier ou PDF).';
        }

        $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
        $seriesId = (int) ($issue['series_id'] ?? 0);
        if ($oeuvreId <= 0 || $seriesId <= 0) {
            return 'Numéro invalide.';
        }

        $bibRepo = new BibliothequeRepository();
        $existingWishlist = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId, LibraryStatut::WISHLIST);
        if ($existingWishlist !== null) {
            return true;
        }

        try {
            $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => LibraryStatut::WISHLIST,
                'support_physique' => '',
            ]);
        } catch (\Throwable $e) {
            error_log('MagazineRepository::addIssueToWishlist: ' . $e->getMessage());

            return 'Impossible d’ajouter aux envies.';
        }

        $this->registerSeriesInLibrary($seriesId, LibraryStatut::WISHLIST, $userId, $foyerId);

        return true;
    }

    /** @deprecated Utiliser addIssueToWishlist() */
    public function moveIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        return $this->addIssueToWishlist($bibId, $userId, $foyerId);
    }

    /** L’utilisateur peut lire un PDF rattaché à un numéro de sa bibliothèque. */
    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $foyerId): bool
    {
        if (!self::isAvailable() || $storedObjectId <= 0 || $userId <= 0) {
            return false;
        }

        $params = [
            'stored_object_id' => $storedObjectId,
            'user_id' => $userId,
            'foyer_id' => $foyerId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
        ];

        $sql = 'SELECT 1
             FROM oeuvre_magazine om
             INNER JOIN bibliotheque b ON b.oeuvre_id = om.oeuvre_id
             WHERE om.stored_object_id = :stored_object_id
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )
             LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return (bool) $stmt->fetchColumn();
    }

    /** Chemin relatif type d’un PDF magazine (sous MONCINE_MEDIA_PATH). */
    public static function pdfStorageHint(): string
    {
        return MediaStorage::rootPath() . '/magazines/{revue}/{annee}/{revue}-{numero}.pdf';
    }

    /**
     * Chemin relatif d’un PDF magazine : revue / année / revue-numero.pdf
     *
     * @return string|false
     */
    public static function buildMagazinePdfRelativePath(string $seriesTitle, string $numero, string $dateParution): string|false
    {
        $seriesSlug = self::slugifyForPath($seriesTitle, 'revue');
        $numeroSlug = self::slugifyForPath($numero, 'numero');
        $year = self::extractParutionYear($dateParution);
        $fileName = $seriesSlug . '-' . $numeroSlug . '.pdf';

        return MediaStorage::relativePath('magazine', $seriesSlug, $year, $fileName);
    }

    /**
     * Enregistre un PDF pour un numéro (stored_objects + oeuvre_magazine).
     *
     * @return true|string
     */
    public function attachPdf(int $oeuvreId, string $tmpPath, string $originalName, int $fileSize): bool|string
    {
        if ($oeuvreId <= 0 || !is_readable($tmpPath)) {
            return 'Fichier PDF invalide.';
        }

        $maxBytes = UploadLimits::maxPdfBytes();
        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            return UploadLimits::pdfTooLargeApplicationMessage();
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if (!$this->isPdfMime($mime, $tmpPath)) {
            return 'Le fichier doit être un PDF.';
        }

        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $meta = $this->findMagazinePdfMeta($oeuvreId);
        if ($meta === null) {
            return 'Numéro magazine introuvable.';
        }

        // Remplacement : supprime l’ancien PDF rattaché à ce numéro.
        $this->removeStoredPdfForOeuvre($oeuvreId);

        $relative = self::buildMagazinePdfRelativePath(
            (string) ($meta['series_titre'] ?? ''),
            (string) ($meta['numero'] ?? ''),
            (string) ($meta['date_parution'] ?? '')
        );
        if ($relative === false) {
            return 'Chemin de stockage invalide.';
        }

        $absolute = MediaStorage::absolutePath($relative);
        if ($absolute === '') {
            return 'Chemin de stockage invalide.';
        }

        $this->purgeStoredObjectAtRelativePath($relative);

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de créer le dossier médias.';
        }

        if (!@move_uploaded_file($tmpPath, $absolute)) {
            if (!@rename($tmpPath, $absolute) && !@copy($tmpPath, $absolute)) {
                return 'Impossible d’enregistrer le PDF (vérifiez les droits d’écriture sur '
                    . dirname($absolute) . ').';
            }
        }

        @chmod($absolute, 0640);

        $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        if ($stored === null) {
            $this->purgeStoredObjectAtRelativePath($relative);
            $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        }
        if ($stored === null) {
            @unlink($absolute);

            return 'Enregistrement du PDF en base impossible (chemin déjà utilisé ?).';
        }

        $this->db->prepare('UPDATE oeuvre_magazine SET stored_object_id = ? WHERE oeuvre_id = ?')
            ->execute([(int) $stored['id'], $oeuvreId]);

        $this->syncSupportTagsForOeuvre($oeuvreId);

        $this->schedulePdfPostProcessing($oeuvreId, $absolute);

        return true;
    }

    /**
     * Met à jour les tags support (papier / pdf) selon la case papier et la présence d’un PDF.
     */
    public function syncSupportTagsForOeuvre(int $oeuvreId, ?bool $hasPaper = null): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT b.id, b.support_physique, om.stored_object_id
             FROM bibliotheque b
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = b.oeuvre_id
             WHERE b.oeuvre_id = ? AND b.statut = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, LibraryStatut::COLLECTION]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }

        if ($hasPaper === null) {
            $hasPaper = MagazineSupport::hasPaper((string) ($row['support_physique'] ?? ''));
        }

        $hasPdf = (int) ($row['stored_object_id'] ?? 0) > 0;
        $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
            ->execute([
                MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf),
                (int) ($row['id'] ?? 0),
            ]);

        $this->clearWishlistEntriesWhenPossessed($oeuvreId);
    }

    /**
     * Indexation texte + couverture après enregistrement (évite de bloquer la réponse HTTP).
     */
    private function schedulePdfPostProcessing(int $oeuvreId, string $absolutePdfPath): void
    {
        register_shutdown_function(static function () use ($oeuvreId, $absolutePdfPath): void {
            if (!is_readable($absolutePdfPath)) {
                return;
            }

            @set_time_limit(300);

            try {
                $repo = new MagazineRepository();
                $repo->indexPdfTextPreviewFromFile($oeuvreId, $absolutePdfPath);
                $repo->applyCoverFromPdfIfMissing($oeuvreId, $absolutePdfPath);
                $repo->applyPageCountFromPdf($oeuvreId, $absolutePdfPath);
            } catch (\Throwable $e) {
                error_log('MagazineRepository::schedulePdfPostProcessing: ' . $e->getMessage());
            }
        });
    }

    /**
     * Utilise la page 1 du PDF comme couverture si le numéro n’en a pas encore.
     */
    public function applyCoverFromPdfIfMissing(int $oeuvreId, string $absolutePdfPath): void
    {
        if ($oeuvreId <= 0 || !is_readable($absolutePdfPath) || !MagazinePdfCoverExtractor::isAvailable()) {
            return;
        }

        $stmt = $this->db->prepare('SELECT poster_url FROM oeuvres WHERE id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        $posterUrl = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($posterUrl !== '') {
            return;
        }

        $binary = MagazinePdfCoverExtractor::renderFirstPageJpeg($absolutePdfPath);
        if ($binary === '') {
            return;
        }

        $webPath = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
        if ($webPath === '') {
            return;
        }

        $this->db->prepare('UPDATE oeuvres SET poster_url = ? WHERE id = ?')
            ->execute([$webPath, $oeuvreId]);
    }

    /**
     * Met à jour le champ « pages » depuis le PDF si la valeur en base est 0 (ou si $force).
     */
    public function applyPageCountFromPdf(int $oeuvreId, string $absolutePdfPath, bool $force = false): void
    {
        if ($oeuvreId <= 0 || !is_readable($absolutePdfPath) || !MagazinePdfInfo::isAvailable()) {
            return;
        }

        $pageCount = MagazinePdfInfo::readPageCount($absolutePdfPath);
        if ($pageCount <= 0) {
            return;
        }

        if (!$force) {
            $stmt = $this->db->prepare('SELECT pages FROM oeuvre_magazine WHERE oeuvre_id = ? LIMIT 1');
            $stmt->execute([$oeuvreId]);
            if ((int) ($stmt->fetchColumn() ?: 0) > 0) {
                return;
            }
        }

        $this->db->prepare('UPDATE oeuvre_magazine SET pages = ? WHERE oeuvre_id = ?')
            ->execute([$pageCount, $oeuvreId]);
    }

    /**
     * Extrait et enregistre le texte des 6 premières pages d’un PDF déjà stocké.
     */
    public function indexPdfTextPreviewFromFile(int $oeuvreId, string $absolutePdfPath): void
    {
        if ($oeuvreId <= 0 || !self::pdfTextPreviewColumnExists()) {
            return;
        }

        $text = MagazinePdfTextExtractor::extractFirstPages($absolutePdfPath);
        $this->db->prepare('UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE oeuvre_id = ?')
            ->execute([$text, $oeuvreId]);
        // Trigger SQL met à jour magazine_issue_fts ; secours si index désynchronisé.
        MagazineIssueFts::upsert($oeuvreId);
    }

    /**
     * Ré-indexe le texte des PDF d’une série (numéros ayant un fichier stocké).
     *
     * @return array{indexed: int, skipped: int, errors: int}
     */
    public function reindexPdfTextPreviewsForSeries(int $seriesId, int $userId, int $foyerId, ?string $statut = null): array
    {
        $result = ['indexed' => 0, 'skipped' => 0, 'errors' => 0];
        if (!self::pdfTextPreviewColumnExists()) {
            return $result;
        }

        $canIndexText = MagazinePdfTextExtractor::isAvailable();
        $canReadMeta = MagazinePdfInfo::isAvailable() || MagazinePdfCoverExtractor::isAvailable();
        if (!$canIndexText && !$canReadMeta) {
            return $result;
        }

        $issues = $this->listIssuesForSeries($seriesId, $userId, $foyerId, $statut);
        $storage = new LocalFilesystemObjectStorage();
        $storedRepo = new StoredObjectRepository();

        foreach ($issues as $issue) {
            $storedObjectId = (int) ($issue['stored_object_id'] ?? 0);
            if ($storedObjectId <= 0) {
                $result['skipped']++;

                continue;
            }

            $row = $storedRepo->findById($storedObjectId);
            if ($row === null) {
                $result['errors']++;

                continue;
            }

            $relative = (string) ($row['relative_path'] ?? '');
            $absolute = MediaStorage::absolutePath($relative);
            if ($absolute === '' || !$storage->exists($relative)) {
                $result['errors']++;

                continue;
            }

            try {
                $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
                if ($canIndexText) {
                    $this->indexPdfTextPreviewFromFile($oeuvreId, $absolute);
                }
                $this->applyCoverFromPdfIfMissing($oeuvreId, $absolute);
                $this->applyPageCountFromPdf($oeuvreId, $absolute);
                $result['indexed']++;
            } catch (\Throwable $e) {
                error_log('reindexPdfTextPreviewsForSeries: ' . $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
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

    /** @param array<string, int|string> $params */
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

    /**
     * SQLite PDO refuse les paramètres nommés absents de la requête.
     *
     * @param array<string, int|string> $params
     * @return array<string, int|string>
     */
    private function filterParamsForSql(string $sql, array $params): array
    {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }

        $filtered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }

        return $filtered;
    }

    private function seriesOrderClause(string $sortBy, string $sortDir): string
    {
        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'issues' => 'issue_count ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'last_date' => 'last_date_parution ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            default => 's.titre COLLATE FRENCH_NOCASE ' . $dir,
        };
    }

    private function issueOrderClause(string $sortBy, string $sortDir): string
    {
        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return match ($sortBy) {
            'numero' => 'om.numero_ordre ' . $dir . ', om.date_parution ' . $dir,
            'date' => 'om.date_parution ' . $dir . ', om.numero_ordre ' . $dir,
            'titre' => 'o.titre COLLATE FRENCH_NOCASE ' . $dir,
            default => 'om.numero_ordre ' . $dir . ', om.date_parution ' . $dir,
        };
    }

    /**
     * Recherche globale dans les numéros d’une série (n°, date, sommaire, texte PDF pages 1–6).
     * Utilise FTS5 si disponible, sinon LIKE.
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function issueGlobalSearchFilterSql(string $searchQuery): array
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return ['', []];
        }

        $orParts = [];
        $params = [];

        $parsed = PublicationType::parseParutionDateFilter($searchQuery);
        if ($parsed !== null) {
            $orParts[] = "CAST(strftime('%Y', om.date_parution) AS INTEGER) = :search_g_year";
            $params['search_g_year'] = $parsed['year'];
            if ($parsed['month'] !== null) {
                $orParts[] = "CAST(strftime('%m', om.date_parution) AS INTEGER) = :search_g_month";
                $params['search_g_month'] = $parsed['month'];
            }
        }

        $ftsMatch = MagazineIssueFts::isAvailable()
            ? MagazineIssueFts::matchExpression($searchQuery)
            : '';
        if ($ftsMatch !== '') {
            $orParts[] = 'om.oeuvre_id IN (
                SELECT magazine_issue_fts.oeuvre_id
                FROM magazine_issue_fts
                WHERE magazine_issue_fts.series_id = om.series_id
                  AND magazine_issue_fts MATCH :search_fts
            )';
            $params['search_fts'] = $ftsMatch;
        } elseif ($parsed === null) {
            $fragment = LikePattern::containsFragment($searchQuery);
            $likeParts = [
                'LOWER(om.numero) LIKE LOWER(:search_g_numero) ESCAPE \'\\\'',
                'LOWER(COALESCE(om.sommaire, \'\')) LIKE LOWER(:search_g_sommaire) ESCAPE \'\\\'',
                'LOWER(COALESCE(om.date_parution, \'\')) LIKE LOWER(:search_g_date_raw) ESCAPE \'\\\'',
            ];
            $params['search_g_numero'] = $fragment;
            $params['search_g_sommaire'] = $fragment;
            $params['search_g_date_raw'] = $fragment;

            if (self::pdfTextPreviewColumnExists()) {
                $likeParts[] = 'LOWER(COALESCE(om.pdf_text_preview, \'\')) LIKE LOWER(:search_g_pdf) ESCAPE \'\\\'';
                $params['search_g_pdf'] = $fragment;
            }

            $orParts[] = '(' . implode(' OR ', $likeParts) . ')';
        }

        if (MagazineSubjectRepository::isAvailable()) {
            [$subjectSql, $subjectParams] = $this->subjectGlobalSearchMatchSql($searchQuery);
            if ($subjectSql !== '') {
                [$issueSubjectSql, $issueSubjectParams] = $this->subjectSearchSqlForAlias(
                    $subjectSql,
                    $subjectParams,
                    'ms_issue',
                    'issue'
                );
                $orParts[] = 'om.oeuvre_id IN (
                    SELECT oms_issue.oeuvre_id
                    FROM oeuvre_magazine_subject oms_issue
                    INNER JOIN magazine_subject ms_issue ON ms_issue.id = oms_issue.subject_id
                    WHERE ' . $issueSubjectSql . '
                )';
                $params = array_merge($params, $issueSubjectParams);
            }
        }

        if ($orParts === []) {
            return ['', []];
        }

        return ['(' . implode(' OR ', $orParts) . ')', $params];
    }

    /**
     * Filtre séries : titre, contenu des numéros ou sujets associés (bibliothèque).
     *
     * @param array<string, int|string> $params
     */
    private function seriesGlobalSearchFilterSql(
        string $searchQuery,
        int $userId,
        int $foyerId,
        ?string $statut,
        array &$params
    ): string {
        $searchParts = ['LOWER(s.titre) LIKE LOWER(:series_q) ESCAPE \'\\\''];
        $params['series_q'] = LikePattern::containsFragment($searchQuery);

        [$issueSearchSql, $issueSearchParams] = $this->issueGlobalSearchFilterSql($searchQuery);
        if ($issueSearchSql !== '') {
            [$librarySql, $libraryParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
            $librarySqlInSub = str_replace('b.', 'b_gs.', $librarySql);
            $params = array_merge($params, $libraryParams, $issueSearchParams);
            $params['domain_gs'] = MediaDomain::MAGAZINE;
            $searchParts[] = 's.id IN (
                SELECT DISTINCT om_gs.series_id
                FROM oeuvre_magazine om_gs
                INNER JOIN oeuvres o_gs ON o_gs.id = om_gs.oeuvre_id AND o_gs.media_domain = :domain_gs
                INNER JOIN bibliotheque b_gs ON b_gs.oeuvre_id = o_gs.id
                WHERE ' . $librarySqlInSub . ' AND ' . str_replace('om.', 'om_gs.', $issueSearchSql) . '
            )';
        }

        if (MagazineSubjectRepository::isAvailable()) {
            [$subjectSql, $subjectParams] = $this->subjectGlobalSearchMatchSql($searchQuery);
            if ($subjectSql !== '') {
                [$librarySql, $libraryParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
                $librarySqlInSub = str_replace('b.', 'b_sub.', $librarySql);
                $params = array_merge($params, $libraryParams, $subjectParams);
                $params['domain_sub'] = MediaDomain::MAGAZINE;
                $searchParts[] = 's.id IN (
                    SELECT DISTINCT om_sub.series_id
                    FROM oeuvre_magazine om_sub
                    INNER JOIN oeuvres o_sub ON o_sub.id = om_sub.oeuvre_id AND o_sub.media_domain = :domain_sub
                    INNER JOIN bibliotheque b_sub ON b_sub.oeuvre_id = o_sub.id
                    INNER JOIN oeuvre_magazine_subject oms ON oms.oeuvre_id = om_sub.oeuvre_id
                    INNER JOIN magazine_subject ms ON ms.id = oms.subject_id
                    WHERE ' . $librarySqlInSub . ' AND ' . $subjectSql . '
                )';
            }
        }

        return '(' . implode(' OR ', $searchParts) . ')';
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function subjectGlobalSearchMatchSql(string $searchQuery): array
    {
        $searchQuery = trim($searchQuery);
        if ($searchQuery === '') {
            return ['', []];
        }

        $matchParts = [];
        $params = [];

        $ftsMatch = MagazineSubjectFts::isAvailable()
            ? MagazineSubjectFts::matchExpression($searchQuery)
            : '';
        if ($ftsMatch !== '') {
            $matchParts[] = 'ms.id IN (
                    SELECT magazine_subject_fts.subject_id
                    FROM magazine_subject_fts
                    WHERE magazine_subject_fts MATCH :series_subj_fts
                )';
            $params['series_subj_fts'] = $ftsMatch;
        } else {
            $matchParts[] = '(LOWER(ms.label) LIKE LOWER(:series_subj_q) ESCAPE \'\\\'
                OR LOWER(ms.detail) LIKE LOWER(:series_subj_q_detail) ESCAPE \'\\\')';
            $params['series_subj_q'] = LikePattern::containsFragment($searchQuery);
            $params['series_subj_q_detail'] = LikePattern::containsFragment($searchQuery);
        }

        if (MagazineGameLink::isAvailable()) {
            $matchParts[] = '(ms.catalog_oeuvre_id IS NOT NULL AND EXISTS (
                SELECT 1
                FROM oeuvres o_subj_game
                INNER JOIN oeuvre_jeu oj_subj_game ON oj_subj_game.oeuvre_id = o_subj_game.id
                WHERE o_subj_game.id = ms.catalog_oeuvre_id
                  AND o_subj_game.media_domain = :subj_game_domain
                  AND (
                      fold_search(o_subj_game.titre) LIKE :subj_game_title ESCAPE \'\\\'
                      OR fold_search(COALESCE(oj_subj_game.alternative_names, \'\')) LIKE :subj_game_acronym ESCAPE \'\\\'
                  )
            ))';
            $gamePattern = SearchMatch::foldedContainsPattern($searchQuery);
            $params['subj_game_domain'] = MediaDomain::JEU;
            $params['subj_game_title'] = $gamePattern;
            $params['subj_game_acronym'] = $gamePattern;
        }

        return ['(' . implode(' OR ', $matchParts) . ')', $params];
    }

    /**
     * Remplace l’alias ms et préfixe les paramètres nommés pour une sous-requête.
     *
     * @param array<string, int|string> $params
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function subjectSearchSqlForAlias(
        string $subjectSql,
        array $params,
        string $tableAlias,
        string $paramSuffix
    ): array {
        $sql = str_replace('ms.', $tableAlias . '.', $subjectSql);
        $outParams = [];
        foreach ($params as $key => $value) {
            $newKey = $key . '_' . $paramSuffix;
            $sql = str_replace(':' . $key, ':' . $newKey, $sql);
            $outParams[$newKey] = $value;
        }

        return [$sql, $outParams];
    }

    private function isPdfMime(mixed $mime, string $path): bool
    {
        if ($mime === 'application/pdf' || $mime === 'application/x-pdf') {
            return true;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = (string) fread($handle, 5);
        fclose($handle);

        return str_starts_with($header, '%PDF-');
    }

    /** @return array{series_titre: string, numero: string, date_parution: string}|null */
    private function findMagazinePdfMeta(int $oeuvreId): ?array
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT s.titre AS series_titre, om.numero, om.date_parution
             FROM oeuvre_magazine om
             INNER JOIN series s ON s.id = om.series_id
             WHERE om.oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** Transforme un libellé en segment de chemin sûr (ex. « PC Jeux » → pc-jeux). */
    private static function slugifyForPath(string $text, string $fallback): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if ($text === '') {
            return $fallback;
        }

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($ascii !== false) {
                $text = strtolower($ascii);
            }
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }

    /** Année de parution (AAAA) ou « inconnu » si la date manque. */
    private static function extractParutionYear(string $dateParution): string
    {
        $dateParution = trim($dateParution);
        if ($dateParution === '') {
            return 'inconnu';
        }

        if (preg_match('/^(19|20)\d{2}/', $dateParution, $matches) === 1) {
            return $matches[0];
        }

        $timestamp = strtotime($dateParution);

        return $timestamp !== false ? date('Y', $timestamp) : 'inconnu';
    }

    /** Supprime une entrée stored_objects (et fichier) à un chemin relatif, ex. avant remplacement. */
    private function purgeStoredObjectAtRelativePath(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return;
        }

        $storedRepo = new StoredObjectRepository();
        $existing = $storedRepo->findByRelativePath($relativePath);
        if ($existing === null) {
            return;
        }

        (new LocalFilesystemObjectStorage())->delete($relativePath);
        $storedRepo->deleteById((int) ($existing['id'] ?? 0));
    }

    private function removeStoredPdfForOeuvre(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('SELECT stored_object_id FROM oeuvre_magazine WHERE oeuvre_id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        $storedObjectId = (int) ($stmt->fetchColumn() ?: 0);
        if ($storedObjectId <= 0) {
            return;
        }

        $storedRepo = new StoredObjectRepository();
        $row = $storedRepo->findById($storedObjectId);
        if ($row !== null) {
            $relative = (string) ($row['relative_path'] ?? '');
            if ($relative !== '') {
                (new LocalFilesystemObjectStorage())->delete($relative);
            }
            $storedRepo->deleteById($storedObjectId);
        }

        $this->db->prepare(
            'UPDATE oeuvre_magazine SET stored_object_id = NULL WHERE oeuvre_id = ?'
        )->execute([$oeuvreId]);

        $this->syncSupportTagsForOeuvre($oeuvreId);

        if (self::pdfTextPreviewColumnExists()) {
            $this->db->prepare('UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE oeuvre_id = ?')
                ->execute(['', $oeuvreId]);
            MagazineIssueFts::upsert($oeuvreId);
        }
    }

    /**
     * Numéro possédé : au moins papier ou PDF (aligné sur MagazineSupport::isPossessed).
     */
    private function sqlIssuePossessedCondition(string $bAlias, string $omAlias): string
    {
        $support = "LOWER(COALESCE($bAlias.support_physique, ''))";

        return "(
            ($omAlias.stored_object_id IS NOT NULL AND $omAlias.stored_object_id > 0)
            OR (INSTR($support, 'papier') > 0)
            OR (INSTR($support, 'pdf') > 0)
            OR (INSTR($support, 'physique') > 0)
            OR (INSTR($support, 'demat') > 0)
            OR (INSTR($support, 'démat') > 0)
        )";
    }

    /** @param list<string> $where */
    private function appendPossessionFilterToWhere(array &$where, ?string $statut, string $possessionFilter): void
    {
        $possessionFilter = self::normalizePossessionFilter($possessionFilter);
        if ($possessionFilter === self::FILTER_HORS_SERIE) {
            $where[] = 'om.est_hors_serie = 1';

            return;
        }

        if ($statut !== LibraryStatut::COLLECTION) {
            return;
        }

        if ($possessionFilter === self::POSSESSION_OWNED) {
            $where[] = $this->sqlIssuePossessedCondition('b', 'om');
        } elseif ($possessionFilter === self::POSSESSION_UNOWNED) {
            $where[] = 'NOT ' . $this->sqlIssuePossessedCondition('b', 'om');
        }
    }

    /** Retire des envies personnelles un numéro désormais possédé en collection (papier ou PDF). */
    private function clearWishlistEntriesWhenPossessed(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT b.support_physique, om.stored_object_id
             FROM bibliotheque b
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = b.oeuvre_id
             WHERE b.oeuvre_id = ? AND b.statut = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, LibraryStatut::COLLECTION]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !MagazineSupport::isPossessed($row)) {
            return;
        }

        $this->db->prepare(
            'DELETE FROM bibliotheque WHERE oeuvre_id = ? AND statut = ?'
        )->execute([$oeuvreId, LibraryStatut::WISHLIST]);
    }
}
