<?php
/**
 * Redirections internes sûres (évite les détournements vers des sites externes).
 */

declare(strict_types=1);

namespace Moncine;

final class SafeRedirect
{
    /**
     * @return chemin interne sûr (ex. /films.php) ; sinon accueil.
     * Rejette https://… et //evil.com (détournement après connexion).
     */
    public static function path(string $raw): string
    {
        $path = trim($raw);
        if ($path === '' || !str_starts_with($path, '/') || str_contains($path, '//')) {
            return '/';
        }

        return $path;
    }
}
