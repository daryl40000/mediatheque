<?php
/**
 * Lectures bibliothèque et catalogue jeux (listes, fiche, recherche).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameLibraryQuery
{
    public function __construct(private readonly PDO $db)
    {
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
        ?GameListFilter $filter = null
    ): array {
        if (!GameRepository::isAvailable()) {
            return [];
        }

        if (!GameRepository::isValidSortColumn($sortBy)) {
            $sortBy = 'titre';
        }
        $direction = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $orderExpr = GameCatalogSql::sortOrderExpression($sortBy);
        $finishedAtSort = $sortBy === 'finished_at' && GameCompletionRepository::isAvailable();

        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, LibraryStatut::normalize($statut));

        $where = [
            'o.media_domain = :game_domain',
            $userWhere,
        ];
        $params['game_domain'] = MediaDomain::JEU;
        $params['history_user_id'] = $userId;

        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            [$searchSql, $searchParams] = GameCatalogSql::gameSearchSqlConditions(
                $searchQuery,
                includeGenre: true,
                includePrefix: false,
                titleParam: 'q',
            );
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        ($filter ?? GameListFilter::empty())->applyToSql($where, $params);

        $sql = 'SELECT ' . GameCatalogSql::selectGameRow() . GameCatalogSql::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . GameSteamStatsRepository::listJoinSql()
            . ' WHERE ' . implode(' AND ', $where);
        if ($finishedAtSort) {
            $sql .= ' ORDER BY derniere_completion IS NULL ASC, derniere_completion ' . $direction;
        } else {
            $sql .= ' ORDER BY ' . $orderExpr . ' ' . $direction;
        }
        if ($sortBy !== 'titre') {
            $sql .= ', o.titre COLLATE FRENCH_NOCASE ASC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([GameRowMapper::class, 'hydrateGameRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string, mixed>|null */
    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        if (!GameRepository::isAvailable() || $bibId <= 0) {
            return null;
        }

        $params = [
            'bib_id' => $bibId,
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
            'history_user_id' => $userId,
        ];

        $stmt = $this->db->prepare(
            'SELECT ' . GameCatalogSql::selectGameRow() . GameCatalogSql::selectGameHistoryExtras()
            . ' FROM bibliotheque b'
            . ' INNER JOIN oeuvres o ON o.id = b.oeuvre_id'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . GameSteamStatsRepository::listJoinSql()
            . ' WHERE b.id = :bib_id'
            . ' AND o.media_domain = :game_domain'
            . ' AND ('
            . '   (b.statut = :collection AND b.foyer_id = :foyer_id)'
            . '   OR (b.statut = :wishlist AND b.user_id = :user_id)'
            . ' )'
            . ' LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $game = GameRowMapper::hydrateGameRow($row);
        if (GameSchema::hasExtensionColumns()) {
            $baseId = (int) ($game['base_game_oeuvre_id'] ?? 0);
            if (!empty($game['is_extension']) && $baseId > 0) {
                $base = $this->findCatalogByOeuvreId($baseId);
                if ($base !== null) {
                    $game['base_game_label'] = (string) ($base['display_label'] ?? $base['titre'] ?? '');
                    $game['base_game_titre'] = (string) ($base['titre'] ?? '');
                }
            }
        }
        if (GameRepository::hasRemakeColumns()) {
            $originalId = (int) ($game['original_game_oeuvre_id'] ?? 0);
            if (!empty($game['is_remake']) && $originalId > 0) {
                $original = $this->findCatalogByOeuvreId($originalId);
                if ($original !== null) {
                    $game['original_game_label'] = (string) ($original['display_label'] ?? $original['titre'] ?? '');
                    $game['original_game_titre'] = (string) ($original['titre'] ?? '');
                }
            }
        }

        return $game;
    }

    /** @return array<string, mixed>|null */
    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        if (!GameRepository::isAvailable() || $oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . GameCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.id = ? AND o.media_domain = ?'
            . ' LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::JEU]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateCatalogRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findCatalogBySteamAppId(int $appid): ?array
    {
        if (!GameRepository::isAvailable() || $appid <= 0 || !GameSchema::hasSteamAppIdColumn()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . GameCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = ? AND oj.steam_appid = ?'
            . ' LIMIT 1'
        );
        $stmt->execute([MediaDomain::JEU, $appid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateCatalogRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findCatalogByIgdbId(int $igdbId): ?array
    {
        if (!GameRepository::isAvailable() || $igdbId <= 0 || !GameRepository::hasIgdbColumns()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . GameCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE o.media_domain = ? AND oj.igdb_id = ?'
            . ' LIMIT 1'
        );
        $stmt->execute([MediaDomain::JEU, $igdbId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? GameRowMapper::hydrateCatalogRow($row) : null;
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $library = (new BibliothequeRepository())->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library === null) {
            return null;
        }

        return $this->findCatalogByOeuvreId($oeuvreId) !== null ? (int) ($library['id'] ?? 0) : null;
    }

    public function findCollectionBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if ($oeuvreId <= 0 || $this->findCatalogByOeuvreId($oeuvreId) === null) {
            return null;
        }

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
        );

        return $library !== null ? (int) ($library['id'] ?? 0) : null;
    }

    public function findWishlistBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        if ($oeuvreId <= 0 || $this->findCatalogByOeuvreId($oeuvreId) === null) {
            return null;
        }

        $library = (new BibliothequeRepository())->findByOeuvreId(
            $oeuvreId,
            $userId,
            $foyerId,
            LibraryStatut::WISHLIST,
        );

        return $library !== null ? (int) ($library['id'] ?? 0) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        if (!GameRepository::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $prefetchLimit = min(max($limit * 8, 80), 250);
        $params = ['game_domain' => MediaDomain::JEU];
        $where = ['o.media_domain = :game_domain'];

        $query = trim($query);
        if ($query !== '') {
            [$searchSql, $searchParams] = GameCatalogSql::gameSearchSqlConditions(
                $query,
                includeGenre: false,
                includePrefix: true,
                titleParam: 'q_titre',
            );
            $where[] = $searchSql;
            foreach ($searchParams as $key => $value) {
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT ' . GameCatalogSql::selectCatalogRow()
            . ' FROM oeuvres o'
            . ' INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY o.titre COLLATE FRENCH_NOCASE ASC'
            . ' LIMIT ' . ($query !== '' ? $prefetchLimit : $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($query !== '') {
            $rows = SearchMatch::filterRankLimit(
                $rows,
                $query,
                static fn (array $row): string => GameTitle::searchText($row)
                    . ' '
                    . (string) ($row['studio'] ?? ''),
                $limit
            );
        }

        return array_map([GameRowMapper::class, 'hydrateCatalogRow'], $rows);
    }

    /** @return list<string> */
    public function listKnownGenres(int $limit = 80): array
    {
        if (!GameRepository::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $stmt = $this->db->query(
            "SELECT genre FROM oeuvre_jeu WHERE TRIM(genre) != '' ORDER BY genre COLLATE FRENCH_NOCASE ASC"
        );
        if ($stmt === false) {
            return [];
        }

        $known = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (GameGenre::parseList((string) ($row['genre'] ?? '')) as $tag) {
                $key = mb_strtolower($tag);
                if (!isset($known[$key])) {
                    $known[$key] = $tag;
                    continue;
                }
                // Préférer une forme avec majuscules (ex. FPS plutôt que fps).
                $current = $known[$key];
                if ($current === mb_strtolower($current) && $tag !== mb_strtolower($tag)) {
                    $known[$key] = $tag;
                }
            }
        }

        $tags = array_values($known);
        sort($tags, SORT_NATURAL | SORT_FLAG_CASE);

        return array_slice($tags, 0, $limit);
    }
}
