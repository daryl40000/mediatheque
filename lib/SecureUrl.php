<?php
/**
 * Validation des URL affichées (affiches) — HTTPS uniquement.
 */

declare(strict_types=1);

namespace Moncine;

final class SecureUrl
{
    /** URL vide ou HTTPS valide pour une affiche. */
    public static function isHttpsUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return isset($parts['scheme']) && strtolower((string) $parts['scheme']) === 'https';
    }

    /** Chemin local Moncine (/posters/123.jpg) ou URL HTTPS distante. */
    public static function sanitizePosterUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (PosterStorage::isLocalWebPath($url)) {
            return $url;
        }

        return self::isHttpsUrl($url) ? $url : '';
    }

    /** Affiche utilisable en base ou formulaire (local ou HTTPS). */
    public static function isValidPosterReference(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }

        return PosterStorage::isLocalWebPath($url) || self::isHttpsUrl($url);
    }
}
