<?php
/**
 * URL de base de l’application (liens dans les e-mails).
 */

declare(strict_types=1);

namespace Moncine;

final class AppUrl
{
    public static function base(): string
    {
        $configured = getenv('MONCINE_BASE_URL');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && strtolower($forwarded) === 'https') {
            $https = true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            $https = true;
        }

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = $https ? 'https' : 'http';

        return $scheme . '://' . $host;
    }

    public static function path(string $path): string
    {
        if ($path === '' || !str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return self::base() . $path;
    }
}
