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
     *   collection_count: int,
     *   wishlist_count: int,
     *   films_vus_count: int,
     *   films_vus_year_count: int,
     *   year: int
     * }
     */
    public function getStats(int $userId): array
    {
        $year = (int) date('Y');
        $foyerId = $this->foyerIdForUser($userId);

        $collectionCount = 0;
        if ($foyerId > 0) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM bibliotheque
                 WHERE foyer_id = ? AND statut = ?'
            );
            $stmt->execute([$foyerId, LibraryStatut::COLLECTION]);
            $collectionCount = (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM bibliotheque WHERE user_id = ? AND statut = ?'
        );
        $stmt->execute([$userId, LibraryStatut::WISHLIST]);
        $wishlistCount = (int) $stmt->fetchColumn();

        $filmsVus = $this->countDistinctViewedFilms($userId);
        $filmsVusYear = $this->countDistinctViewedFilms($userId, $year);

        return [
            'collection_count' => $collectionCount,
            'wishlist_count' => $wishlistCount,
            'films_vus_count' => $filmsVus,
            'films_vus_year_count' => $filmsVusYear,
            'year' => $year,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lastViewedFilms(int $userId, int $limit = 5): array
    {
        if ($userId <= 0 || $limit <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . ',
                    MAX(h.date_vue) AS derniere_vue
             FROM historique h
             INNER JOIN bibliotheque b ON b.id = h.film_id
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE h.user_id = :profile_user_id
             GROUP BY b.id
             ORDER BY derniere_vue DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['profile_user_id' => $userId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function lastCollectionFilms(int $userId, int $limit = 5): array
    {
        $foyerId = $this->foyerIdForUser($userId);
        if ($foyerId <= 0 || $limit <= 0) {
            return [];
        }

        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::COLLECTION);
        $stmt = $this->db->prepare(
            'SELECT ' . CatalogSchema::selectFilmRow() . '
             FROM ' . CatalogSchema::JOIN . '
             WHERE ' . $userWhere . self::publicProfileMediaDomainSql($params) . '
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function lastWishlistFilms(int $userId, int $limit = 5): array
    {
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
            . self::publicProfileMediaDomainSql($params) . '
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCollection(int $userId, string $sortBy = 'titre', string $sortDir = 'asc'): array
    {
        $foyerId = $this->foyerIdForUser($userId);
        if ($foyerId <= 0) {
            return [];
        }

        return $this->listLibraryForUser($foyerId, $userId, LibraryStatut::COLLECTION, $sortBy, $sortDir);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listWishlist(int $userId, string $sortBy = 'titre', string $sortDir = 'asc'): array
    {
        return $this->listLibraryForUser(0, $userId, LibraryStatut::WISHLIST, $sortBy, $sortDir);
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

        $sql = 'SELECT h.id AS historique_id, h.date_vue, h.note,
                       ' . CatalogSchema::selectFilmRow() . '
                FROM historique h
                INNER JOIN bibliotheque b ON b.id = h.film_id
                INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                WHERE ' . $where . '
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
     * @return list<array<string, mixed>>
     */
    private function listLibraryForUser(
        int $foyerId,
        int $userId,
        string $statut,
        string $sortBy,
        string $sortDir
    ): array {
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
                WHERE ' . $userWhere . self::publicProfileMediaDomainSql($params) . '
                ORDER BY ' . $sortColumns[$sortBy] . ' ' . $direction;
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Profils publics : dvdthèque films uniquement (M0).
     *
     * @param array<string, mixed> $params
     */
    private static function publicProfileMediaDomainSql(array &$params): string
    {
        if (!CatalogSchema::hasMediaDomainColumn()) {
            return '';
        }

        $params['profile_media_domain'] = MediaDomain::FILM;

        return ' AND o.media_domain = :profile_media_domain';
    }

    private function countDistinctViewedFilms(int $userId, ?int $year = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(DISTINCT h.film_id) FROM historique h
                INNER JOIN bibliotheque b ON b.id = h.film_id
                WHERE h.user_id = ?';
        $params = [$userId];
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
