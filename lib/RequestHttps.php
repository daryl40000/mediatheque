<?php
/**
 * Détection HTTPS de la requête (cookie Secure, HSTS, URLs).
 *
 * Derrière un reverse proxy, X-Forwarded-Proto n’est lu que si MONCINE_TRUST_PROXY=1.
 */

declare(strict_types=1);

namespace Moncine;

final class RequestHttps
{
    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        if (defined('MONCINE_TRUST_PROXY') && MONCINE_TRUST_PROXY) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
            if (is_string($forwarded) && strtolower($forwarded) === 'https') {
                return true;
            }
        }

        return false;
    }
}
