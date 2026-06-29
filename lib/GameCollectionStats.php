<?php
/**
 * Statistiques de la collection jeux vidéo.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameCollectionStats
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Données pour la page Statistiques (onglet Jeux).
     *
     * @return array<string, mixed>
     */
    public function getDashboard(int $userId, int $foyerId): array
    {
        if (!GameRepository::isAvailable()) {
            return $this->emptyDashboard();
        }

        $totalInLibrary = $this->countByStatut($userId, $foyerId, LibraryStatut::COLLECTION);
        $extensionCount = $this->countExtensions($userId, $foyerId);
        $collectionCount = max(0, $totalInLibrary - $extensionCount);
        $wishlistCount = $this->countByStatut($userId, $foyerId, LibraryStatut::WISHLIST);
        $digitalCount = $this->countBySupport($userId, $foyerId, true);
        $physicalCount = $this->countBySupport($userId, $foyerId, false);
        $platformBreakdown = $this->platformBreakdown($userId, $foyerId, $collectionCount);
        $genreBreakdown = $this->genreBreakdown($userId, $foyerId, 8, $collectionCount);
        $decadeBreakdown = $this->decadeBreakdown($userId, $foyerId, $collectionCount);
        $magazineLinksCount = MagazineGameLink::isAvailable()
            ? $this->countMagazineLinksInLibrary($userId, $foyerId)
            : 0;

        return [
            'collection_count' => $collectionCount,
            'extension_count' => $extensionCount,
            'wishlist_count' => $wishlistCount,
            'digital_count' => $digitalCount,
            'physical_count' => $physicalCount,
            'digital_percent' => $collectionCount > 0
                ? round(($digitalCount / $collectionCount) * 100, 1)
                : 0.0,
            'platform_breakdown' => $platformBreakdown,
            'genre_breakdown' => $genreBreakdown,
            'decade_breakdown' => $decadeBreakdown,
            'magazine_links_count' => $magazineLinksCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'collection_count' => 0,
            'extension_count' => 0,
            'wishlist_count' => 0,
            'digital_count' => 0,
            'physical_count' => 0,
            'digital_percent' => 0.0,
            'platform_breakdown' => ['items' => [], 'max' => 1],
            'genre_breakdown' => ['items' => [], 'max' => 1],
            'decade_breakdown' => ['items' => [], 'max' => 1],
            'magazine_links_count' => 0,
        ];
    }

    private function countByStatut(int $userId, int $foyerId, string $statut): int
    {
        $params = [];
        [$userWhere, $params] = CatalogSchema::libraryFilter($foyerId, $userId, $statut);
        $params['game_domain'] = MediaDomain::JEU;

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain AND ' . $userWhere
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countExtensions(int $userId, int $foyerId): int
    {
        if (!GameRepository::hasExtensionColumns()) {
            return 0;
        }

        $params = [
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ];

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id
               AND oj.is_extension = 1'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countBySupport(int $userId, int $foyerId, bool $digital): int
    {
        $params = [
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
            'is_digital' => $digital ? 1 : 0,
        ];

        $extensionSql = $this->extensionExcludeSql();

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id
               AND oj.is_digital = :is_digital'
            . $extensionSql
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{items: list<array{key: string, label: string, count: int, percent: float, url: string}>, max: int}
     */
    private function platformBreakdown(int $userId, int $foyerId, int $total): array
    {
        $extensionSql = $this->extensionExcludeSql();

        if (GameSchema::hasOwnedPlatformsColumn()) {
            $ownedCol = ', b.owned_platforms';
            $platformsCol = GameSchema::hasPlatformsColumn() ? ', oj.platforms' : '';
            $stmt = $this->db->prepare(
                'SELECT oj.platform' . $platformsCol . $ownedCol . '
                 FROM bibliotheque b
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
                 WHERE o.media_domain = :game_domain
                   AND b.statut = :collection
                   AND b.foyer_id = :foyer_id'
                . $extensionSql
            );
            $stmt->execute([
                'game_domain' => MediaDomain::JEU,
                'collection' => LibraryStatut::COLLECTION,
                'foyer_id' => $foyerId,
            ]);

            $counts = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                foreach (GamePlatformList::ownedKeysFromRow($row) as $platformKey) {
                    $counts[$platformKey] = ($counts[$platformKey] ?? 0) + 1;
                }
            }

            arsort($counts);
            $rows = [];
            foreach ($counts as $platformKey => $gameCount) {
                $rows[] = [
                    'platform_key' => $platformKey,
                    'game_count' => $gameCount,
                ];
            }

            return $this->buildBreakdownRows(
                $rows,
                $total,
                'platform_key',
                static function (string $key): string {
                    $label = GamePlatform::label($key);

                    return $label !== '' ? $label : 'Non renseignée';
                },
                static function (string $key): string {
                    if ($key === '') {
                        return View::gamesCollectionUrl(filter: GameListFilter::forPlatform('_none'));
                    }

                    return View::gamesCollectionUrl(filter: GameListFilter::forPlatform($key));
                }
            );
        }

        $stmt = $this->db->prepare(
            'SELECT oj.platform AS platform_key, COUNT(*) AS game_count
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id'
            . $extensionSql
            . ' GROUP BY oj.platform
             ORDER BY game_count DESC, oj.platform COLLATE NOCASE ASC'
        );
        $stmt->execute([
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ]);

        return $this->buildBreakdownRows(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $total,
            'platform_key',
            static function (string $key): string {
                $label = GamePlatform::label($key);

                return $label !== '' ? $label : 'Non renseignée';
            },
            static function (string $key): string {
                if ($key === '') {
                    return View::gamesCollectionUrl(filter: GameListFilter::forPlatform('_none'));
                }

                return View::gamesCollectionUrl(filter: GameListFilter::forPlatform($key));
            }
        );
    }

    /**
     * @return array{items: list<array{key: string, label: string, count: int, percent: float, url: string}>, max: int}
     */
    private function genreBreakdown(int $userId, int $foyerId, int $limit, int $total): array
    {
        if (!GameRepository::isAvailable() || $total <= 0) {
            return ['items' => [], 'max' => 1];
        }

        $limit = max(1, min($limit, 20));
        $extensionSql = $this->extensionExcludeSql();

        $stmt = $this->db->prepare(
            'SELECT oj.genre
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id
               AND TRIM(oj.genre) != \'\''
            . $extensionSql
        );
        $stmt->execute([
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ]);

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (GameGenre::parseList((string) ($row['genre'] ?? '')) as $tag) {
                $key = mb_strtolower($tag);
                if (!isset($counts[$key])) {
                    $counts[$key] = ['label' => $tag, 'count' => 0];
                }
                $counts[$key]['count']++;
            }
        }

        uasort($counts, static function (array $a, array $b): int {
            $byCount = ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            if ($byCount !== 0) {
                return $byCount;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        $items = [];
        $max = 1;
        foreach (array_slice(array_values($counts), 0, $limit) as $entry) {
            $count = (int) ($entry['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }
            $max = max($max, $count);
            $key = mb_strtolower((string) ($entry['label'] ?? ''));
            $items[] = [
                'key' => $key,
                'label' => (string) ($entry['label'] ?? ''),
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
                'url' => View::gamesCollectionUrl(filter: GameListFilter::forGenre($key)),
            ];
        }

        return ['items' => $items, 'max' => $max];
    }

    /**
     * @return array{items: list<array{key: string, label: string, count: int, percent: float, url: string}>, max: int}
     */
    private function decadeBreakdown(int $userId, int $foyerId, int $total): array
    {
        $extensionSql = $this->extensionExcludeSql();

        $stmt = $this->db->prepare(
            'SELECT (CAST(o.annee AS INTEGER) / 10) * 10 AS decade_start, COUNT(*) AS game_count
             FROM bibliotheque b
             INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             INNER JOIN oeuvre_jeu oj ON oj.oeuvre_id = o.id
             WHERE o.media_domain = :game_domain
               AND b.statut = :collection
               AND b.foyer_id = :foyer_id
               AND o.annee >= 1970'
            . $extensionSql
            . ' GROUP BY decade_start
             ORDER BY decade_start DESC'
        );
        $stmt->execute([
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'foyer_id' => $foyerId,
        ]);

        return $this->buildBreakdownRows(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $total,
            'decade_start',
            static function (string $key): string {
                $start = (int) $key;
                if ($start <= 0) {
                    return 'Année inconnue';
                }

                return $start . '–' . ($start + 9);
            },
            static function (string $key): string {
                $start = (int) $key;
                if ($start <= 0) {
                    return '';
                }

                return View::gamesCollectionUrl(filter: GameListFilter::forDecade($start));
            }
        );
    }

    /** Nombre de sujets magazine reliés à des jeux présents dans la collection. */
    private function countMagazineLinksInLibrary(int $userId, int $foyerId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT ms.id)
             FROM magazine_subject ms
             INNER JOIN oeuvres o ON o.id = ms.catalog_oeuvre_id AND o.media_domain = :game_domain
             INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
             WHERE ms.catalog_oeuvre_id IS NOT NULL
               AND (
                    (b.statut = :collection AND b.foyer_id = :foyer_id)
                    OR (b.statut = :wishlist AND b.user_id = :user_id)
               )'
        );
        $stmt->execute([
            'game_domain' => MediaDomain::JEU,
            'collection' => LibraryStatut::COLLECTION,
            'wishlist' => LibraryStatut::WISHLIST,
            'foyer_id' => $foyerId,
            'user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function extensionExcludeSql(): string
    {
        if (!GameRepository::hasExtensionColumns()) {
            return '';
        }

        return ' AND (oj.is_extension = 0 OR oj.is_extension IS NULL)';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(string): string $labelFn
     * @param callable(string): string $urlFn
     * @return array{items: list<array{key: string, label: string, count: int, percent: float, url: string}>, max: int}
     */
    private function buildBreakdownRows(
        array $rows,
        int $total,
        string $keyField,
        callable $labelFn,
        callable $urlFn
    ): array {
        $items = [];
        $max = 1;

        foreach ($rows as $row) {
            $count = (int) ($row['game_count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $key = (string) ($row[$keyField] ?? '');
            $max = max($max, $count);
            $items[] = [
                'key' => $key,
                'label' => $labelFn($key),
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
                'url' => $urlFn($key),
            ];
        }

        return ['items' => $items, 'max' => $max];
    }
}
