<?php
/**
 * En-têtes HTTP de sécurité pour les pages web.
 *
 * Complément au code PHP : le navigateur applique ces règles avant d’afficher la page.
 */

declare(strict_types=1);

namespace Moncine;

final class SecurityHeaders
{
    /** Pages avec jeton de partage dans l’URL : pas d’indexation / cache, Referer restreint. */
    private const SHARE_PATHS = [
        '/partage.php',
        '/partage-film.php',
        '/partage-jeux.php',
        '/partage-jeu.php',
        '/partage-bd.php',
        '/partage-serie-bd.php',
        '/partage-album-bd.php',
        '/partage-magazines.php',
        '/partage-serie-magazine.php',
    ];

    /** Pages avec jeton sensible dans l’URL : ne pas fuiter le Referer. */
    private const NO_REFERRER_PATHS = [
        '/confirmer-inscription.php',
        '/confirmer-email.php',
        '/reinitialiser-mot-de-passe.php',
    ];

    public static function send(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $path = self::currentPath();
        $isSharePath = in_array($path, self::SHARE_PATHS, true);
        $noReferrer = $isSharePath || in_array($path, self::NO_REFERRER_PATHS, true);
        header(
            $noReferrer
                ? 'Referrer-Policy: no-referrer'
                : 'Referrer-Policy: strict-origin-when-cross-origin'
        );

        self::sendContentSecurityPolicy();
        self::sendStrictTransportSecurityIfHttps();
        if ($isSharePath) {
            self::sendShareVisitorHeaders();
        }
    }

    /** Pages partagées visiteur : pas d’indexation, pas de mise en cache proxy. */
    public static function sendShareVisitorHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }

    private static function sendContentSecurityPolicy(): void
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' https: data:",
            "font-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
        ];

        header('Content-Security-Policy: ' . implode('; ', $directives));
    }

    private static function sendStrictTransportSecurityIfHttps(): void
    {
        if (!self::isHttpsRequest()) {
            return;
        }

        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    public static function isHttpsRequest(): bool
    {
        return RequestHttps::isSecure();
    }

    private static function currentPath(): string
    {
        return parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    }
}
