<?php
/**
 * Préparation des données pour la liste imprimable / PDF d’une série BD.
 */

declare(strict_types=1);

namespace Moncine;

final class BdPrintListService
{
    public const MAX_ROWS = 2000;

    /** Limite pour la liste des séries (Mes BD / Mes envies). */
    public const MAX_SERIES_ROWS = 500;

    public function __construct(
        private readonly BdRepository $bd = new BdRepository(),
        private readonly SeriesRepository $series = new SeriesRepository(),
        private readonly FamilyGroupService $familyGroups = new FamilyGroupService(),
        private readonly FoyerRepository $foyers = new FoyerRepository(),
    ) {
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-bd', …)
     */
    public function viewDataForCollectionPrint(array $queryParams): array
    {
        return $this->viewDataForSeriesListPrint($queryParams, LibraryStatut::COLLECTION);
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-envies-bd', …)
     */
    public function viewDataForWishlistPrint(array $queryParams): array
    {
        return $this->viewDataForSeriesListPrint($queryParams, LibraryStatut::WISHLIST);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    private function viewDataForSeriesListPrint(array $queryParams, string $statut): array
    {
        $query = trim((string) ($queryParams['q'] ?? ''));
        $sortBy = (string) ($queryParams['sort'] ?? 'titre');
        $sortDir = (string) ($queryParams['dir'] ?? 'asc');
        $isWishlist = $statut === LibraryStatut::WISHLIST;

        $rows = $this->bd->listSeriesInLibrary(
            UserContext::currentUserId(),
            UserContext::currentFoyerId(),
            $statut,
            $sortBy,
            $sortDir,
            $query
        );
        $total = count($rows);
        $truncated = $total > self::MAX_SERIES_ROWS;
        if ($truncated) {
            $rows = array_slice($rows, 0, self::MAX_SERIES_ROWS);
        }

        $filterParts = [$isWishlist ? 'Mes envies' : 'Collection du foyer'];
        if ($query !== '') {
            $filterParts[] = 'recherche : « ' . $query . ' »';
        }
        $filterParts[] = $total . ' série' . ($total > 1 ? 's' : '');

        return [
            'layout' => 'print',
            'pageTitle' => $isWishlist ? 'Mes envies BD — version imprimable' : 'Mes BD — version imprimable',
            'seriesList' => $rows,
            'printTruncated' => $truncated,
            'printTotalRows' => $total,
            'printRowLimit' => self::MAX_SERIES_ROWS,
            'filterSummary' => implode(' · ', $filterParts),
            'sortSummary' => self::seriesListSortSummary($sortBy, $sortDir),
            'foyerLabel' => $isWishlist ? '' : $this->foyerLabelForCurrentUser(),
            'isWishlist' => $isWishlist,
            'countColumnLabel' => $isWishlist ? 'Tomes en envies' : 'Possédés / catalogue',
            'backUrl' => $isWishlist
                ? View::bdWishlistUrl($query, $sortBy, $sortDir)
                : View::bdCollectionUrl($query, $sortBy, $sortDir),
        ];
    }

    public function foyerLabelForCurrentUser(): string
    {
        $userId = UserContext::currentUserId();
        $group = $this->familyGroups->findGroupForUser($userId);
        if ($group !== null) {
            return trim((string) ($group['nom'] ?? ''));
        }

        $foyerId = UserContext::currentFoyerId();
        if ($foyerId <= 0) {
            return '';
        }

        $foyer = $this->foyers->findById($foyerId);

        return $foyer !== null ? trim((string) ($foyer['nom'] ?? '')) : '';
    }

    public static function seriesListSortSummary(string $sortBy, string $sortDir): string
    {
        $column = match ($sortBy) {
            'editeur' => 'Éditeur',
            'kind' => 'Type',
            default => 'Titre',
        };
        $dir = strtolower($sortDir) === 'desc' ? 'décroissant' : 'croissant';

        return $column . ' (' . $dir . ')';
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|null
     */
    public function viewDataForSeriesPrint(array $query): ?array
    {
        $seriesId = (int) ($query['series_id'] ?? 0);
        if ($seriesId <= 0) {
            return null;
        }

        $series = $this->series->findById($seriesId, MediaDomain::BD);
        if ($series === null) {
            return null;
        }

        $params = self::paramsFromQuery($query);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        $tomes = $this->bd->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $params['statut'],
            $params['sortBy'],
            $params['sortDir'],
            $params['searchQuery'],
            $params['possessionFilter']
        );

        $totalCount = count($tomes);
        $truncated = $totalCount > self::MAX_ROWS;
        if ($truncated) {
            $tomes = array_slice($tomes, 0, self::MAX_ROWS);
        }

        $rows = [];
        foreach ($tomes as $tome) {
            $rows[] = [
                'tome_numero' => (int) ($tome['tome_numero'] ?? 0),
                'tome_label' => (string) ($tome['tome_label'] ?? ''),
                'est_hors_serie' => !empty($tome['est_hors_serie']),
                'display_titre' => (string) ($tome['display_titre'] ?? BdRowMapper::displayTitle($tome)),
                'annee' => (int) ($tome['annee'] ?? 0),
                'possession_label' => BdPossession::possessionStatusLabel($tome),
                'possession_class' => BdPossession::isPossessed($tome)
                    ? 'magazine-possession--owned'
                    : 'magazine-possession--none',
                'support_label' => BdPhysicalSupport::label((string) ($tome['support_physique'] ?? '')),
            ];
        }

        $backQuery = array_filter([
            'statut' => $params['statut'],
            'q' => $params['searchQuery'] !== '' ? $params['searchQuery'] : null,
            'possession' => ($params['possessionFilter'] ?? null) !== null
                ? (string) $params['possessionFilter']
                : null,
        ]);

        $kindLabel = BdKind::label(BdSeriesMetadata::kindFromSeries($series));

        return [
            'layout' => 'print',
            'pageTitle' => (string) ($series['titre'] ?? 'Série'),
            'backUrl' => View::bdSeriesUrl($seriesId, $params['sortBy'], $params['sortDir'], $backQuery),
            'series' => $series,
            'rows' => $rows,
            'statut' => $params['statut'],
            'filterSummary' => self::filterSummary($params),
            'sortSummary' => self::sortSummary($params['sortBy'], $params['sortDir']),
            'totalCount' => $totalCount,
            'truncated' => $truncated,
            'maxRows' => self::MAX_ROWS,
            'kindLabel' => $kindLabel,
        ];
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{statut: string, sortBy: string, sortDir: string, searchQuery: string, possessionFilter: ?string}
     */
    public static function paramsFromQuery(array $query): array
    {
        $possession = BdRepository::normalizePossessionFilter((string) ($query['possession'] ?? ''));

        return [
            'statut' => LibraryStatut::normalize((string) ($query['statut'] ?? LibraryStatut::COLLECTION)),
            'sortBy' => (string) ($query['sort'] ?? 'tome'),
            'sortDir' => (string) ($query['dir'] ?? 'asc'),
            'searchQuery' => trim((string) ($query['q'] ?? '')),
            'possessionFilter' => $possession !== BdRepository::POSSESSION_ALL ? $possession : null,
        ];
    }

    /**
     * @param array{statut: string, searchQuery: string, possessionFilter?: ?string} $params
     */
    public static function filterSummary(array $params): string
    {
        $parts = [];

        if ($params['statut'] === LibraryStatut::WISHLIST) {
            $parts[] = 'Mes envies';
        } else {
            $parts[] = 'Collection du foyer';
        }

        $possession = $params['possessionFilter'] ?? null;
        if ($possession === BdRepository::FILTER_HORS_SERIE) {
            $parts[] = 'hors-série uniquement';
        } elseif ($possession === BdRepository::POSSESSION_OWNED) {
            $parts[] = 'possédés uniquement';
        } elseif ($possession === BdRepository::POSSESSION_UNOWNED) {
            $parts[] = 'non possédés uniquement';
        }

        if ($params['searchQuery'] !== '') {
            $parts[] = 'recherche : « ' . $params['searchQuery'] . ' »';
        }

        return implode(' · ', $parts);
    }

    public static function sortSummary(string $sortBy, string $sortDir): string
    {
        $column = match ($sortBy) {
            'titre' => 'Titre',
            'annee' => 'Année',
            'read_at' => 'Lu le',
            'note' => 'Note',
            default => 'Tome',
        };
        $dir = strtolower($sortDir) === 'desc' ? 'décroissant' : 'croissant';

        return $column . ' (' . $dir . ')';
    }
}
