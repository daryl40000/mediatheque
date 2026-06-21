<?php
/**
 * Pagination de la page Mes films (liste vs vignettes).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmCollectionPagination
{
    /** Nombre de lignes affichées en mode vignettes. */
    public const GRID_ROWS = 7;

    /** Colonnes de référence pour le calcul (grille ~8 colonnes sur écran large). */
    public const GRID_COLUMNS = 8;

    public const GRID_PER_PAGE = self::GRID_ROWS * self::GRID_COLUMNS;

    public const LIST_PER_PAGE = 100;

    public static function perPage(string $viewMode): int
    {
        if (CollectionViewMode::isGrid($viewMode)) {
            return self::GRID_PER_PAGE;
        }

        if (CollectionViewMode::isShelf($viewMode)) {
            return 0;
        }

        return self::LIST_PER_PAGE;
    }

    /** Vue bibliothèque : toute la collection sur une seule page (comme Mes jeux). */
    public static function usesPagination(string $viewMode): bool
    {
        return !CollectionViewMode::isShelf($viewMode);
    }

    /** @return array{page: int, perPage: int, offset: int, totalPages: int} */
    public static function resolve(int $requestedPage, int $listTotal, string $viewMode): array
    {
        if (CollectionViewMode::isShelf($viewMode)) {
            return [
                'page' => 1,
                'perPage' => max(0, $listTotal),
                'offset' => 0,
                'totalPages' => 1,
            ];
        }

        $perPage = self::perPage($viewMode);
        $totalPages = max(1, (int) ceil(max(0, $listTotal) / $perPage));
        $page = max(1, min($requestedPage, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'perPage' => $perPage,
            'offset' => $offset,
            'totalPages' => $totalPages,
        ];
    }
}
