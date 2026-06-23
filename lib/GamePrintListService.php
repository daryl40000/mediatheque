<?php
/**
 * Préparation des données pour les pages imprimables (Mes jeux / Mes envies jeux).
 */

declare(strict_types=1);

namespace Moncine;

final class GamePrintListService
{
    /** Limite de lignes sur les pages imprimables (mémoire / taille PDF navigateur). */
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly GameRepository $games = new GameRepository(),
        private readonly FamilyGroupService $familyGroups = new FamilyGroupService(),
        private readonly FoyerRepository $foyers = new FoyerRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   sortBy: string,
     *   sortDir: string,
     *   query: string,
     *   listFilter: GameListFilter
     * }
     */
    public static function collectionParamsFromQuery(array $query): array
    {
        return [
            'sortBy' => (string) ($query['sort'] ?? 'titre'),
            'sortDir' => (string) ($query['dir'] ?? 'asc'),
            'query' => trim((string) ($query['q'] ?? '')),
            'listFilter' => GameListFilter::fromQuery($query),
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
     * @return array<string, mixed> données pour View::render('imprimer-jeux', …)
     */
    public function viewDataForCollectionPrint(array $queryParams): array
    {
        $params = self::collectionParamsFromQuery($queryParams);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        $rows = $this->games->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::COLLECTION,
            $params['sortBy'],
            $params['sortDir'],
            $params['query'],
            $params['listFilter']
        );
        $limit = self::applyRowLimit($rows);

        return [
            'layout' => 'print',
            'pageTitle' => 'Mes jeux — version imprimable',
            'games' => $limit['rows'],
            'printTruncated' => $limit['truncated'],
            'printTotalRows' => $limit['total'],
            'printRowLimit' => self::MAX_ROWS,
            'filterSummary' => PrintListHelper::gameCollectionFilterSummary(
                $params['query'],
                $params['listFilter'],
                $limit['total']
            ),
            'sortSummary' => PrintListHelper::gameSortSummary($params['sortBy'], $params['sortDir']),
            'foyerLabel' => $this->foyerLabelForCurrentUser(),
            'backUrl' => View::gamesCollectionUrl(
                $params['query'],
                $params['sortBy'],
                $params['sortDir'],
                '',
                $params['listFilter']
            ),
        ];
    }

    /**
     * @return array<string, mixed> données pour View::render('imprimer-envies-jeux', …)
     */
    public function viewDataForWishlistPrint(array $queryParams): array
    {
        $sortBy = (string) ($queryParams['sort'] ?? 'titre');
        $sortDir = (string) ($queryParams['dir'] ?? 'asc');
        $query = trim((string) ($queryParams['q'] ?? ''));
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();

        $rows = $this->games->listInLibrary(
            $userId,
            $foyerId,
            LibraryStatut::WISHLIST,
            $sortBy,
            $sortDir,
            $query
        );
        $limit = self::applyRowLimit($rows);

        return [
            'layout' => 'print',
            'pageTitle' => 'Mes envies jeux — version imprimable',
            'games' => $limit['rows'],
            'printTruncated' => $limit['truncated'],
            'printTotalRows' => $limit['total'],
            'printRowLimit' => self::MAX_ROWS,
            'filterSummary' => PrintListHelper::gameWishlistFilterSummary($query, $limit['total']),
            'sortSummary' => PrintListHelper::gameSortSummary($sortBy, $sortDir),
            'backUrl' => View::gamesWishlistUrl($query, $sortBy, $sortDir),
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
