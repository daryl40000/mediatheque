<?php
/**
 * URLs des pages livres.
 */

declare(strict_types=1);

namespace Moncine;

final class LivreUrls
{
    public static function livresCollectionUrl(
        string $query = '',
        string $sort = 'titre',
        string $dir = 'asc',
        string $viewMode = ''
    ): string {
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
        $viewParam = CollectionViewMode::queryValue($viewMode);
        if ($viewParam !== null) {
            $params['view'] = $viewParam;
        }

        return $params === [] ? '/livres.php' : '/livres.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function livresWishlistUrl(string $query = '', string $sort = 'titre', string $dir = 'asc'): string
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

        return $params === []
            ? '/livres-envies.php'
            : '/livres-envies.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function livreUrl(int $bibId): string
    {
        return $bibId > 0 ? '/livre.php?id=' . $bibId : '/livres.php';
    }

    public static function livreEditUrl(int $bibId): string
    {
        return $bibId > 0 ? '/modifier-livre.php?id=' . $bibId : '/livres.php';
    }

    public static function oeuvreLivreUrl(int $oeuvreId): string
    {
        return $oeuvreId > 0 ? '/oeuvre-livre.php?id=' . $oeuvreId : '/catalogue.php';
    }

    public static function gameLivresUrl(int $oeuvreId, int $bibId = 0): string
    {
        if ($oeuvreId <= 0) {
            return '/jeux.php';
        }
        $params = ['oeuvre_id' => $oeuvreId];
        if ($bibId > 0) {
            $params['id'] = $bibId;
        }

        return '/jeu-livres.php?' . http_build_query($params);
    }

    public static function addLivreUrl(string $statut = LibraryStatut::COLLECTION): string
    {
        $statut = LibraryStatut::normalize($statut);

        return '/ajouter-livre.php?statut=' . rawurlencode($statut);
    }

    public static function sagasLivresUrl(string $sagaName = ''): string
    {
        $sagaName = trim($sagaName);
        if ($sagaName === '') {
            return '/sagas-livres.php';
        }

        return '/sagas-livres.php?saga=' . rawurlencode($sagaName);
    }

    public static function livresSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $query = '',
        bool $wishlist = false,
        string $viewMode = ''
    ): string {
        $dir = ($currentSort === $column && strtolower($currentDir) !== 'desc') ? 'desc' : 'asc';
        if ($wishlist) {
            return self::livresWishlistUrl($query, $column, $dir);
        }

        return self::livresCollectionUrl($query, $column, $dir, $viewMode);
    }
}
