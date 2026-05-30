<?php
/**
 * Préparation des données pour les pages imprimables (Mes films / Mes envies).
 * Centralise la logique partagée avec films.php et souhaits.php.
 */

declare(strict_types=1);

namespace Moncine;

final class PrintListService
{
    /** Limite de lignes sur les pages imprimables (mémoire / taille PDF navigateur). */
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly FilmRepository $films = new FilmRepository(),
        private readonly GroupWishlistRepository $groupWishlist = new GroupWishlistRepository(),
        private readonly FamilyGroupService $familyGroups = new FamilyGroupService(),
        private readonly FoyerRepository $foyers = new FoyerRepository(),
        private readonly WishlistTargetRepository $wishlistTargets = new WishlistTargetRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{sortBy: string, sortDir: string, query: string, kindFilter: string}
     */
    public static function collectionParamsFromQuery(array $query): array
    {
        return [
            'sortBy' => (string) ($query['sort'] ?? 'titre'),
            'sortDir' => (string) ($query['dir'] ?? 'asc'),
            'query' => trim((string) ($query['q'] ?? '')),
            'kindFilter' => ContentKindFilter::normalize((string) ($query['kind'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   sortBy: string,
     *   sortDir: string,
     *   query: string,
     *   scope: string,
     *   isGroupScope: bool
     * }
     */
    public static function wishlistParamsFromQuery(array $query, bool $canShowGroup): array
    {
        $scope = WishlistScope::normalize((string) ($query['scope'] ?? WishlistScope::MINE));
        if ($scope === WishlistScope::GROUP && !$canShowGroup) {
            $scope = WishlistScope::MINE;
        }

        $sortBy = (string) ($query['sort'] ?? 'titre');
        $sortDir = (string) ($query['dir'] ?? 'asc');
        if ($scope === WishlistScope::GROUP && !array_key_exists('sort', $query)) {
            $sortBy = 'votes';
            $sortDir = 'desc';
        }

        return [
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'query' => trim((string) ($query['q'] ?? '')),
            'scope' => $scope,
            'isGroupScope' => $scope === WishlistScope::GROUP,
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

    /**
     * @return array<string, mixed> données pour View::render('imprimer-films', …)
     */
    public function viewDataForCollectionPrint(array $queryParams): array
    {
        $params = self::collectionParamsFromQuery($queryParams);
        $films = $this->films->findAll(
            $params['sortBy'],
            $params['sortDir'],
            $params['query'],
            $params['kindFilter']
        );
        $limit = self::applyRowLimit($films);

        return [
            'layout' => 'print',
            'pageTitle' => 'Mes films — version imprimable',
            'films' => $limit['rows'],
            'printTruncated' => $limit['truncated'],
            'printTotalRows' => $limit['total'],
            'printRowLimit' => self::MAX_ROWS,
            'filterSummary' => PrintListHelper::collectionFilterSummary(
                $params['query'],
                $params['kindFilter'],
                $limit['total'],
                $this->films->count()
            ),
            'sortSummary' => PrintListHelper::sortSummary($params['sortBy'], $params['sortDir']),
            'foyerLabel' => $this->foyerLabelForCurrentUser(),
            'backUrl' => View::filmsCollectionUrl(
                $params['query'],
                $params['sortBy'],
                $params['sortDir'],
                $params['kindFilter']
            ),
        ];
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-envies', …)
     */
    public function viewDataForWishlistPrint(array $queryParams): array
    {
        $foyerId = UserContext::currentFoyerId();
        $canShowGroup = $this->groupWishlist->canShowGroupView($foyerId);
        $params = self::wishlistParamsFromQuery($queryParams, $canShowGroup);
        $userId = UserContext::currentUserId();

        if ($params['isGroupScope']) {
            $films = $this->groupWishlist->findAggregated(
                $foyerId,
                $userId,
                $params['sortBy'],
                $params['sortDir'],
                $params['query']
            );
        } else {
            $films = $this->films->findAllWishlist($params['sortBy'], $params['sortDir'], $params['query']);
        }

        $groupName = '';
        if ($canShowGroup) {
            $group = $this->familyGroups->findGroupForUser($userId);
            $groupName = (string) ($group['nom'] ?? '');
        }

        $wishlistTargetsByFilmId = [];
        if (!$params['isGroupScope'] && WishlistTargetRepository::tableExists() && $films !== []) {
            $ids = array_map(static fn (array $f): int => (int) ($f['id'] ?? 0), $films);
            $wishlistTargetsByFilmId = $this->wishlistTargets->mapByBibliothequeIds($ids);
        }

        $limit = self::applyRowLimit($films);

        return [
            'layout' => 'print',
            'pageTitle' => $params['isGroupScope']
                ? 'Envies du groupe — version imprimable'
                : 'Mes envies — version imprimable',
            'films' => $limit['rows'],
            'printTruncated' => $limit['truncated'],
            'printTotalRows' => $limit['total'],
            'printRowLimit' => self::MAX_ROWS,
            'filterSummary' => PrintListHelper::wishlistFilterSummary(
                $params['query'],
                $limit['total'],
                $params['isGroupScope'],
                $groupName
            ),
            'sortSummary' => PrintListHelper::sortSummary($params['sortBy'], $params['sortDir']),
            'isGroupScope' => $params['isGroupScope'],
            'wishlistTargetsByFilmId' => $wishlistTargetsByFilmId,
            'backUrl' => View::wishlistUrl(
                $params['query'],
                $params['sortBy'],
                $params['sortDir'],
                $params['scope']
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{rows: list<array<string, mixed>>, truncated: bool, total: int}
     */
    private static function applyRowLimit(array $rows): array
    {
        $total = count($rows);
        if ($total <= self::MAX_ROWS) {
            return ['rows' => $rows, 'truncated' => false, 'total' => $total];
        }

        return [
            'rows' => array_slice($rows, 0, self::MAX_ROWS),
            'truncated' => true,
            'total' => $total,
        ];
    }
}
