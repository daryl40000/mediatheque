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

    public static function isFilmOnlyPath(string $path): bool
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return in_array($path, self::FILM_ONLY_PATHS, true);
    }

    /**
     * URL de retour après un clic sur un onglet média (évite les boucles quiz → film → quiz).
     */
    public static function redirectTargetForTabSwitch(string $targetDomain, string $currentPath, string $queryString = ''): string
    {
        $pathOnly = parse_url($currentPath, PHP_URL_PATH) ?: $currentPath;
        if ($pathOnly === '' || $pathOnly === '/') {
            $pathOnly = '/films.php';
        }

        if (
            self::isFilmOnlyPath($pathOnly)
            || !MediaDomain::isCollectionImplemented(MediaDomain::normalize($targetDomain))
        ) {
            return '/films.php';
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
            . rawurlencode('/films.php')
        );
        exit;
    }
}
