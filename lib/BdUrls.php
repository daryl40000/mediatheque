<?php
/**
 * URLs des pages BD / manga (collection, séries, albums, profils publics).
 */

declare(strict_types=1);

namespace Moncine;

final class BdUrls
{
    public static function bdCollectionUrl(
        string $query = '',
        string $sort = 'titre',
        string $dir = 'asc',
        string $viewMode = '',
        ?BdListFilter $filter = null
    ): string {
        $params = [];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($sort !== 'titre') {
            $params['sort'] = $sort;
        }
        if ($dir !== 'asc') {
            $params['dir'] = $dir;
        }
        $viewParam = CollectionViewMode::queryValue($viewMode);
        if ($viewParam !== null) {
            $params['view'] = $viewParam;
        }
        foreach (($filter ?? BdListFilter::empty())->toQueryParams() as $key => $value) {
            $params[$key] = $value;
        }

        return $params === [] ? '/bd.php' : '/bd.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function bdWishlistUrl(string $query = '', string $sort = 'titre', string $dir = 'asc'): string
    {
        $params = [];
        if ($query !== '') {
            $params['q'] = $query;
        }
        if ($sort !== 'titre') {
            $params['sort'] = $sort;
        }
        if (strtolower($dir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/bd-envies.php' : '/bd-envies.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function bdSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $viewMode = '',
        ?BdListFilter $filter = null
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::bdCollectionUrl($searchQuery, $column, $dir, $viewMode, $filter);
    }

    public static function bdWishlistSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = ''
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::bdWishlistUrl($searchQuery, $column, $dir);
    }

    public static function bdUrl(int $bibId): string
    {
        return $bibId > 0 ? '/album-bd.php?id=' . $bibId : '/bd.php';
    }

    public static function bdNavUrl(int $bibId): string
    {
        $path = self::bdUrl($bibId);
        if (MediaContext::current() === MediaDomain::BD) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::BD, $path);
    }

    public static function bdCatalogApiUrl(): string
    {
        return '/rechercher-bd-catalogue.php';
    }

    public static function bdSeriesCatalogApiUrl(): string
    {
        return '/rechercher-series-bd-catalogue.php';
    }

    public static function bdSeriesUrl(
        int $seriesId,
        string $sort = 'tome',
        string $dir = 'asc',
        array $queryExtra = [],
        string $viewMode = ''
    ): string {
        if ($seriesId <= 0) {
            return '/bd.php';
        }

        $params = [
            'series_id' => $seriesId,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        $viewParam = CollectionViewMode::bdSeriesQueryValue($viewMode);
        if ($viewParam !== null) {
            $params['view'] = $viewParam;
        }

        return '/serie-bd.php?' . http_build_query($params);
    }

    public static function bdAddTomeUrl(int $seriesId, string $statut = 'collection'): string
    {
        return $seriesId > 0
            ? '/ajouter-tome-bd.php?series_id=' . $seriesId . '&statut=' . rawurlencode($statut)
            : '/ajouter-serie-bd.php';
    }

    public static function bdSeriesPrintUrl(
        int $seriesId,
        string $sort = 'tome',
        string $dir = 'asc',
        array $queryExtra = []
    ): string {
        if ($seriesId <= 0) {
            return '/bd.php';
        }

        $params = [
            'series_id' => $seriesId,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return '/imprimer-serie-bd.php?' . http_build_query($params);
    }

    /** Fiche catalogue admin — album BD / manga. */
    public static function oeuvreBdUrl(
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1,
        string $catalogMedia = ''
    ): string {
        return CatalogPageUrls::catalogOeuvrePageUrl(
            '/oeuvre-bd.php',
            $oeuvreId,
            $catalogSearch,
            $catalogSort,
            $catalogDir,
            $catalogPage,
            $catalogMedia
        );
    }

    public static function userProfileBdSeriesUrl(
        int $targetUserId,
        int $seriesId,
        string $listMode = 'collection',
        string $sort = 'tome',
        string $dir = 'asc',
        array $queryExtra = []
    ): string {
        if ($targetUserId <= 0 || $seriesId <= 0) {
            return View::userProfileUrl($targetUserId, MediaDomain::BD);
        }

        $statut = $listMode === 'envies' ? LibraryStatut::WISHLIST : LibraryStatut::COLLECTION;
        $params = [
            'id' => (string) $targetUserId,
            'series_id' => (string) $seriesId,
            'statut' => $statut,
            'sort' => $sort,
            'dir' => $dir,
        ];

        foreach ($queryExtra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $value = is_string($value) ? trim($value) : (string) $value;
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return '/utilisateur-serie-bd.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function userProfileBdAlbumUrl(int $targetUserId, int $bibId): string
    {
        if ($targetUserId <= 0 || $bibId <= 0) {
            return View::userProfileUrl($targetUserId, MediaDomain::BD);
        }

        return '/utilisateur-album-bd.php?' . http_build_query([
            'id' => (string) $targetUserId,
            'bib_id' => (string) $bibId,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
