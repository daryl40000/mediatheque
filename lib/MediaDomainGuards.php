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
        '/imprimer-serie-magazine.php',
        '/magazines-recherche.php',
        '/magazine-sujet.php',
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

    /** Redirige vers l’onglet Magazines si la page magazine est ouverte depuis un autre domaine. */
    public static function ensureMagazineContext(string $redirectPath = '/magazines.php'): void
    {
        if (MediaContext::current() === MediaDomain::MAGAZINE) {
            return;
        }

        header(
            'Location: /set-media-domain.php?domain='
            . rawurlencode(MediaDomain::MAGAZINE)
            . '&redirect='
            . rawurlencode($redirectPath)
        );
        exit;
    }
}
