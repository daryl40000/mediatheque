<?php
/**
 * URLs des pages films (collection, envies, sagas, fiches catalogue).
 *
 * Extrait de View pour alléger la façade (même schéma que BdUrls / GameUrls / MagazineUrls).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmUrls
{
    /** Lien de tri pour la table « Ma collection » (clic = bascule asc/desc). */
    public static function filmsSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $kindFilter = '',
        string $viewMode = ''
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        return self::filmsCollectionUrl($searchQuery, $column, $dir, $kindFilter, $viewMode);
    }

    /** Lien vers la collection (recherche, tri, filtre catégorie, mode d’affichage, page). */
    public static function filmsCollectionUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $kindFilter = '',
        string $viewMode = '',
        int $page = 1
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
        $kindFilter = ContentKindFilter::normalize($kindFilter);
        if ($kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $kindFilter;
        }
        if (CollectionViewMode::isGrid($viewMode) || CollectionViewMode::isShelf($viewMode)) {
            $params['view'] = CollectionViewMode::queryValue($viewMode) ?? CollectionViewMode::GRID;
        }
        if ($page > 1) {
            $params['page'] = (string) $page;
        }

        return $params === [] ? '/films.php' : '/films.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable de la collection (mêmes filtres / tri que Mes films). */
    public static function filmsPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $kindFilter = ''
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
        $kindFilter = ContentKindFilter::normalize($kindFilter);
        if ($kindFilter !== ContentKindFilter::ALL) {
            $params['kind'] = $kindFilter;
        }

        return $params === [] ? '/imprimer-films.php' : '/imprimer-films.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Version imprimable des envies (Mes envies ou envies du groupe). */
    public static function wishlistPrintUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $scope = WishlistScope::MINE
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/imprimer-envies.php' : '/imprimer-envies.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Page de choix ou formulaire d’ajout de film. */
    public static function addFilmChoiceUrl(int $oeuvreId = 0): string
    {
        if ($oeuvreId > 0) {
            return '/ajouter-film.php?oeuvre_id=' . $oeuvreId;
        }

        return '/ajouter-film.php';
    }

    /**
     * Lien depuis la recherche par personne : fiche film si déjà en bibliothèque, sinon ajout catalogue.
     *
     * @param array<string, mixed> $film
     */
    public static function personSearchFilmUrl(array $film): string
    {
        $presence = (string) ($film['library_presence'] ?? 'none');
        $bibId = (int) ($film['id'] ?? 0);
        if ($bibId > 0 && $presence !== 'none') {
            return '/film.php?id=' . $bibId;
        }

        $oeuvreId = (int) ($film['oeuvre_id'] ?? 0);

        return self::addFilmChoiceUrl($oeuvreId);
    }

    public static function addFilmUrl(string $statut, int $oeuvreId = 0): string
    {
        $statut = LibraryStatut::normalize($statut);
        $params = ['statut' => $statut];
        if ($oeuvreId > 0) {
            $params['oeuvre_id'] = (string) $oeuvreId;
        }

        return '/ajouter-film.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Fiche d’une œuvre film dans le catalogue partagé. */
    public static function oeuvreUrl(
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1,
        string $catalogMedia = ''
    ): string {
        return CatalogPageUrls::catalogOeuvrePageUrl(
            '/oeuvre.php',
            $oeuvreId,
            $catalogSearch,
            $catalogSort,
            $catalogDir,
            $catalogPage,
            $catalogMedia
        );
    }

    /** Lien vers la wishlist (Mes envies films). */
    public static function wishlistUrl(
        string $searchQuery = '',
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $scope = WishlistScope::MINE
    ): string {
        $params = [];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }
        if ($sortBy !== '' && $sortBy !== 'titre') {
            $params['sort'] = $sortBy;
        }
        if (strtolower($sortDir) === 'desc') {
            $params['dir'] = 'desc';
        }

        return $params === [] ? '/souhaits.php' : '/souhaits.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function wishlistSortUrl(
        string $column,
        string $currentSort,
        string $currentDir,
        string $searchQuery = '',
        string $scope = WishlistScope::MINE
    ): string {
        $dir = 'asc';
        if ($currentSort === $column && strtolower($currentDir) === 'asc') {
            $dir = 'desc';
        }

        $params = [
            'sort' => $column,
            'dir' => $dir,
        ];
        $searchQuery = trim($searchQuery);
        if ($searchQuery !== '') {
            $params['q'] = $searchQuery;
        }
        if (WishlistScope::normalize($scope) === WishlistScope::GROUP) {
            $params['scope'] = WishlistScope::GROUP;
        }

        return '/souhaits.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Indicateur visuel du tri actif (↑ ou ↓). */
    public static function filmsSortIndicator(string $column, string $currentSort, string $currentDir): string
    {
        if ($currentSort !== $column) {
            return '';
        }

        return strtolower($currentDir) === 'desc' ? ' ↓' : ' ↑';
    }

    public static function sagaUrl(string $sagaName): string
    {
        $sagaName = trim($sagaName);
        if ($sagaName === '') {
            return '/sagas.php';
        }

        return '/sagas.php?saga=' . rawurlencode($sagaName);
    }

    public static function supportFilterUrl(string $supportKey): string
    {
        if (!SupportPhysique::isValid($supportKey)) {
            return '/support.php';
        }

        return '/support.php?type=' . rawurlencode($supportKey);
    }

    public static function personSearchUrl(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '/personnes.php';
        }

        return '/personnes.php?q=' . rawurlencode($name);
    }

    /** Liste des magazines qui traitent un film catalogue. */
    public static function filmMagazinesUrl(int $oeuvreId, int $bibId = 0): string
    {
        $params = ['oeuvre_id' => max(0, $oeuvreId)];
        if ($bibId > 0) {
            $params['id'] = $bibId;
        }

        return '/film-magazines.php?' . http_build_query($params);
    }

    /** Fiche film en bibliothèque (bascule d’onglet média si besoin). */
    public static function filmLibraryNavUrl(int $bibId): string
    {
        $path = '/film.php?id=' . $bibId;
        if (MediaContext::current() === MediaDomain::FILM) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::FILM, $path);
    }
}
