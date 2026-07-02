<?php
/**
 * Garde-fous : pages collection vs domaines pas encore implémentés.
 */

declare(strict_types=1);

namespace Moncine;

final class MediaDomainGuards
{
    /** Pages accessibles uniquement en onglet Films (quiz, sagas…). */
    private const FILM_ONLY_PATHS = [
        '/quiz.php',
        '/resultat.php',
        '/sagas.php',
        '/personnes.php',
        '/meilleurs.php',
        '/ajouter-film.php',
        '/support.php',
    ];

    /** Pages collection / envies réservées à l’onglet Films. */
    private const FILM_COLLECTION_PATHS = [
        '/films.php',
        '/film.php',
        '/souhaits.php',
    ];

    /** Préfixes d’URL réservés à l’onglet Magazines. */
    private const MAGAZINE_ONLY_PATH_PREFIXES = [
        '/magazines',
        '/magazine-',
        '/serie-magazine.php',
        '/ajouter-serie-magazine.php',
        '/ajouter-numero-magazine.php',
        '/modifier-serie-magazine.php',
        '/enregistrer-serie-magazine.php',
        '/enregistrer-numero-magazine.php',
        '/enregistrer-modification-serie-magazine.php',
        '/traiter-numero-magazine.php',
        '/traiter-serie-magazine.php',
        '/imprimer-serie-magazine.php',
        '/magazines-recherche.php',
        '/magazine-sujet.php',
        '/rechercher-series-catalogue.php',
        '/rechercher-numeros-catalogue.php',
        '/export-catalogue-magazines.php',
    ];

    /** Préfixes d’URL réservés à l’onglet BD / Manga. */
    private const BD_ONLY_PATH_PREFIXES = [
        '/bd',
        '/serie-bd.php',
        '/album-bd.php',
        '/ajouter-serie-bd.php',
        '/modifier-serie-bd.php',
        '/enregistrer-modification-serie-bd.php',
        '/ajouter-tome-bd.php',
        '/ajouter-bd.php',
        '/enregistrer-serie-bd.php',
        '/enregistrer-bd.php',
        '/oeuvre-bd.php',
        '/rechercher-bd-catalogue.php',
        '/rechercher-series-bd-catalogue.php',
        '/marquer-bd-lu.php',
        '/supprimer-bd.php',
        '/promouvoir-bd-collection.php',
        '/traiter-tome-bd.php',
        '/imprimer-serie-bd.php',
        '/utilisateur-serie-bd.php',
        '/utilisateur-album-bd.php',
    ];

    /** Pages collection / envies réservées à l’onglet BD. */
    private const BD_COLLECTION_PATHS = [
        '/bd.php',
        '/serie-bd.php',
        '/album-bd.php',
        '/bd-envies.php',
    ];

    /** Préfixes d’URL réservés à l’onglet Jeux. */
    private const GAME_ONLY_PATH_PREFIXES = [
        '/jeux',
        '/jeu.php',
        '/sagas-jeux.php',
        '/ajouter-jeu.php',
        '/modifier-jeu.php',
        '/enregistrer-jeu.php',
        '/enregistrer-modification-jeu.php',
        '/enregistrer-fichier-jeu.php',
        '/supprimer-fichier-jeu.php',
        '/rechercher-jeux-catalogue.php',
    ];

    /** Pages collection / envies réservées à l’onglet Jeux. */
    private const GAME_COLLECTION_PATHS = [
        '/jeux.php',
        '/jeu.php',
        '/jeux-envies.php',
    ];

    public static function isFilmOnlyPath(string $path): bool
    {
        return in_array(self::normalizePath($path), self::FILM_ONLY_PATHS, true);
    }

    public static function isFilmCollectionPath(string $path): bool
    {
        return in_array(self::normalizePath($path), self::FILM_COLLECTION_PATHS, true);
    }

