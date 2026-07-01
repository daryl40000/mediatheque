<?php
/**
 * Lecture des séries et tomes BD exposés via un lien de partage (sans session).
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkBdRepository
{
    /**
     * Séries visibles via le lien (collection ou envies).
     *
     * @param array<string, mixed> $link
     *
     * @return list<array<string, mixed>>
     */
    public function listSeriesForLink(
        array $link,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = ''
    ): array {
        if (!BdRepository::isAvailable()) {
            return [];
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);

        return (new BdRepository())->listSeriesInLibrary(
            $userId,
            $foyerId,
            $statut,
            $sortBy,
            $sortDir,
            $searchQuery
        );
    }

    /**
     * Tomes d’une série visibles via le lien.
     *
     * @param array<string, mixed> $link
     *
     * @return list<array<string, mixed>>
     */
    public function listTomesForSeriesForLink(
        array $link,
        int $seriesId,
        string $sortBy = 'tome',
        string $sortDir = 'asc',
        string $searchQuery = ''
    ): array {
        if (!BdRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }
        if (!$this->seriesVisibleForLink($link, $seriesId)) {
            return [];
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);

        return (new BdRepository())->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $sortBy,
            $sortDir,
            $searchQuery
        );
    }

    /**
     * @param array<string, mixed> $link
     */
    public function seriesVisibleForLink(array $link, int $seriesId): bool
    {
        if ($seriesId <= 0 || !BdRepository::isAvailable()) {
            return false;
        }

        if ((new SeriesRepository())->findById($seriesId, MediaDomain::BD) === null) {
            return false;
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);
        $repo = new BdRepository();

        if (BdRepository::seriesLibraryTableExists()) {
            $params = [
                'series_id' => $seriesId,
                'collection' => LibraryStatut::COLLECTION,
                'wishlist' => LibraryStatut::WISHLIST,
                'foyer_id' => $foyerId,
                'user_id' => $userId,
            ];
            $stmt = Database::getInstance()->prepare(
                'SELECT 1 FROM series_bibliotheque sb
                 WHERE sb.series_id = :series_id
                   AND (
                        (sb.statut = :collection AND sb.foyer_id = :foyer_id)
                        OR (sb.statut = :wishlist AND sb.user_id = :user_id)
                   )
                 LIMIT 1'
            );
            $stmt->execute($params);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        }

        return $repo->countTomesForSeries($seriesId, $userId, $foyerId, $statut) > 0;
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string, mixed>|null
     */
    public function findByBibIdForLink(array $link, int $bibId): ?array
    {
        if (!BdRepository::isAvailable() || $bibId <= 0) {
            return null;
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);
        $row = (new BdRepository())->findByBibId($bibId, $userId, $foyerId);
        if ($row === null) {
            return null;
        }
        if (LibraryStatut::normalize((string) ($row['statut'] ?? '')) !== $statut) {
            return null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function linkScope(array $link): array
    {
        $scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
        $userId = (int) ($link['user_id'] ?? 0);
        $foyerId = (int) ($link['foyer_id'] ?? 0);
        $statut = $scope === ShareLinkScope::WISHLIST
            ? LibraryStatut::WISHLIST
            : LibraryStatut::COLLECTION;

        return [$userId, $foyerId, $statut];
    }
}
