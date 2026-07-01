<?php
/**
 * BD / Manga : catalogue (oeuvres + oeuvre_bd) et collection utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdRepository
{
    /** Colonnes triables sur les listes BD. */
    private const SORT_COLUMNS = [
        'titre' => 'o.titre COLLATE FRENCH_NOCASE',
        'annee' => 'o.annee',
        'series' => 's.titre COLLATE FRENCH_NOCASE',
        'tome' => 'ob.tome_numero',
        'scenariste' => 'ob.scenariste COLLATE FRENCH_NOCASE',
        'dessinateur' => 'ob.dessinateur COLLATE FRENCH_NOCASE',
        'editeur' => 'ob.editeur COLLATE FRENCH_NOCASE',
        'genre' => 'ob.genre COLLATE FRENCH_NOCASE',
        'kind' => 'ob.kind COLLATE NOCASE',
        'note' => 'note_max',
        'read_at' => 'derniere_lecture',
        'support' => 'b.support_physique COLLATE NOCASE',
        'added_at' => 'b.created_at',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return [
            'titre', 'annee', 'series', 'tome', 'scenariste', 'dessinateur',
            'editeur', 'genre', 'kind', 'note', 'read_at', 'support', 'added_at',
        ];
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return in_array($sortBy, self::sortableColumns(), true);
    }

    private static function sortOrderExpression(string $sortBy): string
    {
        return self::SORT_COLUMNS[$sortBy] ?? self::SORT_COLUMNS['titre'];
    }

    public static function isAvailable(): bool
    {
        return BdSchema::tableExists()
            && self::seriesLibraryTableExists()
            && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function seriesLibraryTableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series_bibliotheque' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** Ajoute une série BD à la collection ou aux envies (sans tome). */
    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        if (!self::seriesLibraryTableExists() || $seriesId <= 0) {
            return 'Module BD non disponible.';
        }

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        $this->db->prepare(
            'INSERT OR IGNORE INTO series_bibliotheque (series_id, user_id, foyer_id, statut)
             VALUES (?, ?, ?, ?)'
        )->execute([$seriesId, $userId, $foyerId, $statut]);

        return true;
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
                 WHERE series_id = ? AND statut = ? AND foyer_id = ? LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::COLLECTION, $foyerId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque
                 WHERE series_id = ? AND statut = ? AND user_id = ? LIMIT 1'
            );
            $stmt->execute([$seriesId, LibraryStatut::WISHLIST, $userId]);
        }

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Séries présentes dans la bibliothèque BD.
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

        $order = $this->seriesOrderClause($sortBy, $sortDir);
        $ownedOnly = $statut !== LibraryStatut::WISHLIST;
        $ownedSql = $ownedOnly ? ' AND ' . $this->sqlTomePossessedCondition('b') : '';
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
        $stmt->execute($this->filterParamsForSql($sql, $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['catalog_tome_count'] = (int) ($row['catalog_tome_count'] ?? 0);
            $row['possessed_tome_count'] = (int) ($row['possessed_tome_count'] ?? 0);
            $row['tome_count'] = $row['possessed_tome_count'];
            $row['kind'] = BdSeriesMetadata::kindFromSeries($row);
            $row['kind_label'] = BdKind::label($row['kind']);
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
        if (!self::isAvailable()) {
            return 0;
        }

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge(['domain_oeuvre' => MediaDomain::BD], $statutParams);

        $where = [$statutSql];
        if ($statut !== LibraryStatut::WISHLIST) {
            $where[] = $this->sqlTomePossessedCondition('b');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
             INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id
             WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return (int) $stmt->fetchColumn();
    }

    public function countCatalogTomesForSeries(int $seriesId): int
    {
        if (!self::isAvailable() || $seriesId <= 0) {
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
        if (!self::isAvailable() || $seriesId <= 0) {
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
            $where[] = $this->sqlTomePossessedCondition('b');
        }

        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id AND o.media_domain = :domain_oeuvre
                INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id
                WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

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

    public static function suggestNextTomeNumero(int $lastTome): int
    {
        return $lastTome > 0 ? $lastTome + 1 : 1;
    }

    /**
     * Tomes d’une série dans la bibliothèque.
     *
     * @return list<array<string, mixed>>
     */
    public function listTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'tome',
        string $sortDir = 'asc',
        string $searchQuery = ''
    ): array {
        if (!self::isAvailable() || $seriesId <= 0) {
            return [];
        }

        [$statutSql, $statutParams] = $this->libraryStatutFilter($statut, $userId, $foyerId);
        $params = array_merge([
            'series_id' => $seriesId,
            'domain' => MediaDomain::BD,
            'history_user_id' => $userId,
            'foyer_id_rating' => $foyerId,
        ], $statutParams);

        $where = [
            'ob.series_id = :series_id',
            'o.media_domain = :domain',
            $statutSql,
        ];

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = self::bdSearchSqlConditions($searchQuery, 'series_tome');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $order = match ($sortBy) {
            'titre' => 'o.titre COLLATE FRENCH_NOCASE ' . $direction,
            'annee' => 'o.annee ' . $direction . ', ob.tome_numero ASC',
            'read_at' => 'derniere_lecture IS NULL ASC, derniere_lecture ' . $direction,
            'note' => 'note_max IS NULL ASC, note_max ' . $direction,
            default => 'ob.tome_numero ' . $direction . ', o.titre COLLATE FRENCH_NOCASE ASC',
        };

        $sql = 'SELECT ' . self::selectBdRow() . self::selectBdHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' INNER JOIN series s ON s.id = ob.series_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return array_map([BdRowMapper::class, 'hydrateBdRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = ''
    ): int {
        return count($this->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            'tome',
            'asc',
            $searchQuery
        ));
    }

    /**
     * Recherche séries BD au catalogue partagé.
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

    /**
     * Rattache tous les tomes catalogue d’une série à la collection (non possédés).
     *
     * @return int nombre de tomes ajoutés
     */
    public function attachCatalogTomesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        if (!self::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT oeuvre_id FROM oeuvre_bd WHERE series_id = ? ORDER BY tome_numero ASC'
        );
        $stmt->execute([$seriesId]);

        $attached = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }
            $result = $this->addFromCatalogOeuvre($oeuvreId, LibraryStatut::COLLECTION, $userId, $foyerId);
            if (is_int($result)) {
                $attached++;
            }
        }

        return $attached;
    }

    public function findCatalogTomeBySeriesAndNumero(int $seriesId, int $tomeNumero): ?array
    {
        if ($seriesId <= 0 || $tomeNumero <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE ob.series_id = ? AND ob.tome_numero = ? AND o.media_domain = ? LIMIT 1'
        );
        $stmt->execute([$seriesId, $tomeNumero, MediaDomain::BD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateCatalogRow($row) : null;
    }

    public static function tableExists(): bool
    {
        return BdSchema::tableExists();
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
        if (!self::isAvailable()) {
            return [];
        }

        if (!self::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = self::sortOrderExpression($sortBy);
        $readAtSort = $sortBy === 'read_at';

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :bd_domain',
            $userWhere,
        ];
        $params['bd_domain'] = MediaDomain::BD;
        $params['history_user_id'] = $userId;
        $params['foyer_id_rating'] = $foyerId;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = self::bdSearchSqlConditions($searchQuery, 'q');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        ($filter ?? BdListFilter::empty())->applyToSql($where, $params);

        $sql = 'SELECT ' . self::selectBdRow() . self::selectBdHistoryExtras()
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
        if (!self::isAvailable()) {
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
            [$searchSql, $searchParams] = self::bdSearchSqlConditions($searchQuery, 'q_count');
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

    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!self::isAvailable() || $bibId <= 0) {
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
            'foyer_id_rating' => $foyerId,
        ];

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectBdRow() . self::selectBdHistoryExtras()
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

    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_bd ob ON ob.oeuvre_id = o.id'
            . ' LEFT JOIN series s ON s.id = ob.series_id'
            . ' WHERE o.id = ? AND o.media_domain = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::BD]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? BdRowMapper::hydrateCatalogRow($row) : null;
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $params = [
            'oeuvre_id' => $oeuvreId,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
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
        $params['bd_domain'] = MediaDomain::BD;
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Recherche dans le catalogue BD (autocomplétion).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $params = ['bd_domain' => MediaDomain::BD];
        $where = ['o.media_domain = :bd_domain'];

        $query = trim($query);
        if ($query !== '') {
            [$searchSql, $searchParams] = self::bdSearchSqlConditions($query, 'q_cat');
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT ' . self::selectCatalogRow()
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
        if (!self::isAvailable()) {
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

    /**
     * Ajoute un album depuis le catalogue partagé.
     *
     * @param array<string, mixed> $libraryDetails
     * @return int|string
     */
    public function addFromCatalogOeuvre(
        int $oeuvreId,
        string $statut,
        int $userId,
        int $foyerId,
        array $libraryDetails = []
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module BD non disponible.';
        }

        $catalog = $this->findCatalogByOeuvreId($oeuvreId);
        if ($catalog === null) {
            return 'Album introuvable dans le catalogue.';
        }

        $existing = $this->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
        if ($existing !== null && $existing > 0) {
            return 'Cet album est déjà dans votre bibliothèque.';
        }

        $support = BdPhysicalSupport::normalize((string) ($libraryDetails['support_physique'] ?? ''));
        $seriesId = (int) ($catalog['series_id'] ?? 0);

        try {
            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => LibraryStatut::normalize($statut),
                'support_physique' => $support,
            ]);
            if ($seriesId > 0) {
                $register = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
                if ($register !== true) {
                    return (string) $register;
                }
            }

            return $bibId;
        } catch (\Throwable $e) {
            return 'Erreur lors de l’ajout à la bibliothèque.';
        }
    }

    /**
     * Crée un tome catalogue + entrée bibliothèque (série obligatoire).
     *
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createTomeWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        $data['series_id'] = $seriesId;

        return $this->createWithLibrary($data, $statut, $userId, $foyerId);
    }

    /**
     * Crée une fiche catalogue + entrée bibliothèque.
     *
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module BD non disponible.';
        }

        $resolved = self::resolveTitleAndSeries($data);
        if (is_string($resolved)) {
            return $resolved;
        }
        [$titre, $seriesId] = $resolved;

        $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return 'Série introuvable.';
        }

        $statut = LibraryStatut::normalize($statut);
        $kind = trim((string) ($data['kind'] ?? '')) !== ''
            ? BdKind::normalize((string) $data['kind'])
            : BdSeriesMetadata::kindFromSeries($series);
        $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));

        $this->db->beginTransaction();
        try {
            $oeuvreId = (new OeuvreRepository())->insert([
                'titre' => $titre,
                'realisateur' => '',
                'annee' => max(0, (int) ($data['annee'] ?? 0)),
                'synopsis' => trim((string) ($data['synopsis'] ?? '')),
                'poster_url' => trim((string) ($data['poster_url'] ?? '')),
                'media_domain' => MediaDomain::BD,
            ]);

            $this->insertCatalogBdRow($oeuvreId, $data, $seriesId, $kind);

            $bibId = (new BibliothequeRepository())->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => $support,
            ]);

            $register = $this->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
            if ($register !== true) {
                throw new \RuntimeException((string) $register);
            }

            $this->db->commit();

            return $bibId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de l’enregistrement de l’album.';
        }
    }

    /**
     * Met à jour le catalogue d’un album (admin ou fiche utilisateur).
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalog(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        if (!self::isAvailable()) {
            return 'Module BD non disponible.';
        }

        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return 'Album introuvable.';
        }

        return $this->updateCatalogByOeuvreId((int) ($album['oeuvre_id'] ?? 0), $data, $bibId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data, ?int $bibId = null): bool|string
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return 'Module BD non disponible.';
        }

        $resolved = self::resolveTitleAndSeries($data);
        if (is_string($resolved)) {
            return $resolved;
        }
        [$titre, $seriesId] = $resolved;

        $kind = BdKind::normalize((string) ($data['kind'] ?? BdKind::BD));
        $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));

        $oeuvreUpdate = [
            'titre' => $titre,
            'annee' => max(0, (int) ($data['annee'] ?? 0)),
            'synopsis' => trim((string) ($data['synopsis'] ?? '')),
        ];
        $oeuvreFields = ['titre', 'annee', 'synopsis'];
        if (array_key_exists('poster_url', $data)) {
            $oeuvreUpdate['poster_url'] = trim((string) $data['poster_url']);
            $oeuvreFields[] = 'poster_url';
        }

        $this->db->beginTransaction();
        try {
            (new OeuvreRepository())->update($oeuvreId, $oeuvreUpdate, $oeuvreFields);

            $this->db->prepare(
                'UPDATE oeuvre_bd SET
                    series_id = ?,
                    kind = ?,
                    tome_numero = ?,
                    tome_label = ?,
                    scenariste = ?,
                    dessinateur = ?,
                    editeur = ?,
                    genre = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $seriesId > 0 ? $seriesId : null,
                $kind,
                max(0, (int) ($data['tome_numero'] ?? 0)),
                trim((string) ($data['tome_label'] ?? '')),
                trim((string) ($data['scenariste'] ?? '')),
                trim((string) ($data['dessinateur'] ?? '')),
                trim((string) ($data['editeur'] ?? '')),
                trim((string) ($data['genre'] ?? '')),
                $oeuvreId,
            ]);

            if ($bibId !== null && $bibId > 0) {
                $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
                    ->execute([$support, $bibId]);
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Erreur lors de la mise à jour de l’album.';
        }
    }

    /**
     * Met à jour un tome (catalogue + exemplaire bibliothèque).
     *
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateTome(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return 'Tome introuvable.';
        }

        $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
        $seriesId = (int) ($album['series_id'] ?? 0);
        if ($seriesId <= 0) {
            return 'Série introuvable pour ce tome.';
        }

        $data['series_id'] = $seriesId;
        $tomeNum = max(0, (int) ($data['tome_numero'] ?? $album['tome_numero'] ?? 0));
        if ($tomeNum > 0) {
            $duplicate = $this->findCatalogTomeBySeriesAndNumero($seriesId, $tomeNum);
            if ($duplicate !== null && (int) ($duplicate['oeuvre_id'] ?? 0) !== $oeuvreId) {
                return 'Un autre tome avec ce numéro existe déjà pour cette série.';
            }
        }

        if (array_key_exists('support_possede', $data)) {
            if (!empty($data['support_possede'])) {
                $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));
                if ($support === '') {
                    $support = BdPhysicalSupport::normalize((string) ($album['support_physique'] ?? ''));
                }
                if ($support === '') {
                    $support = BdPhysicalSupport::ALBUM;
                }
                $data['support_physique'] = $support;
            } else {
                $data['support_physique'] = '';
            }
        }

        return $this->updateCatalogByOeuvreId($oeuvreId, $data, $bibId);
    }

    /** Support physique depuis un formulaire (case « je possède »). */
    public static function supportFromPost(array $post): string
    {
        if (empty($post['support_possede'])) {
            return '';
        }

        $support = BdPhysicalSupport::normalize((string) ($post['support_physique'] ?? ''));

        return $support !== '' ? $support : BdPhysicalSupport::ALBUM;
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return false;
        }

        $posterUrl = trim($posterUrl);
        if ($posterUrl === '') {
            return false;
        }

        if ($this->findCatalogByOeuvreId($oeuvreId) === null) {
            return false;
        }

        (new OeuvreRepository())->update($oeuvreId, ['poster_url' => $posterUrl], ['poster_url']);

        return true;
    }

    /**
     * Enregistre la couverture : fichier upload prioritaire, sinon téléchargement URL → stockage local.
     */
    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        if ($oeuvreId <= 0 || !self::isAvailable()) {
            return;
        }

        $storage = new PosterStorage();

        if ($uploadedBinary !== null && $uploadedBinary !== '') {
            $local = $storage->importBinaryForOeuvre($oeuvreId, $uploadedBinary);
            if ($local !== '') {
                $this->updatePosterUrl($oeuvreId, $local);
            }

            return;
        }

        $posterUrlInput = trim($posterUrlInput);
        if ($posterUrlInput === '') {
            return;
        }

        $local = $storage->ensureLocalForOeuvre($oeuvreId, $posterUrlInput);
        if ($local !== '') {
            $this->updatePosterUrl($oeuvreId, $local);

            return;
        }

        $sanitized = SecureUrl::sanitizePosterUrl($posterUrlInput);
        if ($sanitized !== '') {
            $this->updatePosterUrl($oeuvreId, $sanitized);
        }
    }

    public function promoteToCollection(int $bibId, int $userId, int $foyerId): bool
    {
        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null || ($album['statut'] ?? '') !== LibraryStatut::WISHLIST) {
            return false;
        }

        return (new BibliothequeRepository())->promoteToCollection($bibId, $userId, $foyerId);
    }

    public function deleteById(int $bibId, int $userId, int $foyerId): bool
    {
        if ($this->findByBibId($bibId, $userId, $foyerId) === null) {
            return false;
        }

        $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$bibId]);

        return (new BibliothequeRepository())->deleteById($bibId, $userId, $foyerId);
    }

    /**
     * Détermine le titre et la série à partir des champs formulaire.
     *
     * @param array<string, mixed> $data
     * @return array{0: string, 1: int}|string
     */
    private static function resolveTitleAndSeries(array $data): array|string
    {
        $titre = trim((string) ($data['titre'] ?? ''));
        $seriesId = max(0, (int) ($data['series_id'] ?? 0));
        $tomeNum = max(0, (int) ($data['tome_numero'] ?? 0));
        $tomeLabel = trim((string) ($data['tome_label'] ?? ''));

        if ($seriesId <= 0) {
            return 'Choisissez d’abord une série, ou créez-en une nouvelle.';
        }

        if ($tomeNum <= 0 && $tomeLabel === '' && $titre === '') {
            return 'Indiquez au minimum un numéro de tome ou un titre.';
        }

        if ($titre === '') {
            $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
            $seriesTitre = trim((string) ($series['titre'] ?? ''));
            if ($seriesTitre === '') {
                return 'Série introuvable.';
            }
            $titre = BdRowMapper::seriesDisplayTitle([
                'series_titre' => $seriesTitre,
                'tome_numero' => $tomeNum,
                'tome_label' => $tomeLabel,
            ]);
        }

        return [$titre, $seriesId];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertCatalogBdRow(int $oeuvreId, array $data, int $seriesId, string $kind): void
    {
        $this->db->prepare(
            'INSERT INTO oeuvre_bd (
                oeuvre_id, series_id, kind, tome_numero, tome_label,
                scenariste, dessinateur, editeur, genre
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $oeuvreId,
            $seriesId > 0 ? $seriesId : null,
            $kind,
            max(0, (int) ($data['tome_numero'] ?? 0)),
            trim((string) ($data['tome_label'] ?? '')),
            trim((string) ($data['scenariste'] ?? '')),
            trim((string) ($data['dessinateur'] ?? '')),
            trim((string) ($data['editeur'] ?? '')),
            trim((string) ($data['genre'] ?? '')),
        ]);
    }

    private static function selectBdRow(): string
    {
        return 'b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.support_physique, b.created_at,'
            . ' o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' ob.series_id, ob.kind, ob.tome_numero, ob.tome_label,'
            . ' ob.scenariste, ob.dessinateur, ob.editeur, ob.genre,'
            . ' s.titre AS series_titre';
    }

    private static function selectBdHistoryExtras(): string
    {
        return ','
            . ' (SELECT MAX(h.date_vue) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id) AS derniere_lecture,'
            . ' (SELECT MAX(h.note) FROM historique h'
            . '  WHERE h.film_id = b.id AND h.user_id = :history_user_id'
            . '    AND h.note IS NOT NULL AND h.note >= 1) AS note_max,'
            . CatalogSchema::foyerAverageNoteSubquery('b.id', ':foyer_id_rating');
    }

    private static function selectCatalogRow(): string
    {
        return 'o.id AS oeuvre_id, o.titre, o.titre_original, o.annee, o.poster_url, o.synopsis,'
            . ' ob.series_id, ob.kind, ob.tome_numero, ob.tome_label,'
            . ' ob.scenariste, ob.dessinateur, ob.editeur, ob.genre,'
            . ' s.titre AS series_titre';
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private static function bdSearchSqlConditions(string $query, string $paramPrefix): array
    {
        $pattern = LikePattern::containsFragment($query);
        $params = [
            $paramPrefix => $pattern,
            $paramPrefix . '_series' => $pattern,
            $paramPrefix . '_scen' => $pattern,
            $paramPrefix . '_dess' => $pattern,
            $paramPrefix . '_edit' => $pattern,
            $paramPrefix . '_genre' => $pattern,
        ];

        $sql = '('
            . 'LOWER(o.titre) LIKE LOWER(:' . $paramPrefix . ') ESCAPE \'\\\''
            . ' OR LOWER(s.titre) LIKE LOWER(:' . $paramPrefix . '_series) ESCAPE \'\\\''
            . ' OR LOWER(ob.scenariste) LIKE LOWER(:' . $paramPrefix . '_scen) ESCAPE \'\\\''
            . ' OR LOWER(ob.dessinateur) LIKE LOWER(:' . $paramPrefix . '_dess) ESCAPE \'\\\''
            . ' OR LOWER(ob.editeur) LIKE LOWER(:' . $paramPrefix . '_edit) ESCAPE \'\\\''
            . ' OR LOWER(ob.genre) LIKE LOWER(:' . $paramPrefix . '_genre) ESCAPE \'\\\''
            . ')';

        return [$sql, $params];
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

    /**
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
            'tomes' => 'possessed_tome_count ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'kind' => 's.tags COLLATE NOCASE ' . $dir . ', s.titre COLLATE FRENCH_NOCASE ASC',
            'editeur' => 's.editeur COLLATE FRENCH_NOCASE ' . $dir,
            default => 's.titre COLLATE FRENCH_NOCASE ' . $dir,
        };
    }

    /** Tome possédé : support physique BD valide (album, relié, etc.). */
    private function sqlTomePossessedCondition(string $bAlias): string
    {
        $keys = array_map(
            static fn (string $key): string => "'" . str_replace("'", "''", $key) . "'",
            array_keys(BdPhysicalSupport::choices())
        );

        return 'LOWER(TRIM(' . $bAlias . '.support_physique)) IN (' . implode(', ', $keys) . ')';
    }
}
