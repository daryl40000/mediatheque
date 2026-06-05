<?php
/**
 * Préparation des données pour la liste imprimable / PDF d’une série magazine.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazinePrintListService
{
    /** Limite de lignes (séries très longues). */
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly MagazineRepository $magazines = new MagazineRepository(),
        private readonly SeriesRepository $series = new SeriesRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|null null si série introuvable
     */
    public function viewDataForSeriesPrint(array $query): ?array
    {
        $seriesId = (int) ($query['series_id'] ?? 0);
        if ($seriesId <= 0) {
            return null;
        }

        $series = $this->series->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return null;
        }

        $params = self::paramsFromQuery($query);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        $issues = $this->magazines->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $params['statut'],
            $params['sortBy'],
            $params['sortDir'],
            $params['searchQuery'],
            $params['possessionFilter']
        );

        $totalCount = count($issues);
        $truncated = $totalCount > self::MAX_ROWS;
        if ($truncated) {
            $issues = array_slice($issues, 0, self::MAX_ROWS);
        }

        $rows = [];
        foreach ($issues as $issue) {
            $rows[] = [
                'numero' => (string) ($issue['numero'] ?? ''),
                'date_label' => PublicationType::formatParutionDate(
                    (string) ($issue['date_parution'] ?? ''),
                    (string) ($issue['publication_type'] ?? $series['publication_type'] ?? '')
                ),
                'pages' => (int) ($issue['pages'] ?? 0),
                'est_hors_serie' => !empty($issue['est_hors_serie']),
                'possession_label' => MagazineSupport::possessionStatusLabel($issue),
                'possession_class' => MagazineSupport::possessionStatusCssClass($issue),
            ];
        }

        $backQuery = array_filter([
            'statut' => $params['statut'],
            'q' => $params['searchQuery'] !== '' ? $params['searchQuery'] : null,
            'possession' => $params['possessionFilter'] !== MagazineRepository::POSSESSION_ALL
                ? $params['possessionFilter']
                : null,
        ]);

        return [
            'layout' => 'print',
            'pageTitle' => (string) ($series['titre'] ?? 'Série'),
            'backUrl' => View::magazineSeriesUrl(
                $seriesId,
                $params['sortBy'],
                $params['sortDir'],
                $backQuery
            ),
            'series' => $series,
            'rows' => $rows,
            'statut' => $params['statut'],
            'filterSummary' => self::filterSummary($params),
            'sortSummary' => self::sortSummary($params['sortBy'], $params['sortDir']),
            'totalCount' => $totalCount,
            'truncated' => $truncated,
            'maxRows' => self::MAX_ROWS,
            'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{
     *   statut: string,
     *   sortBy: string,
     *   sortDir: string,
     *   searchQuery: string,
     *   possessionFilter: string
     * }
     */
    public static function paramsFromQuery(array $query): array
    {
        return [
            'statut' => LibraryStatut::normalize((string) ($query['statut'] ?? LibraryStatut::COLLECTION)),
            'sortBy' => (string) ($query['sort'] ?? 'numero_ordre'),
            'sortDir' => (string) ($query['dir'] ?? 'desc'),
            'searchQuery' => trim((string) ($query['q'] ?? '')),
            'possessionFilter' => MagazineRepository::normalizePossessionFilter(
                (string) ($query['possession'] ?? MagazineRepository::POSSESSION_ALL)
            ),
        ];
    }

    /**
     * @param array{
     *   statut: string,
     *   searchQuery: string,
     *   possessionFilter: string
     * } $params
     */
    public static function filterSummary(array $params): string
    {
        $parts = [];

        if ($params['statut'] === LibraryStatut::WISHLIST) {
            $parts[] = 'Mes envies';
        } else {
            $parts[] = 'Collection du foyer';
        }

        $possession = $params['possessionFilter'];
        if ($possession === MagazineRepository::POSSESSION_OWNED) {
            $parts[] = 'numéros possédés uniquement';
        } elseif ($possession === MagazineRepository::POSSESSION_UNOWNED) {
            $parts[] = 'numéros non possédés uniquement';
        }

        if ($params['searchQuery'] !== '') {
            $parts[] = 'recherche : « ' . $params['searchQuery'] . ' »';
        }

        return implode(' · ', $parts);
    }

    public static function sortSummary(string $sortBy, string $sortDir): string
    {
        $column = match ($sortBy) {
            'numero' => 'Numéro',
            'date_parution' => 'Date de parution',
            default => 'Ordre de tri',
        };
        $dir = strtolower($sortDir) === 'asc' ? 'croissant' : 'décroissant';

        return $column . ' (' . $dir . ')';
    }
}
