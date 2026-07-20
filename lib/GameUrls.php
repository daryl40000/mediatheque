<?php
/**
 * URLs des pages jeux vidéo (collection, fiches, franchises, catalogue).
 */

declare(strict_types=1);

namespace Moncine;

final class GameUrls
{
    public static function addGameChoiceUrl(int $oeuvreId = 0): string
    {
        if ($oeuvreId > 0) {
            return '/ajouter-jeu.php?oeuvre_id=' . $oeuvreId;
        }

        return '/ajouter-jeu.php';
    }

    /** Fiche catalogue admin — jeu vidéo. */
    public static function oeuvreJeuUrl(
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1,
        string $catalogMedia = ''
    ): string {
        return CatalogPageUrls::catalogOeuvrePageUrl(
            '/oeuvre-jeu.php',
            $oeuvreId,
            $catalogSearch,
            $catalogSort,
            $catalogDir,
            $catalogPage,
            $catalogMedia
        );
    }

    /** Liste des magazines qui traitent un jeu catalogue. */
    public static function gameMagazinesUrl(int $oeuvreId, int $bibId = 0): string
    {
        if ($oeuvreId <= 0) {
            return '/jeux.php';
        }

        $params = ['oeuvre_id' => $oeuvreId];
        if ($bibId > 0) {
            $params['id'] = $bibId;
        }

        return '/jeu-magazines.php?' . http_build_query($params);
    }

    public static function gameFranchiseUrl(string $franchiseName, string $viewMode = ''): string
    {
        $franchiseName = trim($franchiseName);
        $params = [];
        if ($franchiseName !== '') {
            $params['franchise'] = $franchiseName;
        }
        $viewParam = CollectionViewMode::queryValue($viewMode);
        if ($viewParam !== null) {
            $params['view'] = $viewParam;
        }

        return $params === [] ? '/sagas-jeux.php' : '/sagas-jeux.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function gamesCollectionUrl(
        string $query = '',
        string $sort = 'titre',
        string $dir = 'asc',
        string $viewMode = '',
        ?GameListFilter $filter = null
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
        foreach (($filter ?? GameListFilter::empty())->toQueryParams() as $key => $value) {
            $params[$key] = $value;
        }

        return $params === [] ? '/jeux.php' : '/jeux.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable de Mes jeux (mêmes filtres / tri que /jeux.php). */
    public static function gamesPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        ?GameListFilter $filter = null
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }
        foreach (($filter ?? GameListFilter::empty())->toQueryParams() as $key => $value) {
            $params[$key] = $value;
        }

        return $params === [] ? '/imprimer-jeux.php' : '/imprimer-jeux.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable des envies jeux. */
    public static function gamesWishlistPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc'
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/imprimer-envies-jeux.php' : '/imprimer-envies-jeux.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function gamesWishlistUrl(string $query = '', string $sort = 'titre', string $dir = 'asc'): string
    {
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

        return $params === [] ? '/jeux-envies.php' : '/jeux-envies.php?' . http_build_query($params);
    }

    /** Lien de tri pour la liste « Mes jeux » (clic = bascule asc/desc). */
    public static function gamesSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $viewMode = '',
        ?GameListFilter $filter = null
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::gamesCollectionUrl($searchQuery, $column, $dir, $viewMode, $filter);
    }

    public static function gamesWishlistSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = ''
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::gamesWishlistUrl($searchQuery, $column, $dir);
    }

    public static function gameUrl(int $bibId): string
    {
        return $bibId > 0 ? '/jeu.php?id=' . $bibId : '/jeux.php';
    }

    /** Lien cliquable vers une fiche jeu (bascule d’onglet si besoin). */
    public static function gameNavUrl(int $bibId): string
    {
        $path = self::gameUrl($bibId);
        if (MediaContext::current() === MediaDomain::JEU) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::JEU, $path);
    }

    public static function gameCatalogApiUrl(): string
    {
        return '/rechercher-jeux-catalogue.php';
    }

    public static function gameEditUrl(int $bibId): string
    {
        return $bibId > 0 ? '/modifier-jeu.php?id=' . $bibId : '/jeux.php';
    }
}
