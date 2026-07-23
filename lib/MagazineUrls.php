<?php
/**
 * URLs des pages magazines (collection, séries, numéros, sujets, profils publics).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineUrls
{
    public static function magazinesUrl(string $query = '', string $sort = 'titre', string $dir = 'asc'): string
    {
        $params = array_filter([
            'q' => trim($query),
            'sort' => $sort,
            'dir' => $dir,
        ], static fn (string $v): bool => $v !== '');

        return $params === [] ? '/magazines.php' : '/magazines.php?' . http_build_query($params);
    }

    public static function magazineSeriesUrl(
        int $seriesId,
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($seriesId <= 0) {
            return '/magazines.php';
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

        return '/serie-magazine.php?' . http_build_query($params);
    }

    /** Liste imprimable / PDF d’une série (mêmes filtres que la page série). */
    public static function magazineSeriesPrintUrl(
        int $seriesId,
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($seriesId <= 0) {
            return '/magazines.php';
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

        return '/imprimer-serie-magazine.php?' . http_build_query($params);
    }

    /** Page statistiques d’évolution d’une série magazine. */
    public static function magazineSeriesStatsUrl(int $seriesId, string $statut = LibraryStatut::COLLECTION): string
    {
        if ($seriesId <= 0) {
            return '/magazines.php';
        }

        $params = ['series_id' => $seriesId];
        $statut = LibraryStatut::normalize($statut);
        if ($statut !== LibraryStatut::COLLECTION) {
            $params['statut'] = $statut;
        }

        return '/stats-serie-magazine.php?' . http_build_query($params);
    }

    public static function magazineIssueUrl(int $bibId): string
    {
        return $bibId > 0 ? '/magazine-numero.php?id=' . $bibId : '/magazines.php';
    }

    /** Lien cliquable vers un numéro magazine (bascule d’onglet si besoin). */
    public static function magazineIssueNavUrl(int $bibId): string
    {
        $path = self::magazineIssueUrl($bibId);
        if (MediaContext::current() === MediaDomain::MAGAZINE) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::MAGAZINE, $path);
    }

    public static function magazineSubjectSearchUrl(): string
    {
        return '/magazines-recherche.php';
    }

    /** Liste des numéros ayant offert un jeu (depuis les statistiques). */
    public static function magazinesJeuxOffertsUrl(): string
    {
        return '/magazines-jeux-offerts.php';
    }

    public static function magazineSubjectUrl(int $subjectId): string
    {
        return $subjectId > 0
            ? '/magazine-sujet.php?id=' . $subjectId
            : self::magazineSubjectSearchUrl();
    }

    /** Lien cliquable vers une fiche sujet magazine (bascule d’onglet si besoin). */
    public static function magazineSubjectNavUrl(int $subjectId): string
    {
        $path = self::magazineSubjectUrl($subjectId);
        if (MediaContext::current() === MediaDomain::MAGAZINE) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::MAGAZINE, $path);
    }

    public static function magazineSubjectApiUrl(): string
    {
        return '/rechercher-sujets-magazine.php';
    }

    public static function magazineSubjectCatalogApiUrl(): string
    {
        return '/rechercher-catalogue-sujet-magazine.php';
    }

    /** Fiche catalogue admin — numéro de magazine. */
    public static function oeuvreMagazineUrl(
        int $oeuvreId,
        string $catalogSearch = '',
        string $catalogSort = 'titre',
        string $catalogDir = 'asc',
        int $catalogPage = 1,
        string $catalogMedia = ''
    ): string {
        return CatalogPageUrls::catalogOeuvrePageUrl(
            '/oeuvre-magazine.php',
            $oeuvreId,
            $catalogSearch,
            $catalogSort,
            $catalogDir,
            $catalogPage,
            $catalogMedia
        );
    }

    /** Lien cliquable vers une fiche catalogue magazine (bascule d’onglet si besoin). */
    public static function oeuvreMagazineNavUrl(int $oeuvreId): string
    {
        $path = self::oeuvreMagazineUrl($oeuvreId);
        if (MediaContext::current() === MediaDomain::MAGAZINE) {
            return $path;
        }

        return MediaDomainGuards::mediaDomainSwitchUrl(MediaDomain::MAGAZINE, $path);
    }

    public static function userProfileMagazineSeriesUrl(
        int $targetUserId,
        int $seriesId,
        string $listMode = 'collection',
        string $sort = 'numero_ordre',
        string $dir = 'desc',
        array $queryExtra = []
    ): string {
        if ($targetUserId <= 0 || $seriesId <= 0) {
            return View::userProfileUrl($targetUserId, MediaDomain::MAGAZINE);
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

        return '/utilisateur-serie-magazine.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function userProfileMagazineIssueUrl(int $targetUserId, int $bibId): string
    {
        if ($targetUserId <= 0 || $bibId <= 0) {
            return View::userProfileUrl($targetUserId, MediaDomain::MAGAZINE);
        }

        return '/utilisateur-numero-magazine.php?' . http_build_query([
            'id' => (string) $targetUserId,
            'bib_id' => (string) $bibId,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
