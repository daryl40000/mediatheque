<?php
/**
 * Profil public d’un utilisateur (amis / membres du groupe) : stats et vignettes.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class UserPublicProfileService
{
    private PDO $db;

    private FriendshipRepository $friendships;

    private FamilyGroupService $groups;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->friendships = new FriendshipRepository();
        $this->groups = new FamilyGroupService();
    }

    /**
     * @return true|string
     */
    public function canView(int $viewerId, int $targetUserId): bool|string
    {
        if ($targetUserId <= 0) {
            return 'Utilisateur introuvable.';
        }
        if ($viewerId <= 0) {
            return 'Connexion requise.';
        }
        if ($viewerId === $targetUserId) {
            return true;
        }

        $target = (new UtilisateurRepository())->findById($targetUserId);
        if ($target === null || (int) ($target['actif'] ?? 0) !== 1) {
            return 'Utilisateur introuvable.';
        }

        if ($this->friendships->isBlockedBetween($viewerId, $targetUserId)) {
            return 'Profil non accessible.';
        }

        if ($this->friendships->areFriends($viewerId, $targetUserId)) {
            return true;
        }

        if ($this->groups->shareSameGroup($viewerId, $targetUserId)) {
            return true;
        }

        return 'Profil visible uniquement par vos amis ou les membres de votre groupe.';
    }

    /** @return array<string, mixed>|null */
    public function findPublicUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, nom, prenom, pseudo, ville, role, actif
             FROM utilisateurs WHERE id = ? AND actif = 1 LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function foyerIdForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $group = $this->groups->findGroupForUser($userId);
        if ($group !== null) {
            return (int) ($group['id'] ?? 0);
        }

        return (new FoyerRepository())->currentFoyerIdForUser($userId);
    }

    /**
     * @return array{
     *   media_domain: string,
     *   collection_count: int,
     *   wishlist_count: int,
     *   issue_count: int,
     *   films_vus_count: int,
     *   films_vus_year_count: int,
     *   year: int
     * }
     */
    public function getStats(int $userId, string $mediaDomain = MediaDomain::FILM): array
    {
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        $year = (int) date('Y');

        if (MediaDomain::isMagazine($mediaDomain)) {
            return $this->getMagazineStats($userId, $year);
        }

        return $this->getFilmStats($userId, $mediaDomain, $year);
    }

    /** @return list<array<string, mixed>> */
    public function lastViewedFilms(int $userId, int $limit = 5): array
    {
        if ($userId <= 0 || $limit <= 0) {
            return [];
        }
        $params = ['profile_user_id' => $userId];
        $domainSql = self::publicProfileMediaDomainSql($params, MediaDomain::FILM, 'o');
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                    MAX(h.date_vue) AS derniere_vue
             FROM historique h
             INNER JOIN bibliotheque b ON b.id = h.film_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE h.user_id = :profile_user_id' . $domainSql . '
             GROUP BY b.id
             ORDER BY derniere_vue DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function lastCollectionFilms(int $userId, int $limit = 5, string $mediaDomain = MediaDomain::FILM): array
    {
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        if (MediaDomain::isMagazine($mediaDomain)) {
            return $this->lastMagazineSeries($userId, LibraryStatut::COLLECTION, $limit);
        }

        $foyerId = $this->foyerIdForUser($userId);
        if ($foyerId <= 0 || $limit <= 0) {
            return [];
        }

        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::COLLECTION);
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . self::publicProfileMediaDomainSql($params, $mediaDomain) . '
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function lastNotedGames(int $userId, int $limit = 5): array
    {
        if ($userId <= 0 || $limit <= 0) {
            return [];
        }

        $params = ['profile_user_id' => $userId];
        $domainSql = self::publicProfileMediaDomainSql($params, MediaDomain::JEU, 'o');
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                    MAX(h.date_vue) AS derniere_note
             FROM historique h
             INNER JOIN bibliotheque b ON b.id = h.film_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE h.user_id = :profile_user_id
               AND h.note IS NOT NULL' . $domainSql . '
             GROUP BY b.id
             ORDER BY derniere_note DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function lastWishlistFilms(int $userId, int $limit = 5, string $mediaDomain = MediaDomain::FILM): array
    {
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        if (MediaDomain::isMagazine($mediaDomain)) {
            return $this->lastMagazineSeries($userId, LibraryStatut::WISHLIST, $limit);
        }

        if ($userId <= 0 || $limit <= 0) {
            return [];
        }
        $params = [
            'profile_user_id' => $userId,
            'profile_statut' => LibraryStatut::WISHLIST,
        ];
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE b.user_id = :profile_user_id AND b.statut = :profile_statut'
            . self::publicProfileMediaDomainSql($params, $mediaDomain) . '
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCollection(
        int $userId,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $mediaDomain = MediaDomain::FILM
    ): array {
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        if (MediaDomain::isMagazine($mediaDomain)) {
            return $this->listMagazineSeries($userId, LibraryStatut::COLLECTION, $sortBy, $sortDir);
        }

        $foyerId = $this->foyerIdForUser($userId);
        if ($foyerId <= 0) {
            return [];
        }

        return $this->listLibraryForUser(
            $foyerId,
            $userId,
            LibraryStatut::COLLECTION,
            $sortBy,
            $sortDir,
            $mediaDomain
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishlist(
        int $userId,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $mediaDomain = MediaDomain::FILM
    ): array {
        $mediaDomain = MediaDomain::normalize($mediaDomain);
        if (MediaDomain::isMagazine($mediaDomain)) {
            return $this->listMagazineSeries($userId, LibraryStatut::WISHLIST, $sortBy, $sortDir);
        }

        return $this->listLibraryForUser(0, $userId, LibraryStatut::WISHLIST, $sortBy, $sortDir, $mediaDomain);
    }

    /**
     * Historique des visions (une ligne par vision : date et note).
     *
     * @return list<array<string, mixed>>
     */
    public function listViewingHistory(
        int $userId,
        string $sortBy = 'date',
        string $sortDir = 'desc',
        ?int $yearFilter = null
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $sortColumns = [
            'date' => 'h.date_vue',
            'titre' => 'o.titre COLLATE FRENCH_NOCASE',
            'note' => 'h.note',
        ];
        if (!isset($sortColumns[$sortBy])) {
            $sortBy = 'date';
        }
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';

        $params = ['profile_user_id' => $userId];
        $where = 'h.user_id = :profile_user_id';

        if ($yearFilter !== null && $yearFilter > 0) {
            $where .= ' AND h.date_vue >= :year_start AND h.date_vue < :year_end';
            $params['year_start'] = $yearFilter . '-01-01';
            $params['year_end'] = ($yearFilter + 1) . '-01-01';
        }

        $domainSql = self::publicProfileMediaDomainSql($params, MediaDomain::FILM, 'o');

        $sql = 'SELECT h.id AS historique_id, h.date_vue, h.note,
                       ' . CatalogSchema::selectFilmRow() . '
                FROM historique h
                INNER JOIN bibliotheque b ON b.id = h.film_id
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                WHERE ' . $where . $domainSql . '
                ORDER BY ' . $sortColumns[$sortBy] . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }
        $sql .= ', h.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{
     *   media_domain: string,
     *   collection_count: int,
     *   wishlist_count: int,
     *   issue_count: int,
     *   films_vus_count: int,
     *   films_vus_year_count: int,
     *   year: int
     * }
     */
    private function getFilmStats(int $userId, string $mediaDomain, int $year): array
    {
        $foyerId = $this->foyerIdForUser($userId);

        $collectionCount = 0;
        if ($foyerId > 0) {
            $params = [
                'foyer_id' => $foyerId,
                'statut' => LibraryStatut::COLLECTION,
            ];
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE b.foyer_id = :foyer_id AND b.statut = :statut'
                . self::publicProfileMediaDomainSql($params, $mediaDomain)
            );
            $stmt->execute($params);
            $collectionCount = (int) $stmt->fetchColumn();
        }

        $wishParams = [
            'user_id' => $userId,
            'statut' => LibraryStatut::WISHLIST,
        ];
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.user_id = :user_id AND b.statut = :statut'
            . self::publicProfileMediaDomainSql($wishParams, $mediaDomain)
        );
        $stmt->execute($wishParams);
        $wishlistCount = (int) $stmt->fetchColumn();

        $filmsVus = MediaDomain::isGame($mediaDomain)
            ? 0
            : $this->countDistinctViewedFilms($userId);
        $filmsVusYear = MediaDomain::isGame($mediaDomain)
            ? 0
            : $this->countDistinctViewedFilms($userId, $year);
        $gamesNoted = MediaDomain::isGame($mediaDomain)
            ? $this->countDistinctNotedGames($userId)
            : 0;
        $gamesNotedYear = MediaDomain::isGame($mediaDomain)
            ? $this->countDistinctNotedGames($userId, $year)
            : 0;

        return [
            'media_domain' => $mediaDomain,
            'collection_count' => $collectionCount,
            'wishlist_count' => $wishlistCount,
            'issue_count' => 0,
            'films_vus_count' => $filmsVus,
            'films_vus_year_count' => $filmsVusYear,
            'games_noted_count' => $gamesNoted,
            'games_noted_year_count' => $gamesNotedYear,
            'year' => $year,
        ];
    }

    /**
     * @return array{
     *   media_domain: string,
     *   collection_count: int,
     *   wishlist_count: int,
     *   issue_count: int,
     *   films_vus_count: int,
     *   films_vus_year_count: int,
     *   year: int
     * }
     */
    private function getMagazineStats(int $userId, int $year): array
    {
        $foyerId = $this->foyerIdForUser($userId);
        $repo = new MagazineRepository();
        $available = MagazineRepository::isAvailable();

        $seriesCollection = $foyerId > 0 && $available
            ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0;
        $seriesWishlist = $available
            ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::WISHLIST)
            : 0;
        $issueCount = $foyerId > 0 && $available
            ? $repo->countIssuesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0;

        return [
            'media_domain' => MediaDomain::MAGAZINE,
            'collection_count' => $seriesCollection,
            'wishlist_count' => $seriesWishlist,
            'issue_count' => $issueCount,
            'films_vus_count' => 0,
            'films_vus_year_count' => 0,
            'year' => $year,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listMagazineSeries(
        int $userId,
        string $statut,
        string $sortBy,
        string $sortDir
    ): array {
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        $foyerId = $this->foyerIdForUser($userId);
        if ($statut === LibraryStatut::COLLECTION && $foyerId <= 0) {
            return [];
        }

        $magSort = match ($sortBy) {
            'issues' => 'issues',
            'last_date' => 'last_date',
            default => 'titre',
        };

        return (new MagazineRepository())->listSeriesInLibrary(
            $userId,
            $foyerId,
            $statut,
            $magSort,
            $sortDir
        );
    }

    /** @return list<array<string, mixed>> */
    private function lastMagazineSeries(int $userId, string $statut, int $limit): array
    {
        if ($userId <= 0 || $limit <= 0 || !MagazineRepository::seriesLibraryTableExists()) {
            return [];
        }

        $foyerId = $this->foyerIdForUser($userId);
        $params = [
            'domain_series' => MediaDomain::MAGAZINE,
            'domain_oeuvre' => MediaDomain::MAGAZINE,
            'limit' => $limit,
        ];

        if ($statut === LibraryStatut::COLLECTION) {
            if ($foyerId <= 0) {
                return [];
            }
            $scopeSql = 'sb.statut = :sb_statut AND sb.foyer_id = :sb_foyer_id';
            $params['sb_statut'] = LibraryStatut::COLLECTION;
            $params['sb_foyer_id'] = $foyerId;
        } else {
            $scopeSql = 'sb.statut = :sb_statut AND sb.user_id = :sb_user_id';
            $params['sb_statut'] = LibraryStatut::WISHLIST;
            $params['sb_user_id'] = $userId;
        }

        $sql = 'SELECT s.*,
                    MAX(CASE WHEN TRIM(o.poster_url) != \'\' THEN o.poster_url END) AS latest_poster_url,
                    COUNT(DISTINCT CASE WHEN b.statut = sb.statut THEN b.id END) AS issue_count
                FROM series s
                INNER JOIN series_bibliotheque sb ON sb.series_id = s.id
                LEFT JOIN oeuvre_magazine om ON om.series_id = s.id
                LEFT JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain_oeuvre
                LEFT JOIN bibliotheque b ON b.oeuvre_id = o.id
                WHERE s.media_domain = :domain_series AND ' . $scopeSql . '
                GROUP BY s.id
                ORDER BY sb.created_at DESC, s.id DESC
                LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return true|string
     */
    public function canViewMagazineSeries(int $viewerId, int $targetUserId, int $seriesId): bool|string
    {
        $access = $this->canView($viewerId, $targetUserId);
        if ($access !== true) {
            return $access;
        }
        if ($seriesId <= 0) {
            return 'Série introuvable.';
        }
        if (!MagazineRepository::isAvailable()) {
            return 'Module magazines non disponible.';
        }
        if ((new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) === null) {
            return 'Série introuvable.';
        }
        if (!$this->seriesVisibleOnProfile($targetUserId, $seriesId)) {
            return 'Cette série n’est pas partagée sur ce profil.';
        }

        return true;
    }

    /**
     * @return true|string
     */
    public function canViewMagazineIssue(int $viewerId, int $targetUserId, int $bibId): bool|string
    {
        $access = $this->canView($viewerId, $targetUserId);
        if ($access !== true) {
            return $access;
        }
        if ($bibId <= 0 || !MagazineRepository::isAvailable()) {
            return 'Numéro introuvable.';
        }

        $foyerId = $this->foyerIdForUser($targetUserId);
        $issue = (new MagazineRepository())->findIssueByBibId($bibId, $targetUserId, $foyerId);
        if ($issue === null) {
            return 'Numéro introuvable.';
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    public function findMagazineIssueForProfile(int $targetUserId, int $bibId): ?array
    {
        if ($targetUserId <= 0 || $bibId <= 0 || !MagazineRepository::isAvailable()) {
            return null;
        }

        $foyerId = $this->foyerIdForUser($targetUserId);

        return (new MagazineRepository())->findIssueByBibId($bibId, $targetUserId, $foyerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMagazineIssuesForSeries(
        int $targetUserId,
        int $seriesId,
        string $statut = LibraryStatut::COLLECTION,
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

        $foyerId = $this->foyerIdForUser($targetUserId);

        return (new MagazineRepository())->listIssuesForSeries(
            $seriesId,
            $targetUserId,
            $foyerId,
            LibraryStatut::normalize($statut),
            $sortBy,
            $sortDir,
            $searchQuery,
            $possessionFilter,
            $limit,
            $offset
        );
    }

    public function countMagazineIssuesForSeries(
        int $targetUserId,
        int $seriesId,
        string $statut = LibraryStatut::COLLECTION,
        string $searchQuery = '',
        string $possessionFilter = MagazineRepository::POSSESSION_ALL
    ): int {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return 0;
        }

        $foyerId = $this->foyerIdForUser($targetUserId);

        return (new MagazineRepository())->countIssuesForSeries(
            $seriesId,
            $targetUserId,
            $foyerId,
            LibraryStatut::normalize($statut),
            $searchQuery,
            $possessionFilter
        );
    }

    private function seriesVisibleOnProfile(int $targetUserId, int $seriesId): bool
    {
        $foyerId = $this->foyerIdForUser($targetUserId);
        $repo = new MagazineRepository();

        if (MagazineRepository::seriesLibraryTableExists()) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM series_bibliotheque sb
                 WHERE sb.series_id = :series_id
                   AND (
                        (sb.statut = :collection AND sb.foyer_id = :foyer_id)
                        OR (sb.statut = :wishlist AND sb.user_id = :user_id)
                   )
                 LIMIT 1'
            );
            $stmt->execute([
                'series_id' => $seriesId,
                'collection' => LibraryStatut::COLLECTION,
                'foyer_id' => $foyerId,
                'wishlist' => LibraryStatut::WISHLIST,
                'user_id' => $targetUserId,
            ]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        return $repo->countIssuesForSeries($seriesId, $targetUserId, $foyerId, null) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listLibraryForUser(
        int $foyerId,
        int $userId,
        string $statut,
        string $sortBy,
        string $sortDir,
        string $mediaDomain
    ): array {
        if (MediaDomain::isGame($mediaDomain) && GameRepository::isAvailable()) {
            return $this->listGamesForUser($foyerId, $userId, $statut, $sortBy, $sortDir);
        }

        $sortColumns = [
            'titre' => 'o.titre COLLATE FRENCH_NOCASE',
            'annee' => 'o.annee',
            'realisateur' => 'o.realisateur COLLATE FRENCH_NOCASE',
        ];
        if (!isset($sortColumns[$sortBy])) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, $statut);

        $sql = 'SELECT ' . CatalogSchema::selectFilmRow() . '
                FROM ' . CatalogSchema::JOIN . '
                WHERE ' . $userWhere . self::publicProfileMediaDomainSql($params, $mediaDomain) . '
                ORDER BY ' . $sortColumns[$sortBy] . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listGamesForUser(
        int $foyerId,
        int $userId,
        string $statut,
        string $sortBy,
        string $sortDir
    ): array {
        $sortColumns = [
            'titre' => 'o.titre COLLATE FRENCH_NOCASE',
            'annee' => 'o.annee',
            'studio' => 'oj.studio COLLATE FRENCH_NOCASE',
            'platform' => 'oj.platform COLLATE NOCASE',
            'genre' => 'oj.genre COLLATE FRENCH_NOCASE',
        ];
        if (!isset($sortColumns[$sortBy])) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, $statut);
        $params['profile_media_domain'] = MediaDomain::JEU;

        $sql = 'SELECT b.id, b.user_id, b.foyer_id, b.oeuvre_id, b.statut, b.created_at,
                       o.titre, o.annee, o.poster_url,
                       oj.platform, oj.studio, oj.genre, oj.is_digital
                FROM bibliotheque b
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
                WHERE ' . $userWhere . ' AND o.media_domain = :profile_media_domain
                ORDER BY ' . $sortColumns[$sortBy] . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['platform_label'] = GamePlatform::label((string) ($row['platform'] ?? ''));
            $row['platform_short'] = GamePlatform::shortLabel((string) ($row['platform'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function publicProfileMediaDomainSql(
        array &$params,
        string $mediaDomain,
        string $oeuvreAlias = 'o'
    ): string {
        if (!CatalogSchema::hasMediaDomainColumn()) {
            return '';
        }

        $params['profile_media_domain'] = MediaDomain::normalize($mediaDomain);

        return ' AND ' . $oeuvreAlias . '.media_domain = :profile_media_domain';
    }

    private function countDistinctViewedFilms(int $userId, ?int $year = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(DISTINCT h.film_id) FROM historique h
                INNER JOIN bibliotheque b ON b.id = h.film_id
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                WHERE h.user_id = ?';
        $params = [$userId];
        if (CatalogSchema::hasMediaDomainColumn()) {
            $sql .= ' AND o.media_domain = ?';
            $params[] = MediaDomain::FILM;
        }
        if ($year !== null && $year > 0) {
            $sql .= ' AND h.date_vue >= ? AND h.date_vue < ?';
            $params[] = $year . '-01-01';
            $params[] = ($year + 1) . '-01-01';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countDistinctNotedGames(int $userId, ?int $year = null): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(DISTINCT h.film_id) FROM historique h
                INNER JOIN bibliotheque b ON b.id = h.film_id
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                WHERE h.user_id = ? AND h.note IS NOT NULL';
        $params = [$userId];
        if (CatalogSchema::hasMediaDomainColumn()) {
            $sql .= ' AND o.media_domain = ?';
            $params[] = MediaDomain::JEU;
        }
        if ($year !== null && $year > 0) {
            $sql .= ' AND h.date_vue >= ? AND h.date_vue < ?';
            $params[] = $year . '-01-01';
            $params[] = ($year + 1) . '-01-01';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
