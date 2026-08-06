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

    /** Limite pour la liste des séries (Mes magazines / Mes envies). */
    public const MAX_SERIES_ROWS = 500;

    public function __construct(
        private readonly MagazineRepository $magazines = new MagazineRepository(),
        private readonly SeriesRepository $series = new SeriesRepository(),
        private readonly FamilyGroupService $familyGroups = new FamilyGroupService(),
        private readonly FoyerRepository $foyers = new FoyerRepository(),
    ) {
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-magazines', …)
     */
    public function viewDataForCollectionPrint(array $queryParams): array
    {
        return $this->viewDataForSeriesListPrint($queryParams, LibraryStatut::COLLECTION);
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-envies-magazines', …)
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

        $rows = $this->magazines->listSeriesInLibrary(
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
            'pageTitle' => $isWishlist
                ? 'Mes envies magazines — version imprimable'
                : 'Mes magazines — version imprimable',
            'seriesList' => $rows,
            'printTruncated' => $truncated,
            'printTotalRows' => $total,
            'printRowLimit' => self::MAX_SERIES_ROWS,
            'filterSummary' => implode(' · ', $filterParts),
            'sortSummary' => self::seriesListSortSummary($sortBy, $sortDir),
            'foyerLabel' => $isWishlist ? '' : $this->foyerLabelForCurrentUser(),
            'isWishlist' => $isWishlist,
            'countColumnLabel' => $isWishlist ? 'Numéros en envies' : 'Possédés / catalogue',
            'backUrl' => $isWishlist
                ? View::magazinesWishlistUrl($query, $sortBy, $sortDir)
                : View::magazinesUrl($query, $sortBy, $sortDir),
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
            default => 'Titre',
        };
        $dir = strtolower($sortDir) === 'desc' ? 'décroissant' : 'croissant';

        return $column . ' (' . $dir . ')';
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
     * Version imprimable des sujets d’une série filtrés par catégorie + année de parution.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null null si paramètres / série invalides
     */
    public function viewDataForSeriesStatsSubjectsPrint(array $query): ?array
    {
        if (!MagazineSeriesStats::isAvailable()) {
            return null;
        }

        $seriesId = (int) ($query['series_id'] ?? 0);
        $category = MagazineSubject::normalizeCategory((string) ($query['category'] ?? ''));
        $year = (int) ($query['year'] ?? 0);
        $statut = LibraryStatut::normalize((string) ($query['statut'] ?? LibraryStatut::COLLECTION));

        if ($seriesId <= 0 || $year < 1900 || $year > 2100) {
            return null;
        }
        if ($category === '' || !isset(MagazineSubject::choices()[$category])) {
            return null;
        }

        $series = $this->series->findById($seriesId, MediaDomain::MAGAZINE);
        if ($series === null) {
            return null;
        }

        $subjects = (new MagazineSeriesStats())->listSubjectsByCategoryAndYear(
            $seriesId,
            $category,
            $year
        );

        $categoryLabel = MagazineSubject::label($category);
        $showScores = $category === MagazineSubject::TEST;
        $rows = [];
        foreach ($subjects as $subject) {
            $page = MagazineSubjectRepository::normalizePage($subject['page'] ?? 0);
            $score = array_key_exists('score', $subject) && $subject['score'] !== null
                ? (float) $subject['score']
                : null;
            $ratingScale = MagazineRatingScale::normalize($subject['rating_scale'] ?? null);
            $scoreDisplay = '';
            if ($showScores && $score !== null && $ratingScale !== null) {
                $scoreDisplay = MagazineRatingScale::formatDisplay($score, $ratingScale);
            }

            $rows[] = [
                'issue_label' => (string) ($subject['issue_label'] ?? '—'),
                'display_label' => (string) ($subject['display_label'] ?? ''),
                'page' => $page,
                'score_display' => $scoreDisplay,
            ];
        }

        $backParams = [
            'series_id' => $seriesId,
            'category' => $category,
            'year' => $year,
        ];
        if ($statut !== LibraryStatut::COLLECTION) {
            $backParams['statut'] = $statut;
        }

        return [
            'layout' => 'print',
            'pageTitle' => $categoryLabel . ' ' . $year . ' — ' . (string) ($series['titre'] ?? 'Série'),
            'backUrl' => '/stats-serie-magazine.php?' . http_build_query($backParams) . '#sujets-annee',
            'series' => $series,
            'rows' => $rows,
            'category' => $category,
            'categoryLabel' => $categoryLabel,
            'year' => $year,
            'showScores' => $showScores,
            'totalCount' => count($rows),
            'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
            'statut' => $statut,
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
        } elseif ($possession === MagazineRepository::FILTER_HORS_SERIE) {
            $parts[] = 'numéros hors-série uniquement';
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
