<?php
/**
 * Lecture des séries et numéros magazines exposés via un lien de partage (sans session).
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkMagazineRepository
{
    /**
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
        if (!MagazineRepository::isAvailable()) {
            return [];
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);

        return (new MagazineRepository())->listSeriesInLibrary(
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
     *
     * @return list<array<string, mixed>>
     */
    public function listIssuesForSeriesForLink(
        array $link,
        int $seriesId,
        string $sortBy = 'numero_ordre',
        string $sortDir = 'desc',
        string $searchQuery = ''
    ): array {
        if (!MagazineRepository::isAvailable() || $seriesId <= 0) {
            return [];
        }
        if (!$this->seriesVisibleForLink($link, $seriesId)) {
            return [];
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);

        return (new MagazineRepository())->listIssuesForSeries(
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
        if ($seriesId <= 0 || !MagazineRepository::isAvailable()) {
            return false;
        }

        if ((new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) === null) {
            return false;
        }

        [$userId, $foyerId, $statut] = $this->linkScope($link);

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

        return (new MagazineRepository())->countIssuesForSeries($seriesId, $userId, $foyerId, $statut) > 0;
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