    public static function isMagazineOnlyPath(string $path): bool
    {
        $path = self::normalizePath($path);

        foreach (self::MAGAZINE_ONLY_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isGameOnlyPath(string $path): bool
    {
        $path = self::normalizePath($path);

        foreach (self::GAME_ONLY_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isGameCollectionPath(string $path): bool
    {
        return in_array(self::normalizePath($path), self::GAME_COLLECTION_PATHS, true);
    }

    public static function isBdOnlyPath(string $path): bool
    {
        $path = self::normalizePath($path);

        foreach (self::BD_ONLY_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isBdCollectionPath(string $path): bool
    {
        return in_array(self::normalizePath($path), self::BD_COLLECTION_PATHS, true);
    }

    private static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return $path;
    }

    /**
     * URL de retour après un clic sur un onglet média (évite les boucles quiz → film → quiz).
     */
    public static function redirectTargetForTabSwitch(string $targetDomain, string $currentPath, string $queryString = ''): string
    {
        $pathOnly = self::normalizePath($currentPath);
        if ($pathOnly === '/') {
            $pathOnly = '/films.php';
        }

        $targetDomain = MediaDomain::normalize($targetDomain);

        if (
            self::isFilmOnlyPath($pathOnly)
            || !MediaDomain::isCollectionImplemented($targetDomain)
        ) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isFilm($targetDomain) && self::isMagazineOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isMagazine($targetDomain) && self::isFilmCollectionPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isGame($targetDomain) && (self::isFilmCollectionPath($pathOnly) || self::isMagazineOnlyPath($pathOnly))) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isFilm($targetDomain) && self::isGameOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isMagazine($targetDomain) && self::isGameOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isBd($targetDomain) && (self::isFilmCollectionPath($pathOnly) || self::isMagazineOnlyPath($pathOnly) || self::isGameOnlyPath($pathOnly))) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isFilm($targetDomain) && self::isBdOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isMagazine($targetDomain) && self::isBdOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        if (MediaDomain::isGame($targetDomain) && self::isBdOnlyPath($pathOnly)) {
            return MediaDomain::collectionPath($targetDomain);
        }

        $target = $pathOnly;
        $queryString = trim($queryString);
        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        return $target;
    }

    /** Affiche un message « bientôt » et arrête si le domaine n’est pas encore géré. */
    public static function renderCollectionPageOrExit(): void
    {
        if (MediaDomain::isCollectionImplemented(MediaContext::current())) {
            return;
        }

        View::render('media-domain-soon', [
            'pageTitle' => MediaDomain::label(MediaContext::current()),
            'domain' => MediaContext::current(),
        ]);
        exit;
    }

    /** Redirige vers l’onglet Films pour les outils spécifiques dvdthèque. */
    public static function redirectUnlessFilmFeature(): void
    {
        if (MediaDomain::hasFilmOnlyFeatures(MediaContext::current())) {
            return;
        }

        header(
            'Location: /set-media-domain.php?domain='
            . rawurlencode(MediaDomain::FILM)
            . '&redirect='
            . rawurlencode(MediaDomain::collectionPath(MediaDomain::FILM))
        );
        exit;
    }

    /**
     * URL interne pour basculer d’onglet média puis ouvrir une page.
     * Si $redirectPath est null, conserve l’URL demandée (path + query).
     */
    public static function mediaDomainSwitchUrl(
        string $targetDomain,
        ?string $redirectPath = null,
        string $fallbackPath = '/'
    ): string {
        $target = $redirectPath ?? self::currentRequestUri($fallbackPath);

        return '/set-media-domain.php?domain='
            . rawurlencode(MediaDomain::normalize($targetDomain))
            . '&redirect='
            . rawurlencode(SafeRedirect::path($target));
    }

    /** Redirige vers l’onglet Magazines si la page magazine est ouverte depuis un autre domaine. */
    public static function ensureMagazineContext(?string $redirectPath = null): void
    {
        if (MediaContext::current() === MediaDomain::MAGAZINE) {
            return;
        }

        header('Location: ' . self::mediaDomainSwitchUrl(MediaDomain::MAGAZINE, $redirectPath, '/magazines.php'));
        exit;
    }

    /** Redirige vers l’onglet Jeux si la page jeu est ouverte depuis un autre domaine. */
    public static function ensureGameContext(?string $redirectPath = null): void
    {
        if (MediaContext::current() === MediaDomain::JEU) {
            return;
        }

        header('Location: ' . self::mediaDomainSwitchUrl(MediaDomain::JEU, $redirectPath, '/jeux.php'));
        exit;
    }

    /** Redirige vers l’onglet BD si la page album est ouverte depuis un autre domaine. */
    public static function ensureBdContext(?string $redirectPath = null): void
    {
        if (MediaContext::current() === MediaDomain::BD) {
            return;
        }

        header('Location: ' . self::mediaDomainSwitchUrl(MediaDomain::BD, $redirectPath, '/bd.php'));
        exit;
    }

    /** Path + query de la requête courante (GET), pour reprendre la navigation après changement d’onglet. */
    private static function currentRequestUri(string $fallback): string
    {
        $uri = trim((string) ($_SERVER['REQUEST_URI'] ?? ''));
        if ($uri === '') {
            return $fallback;
        }

        return SafeRedirect::path($uri);
    }
}
