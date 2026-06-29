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

        return self::base() . self::webPath($path);
    }

    /**
     * Préfixe web de l’application (ex. /moncine si installé dans un sous-dossier).
     * Variable d’environnement optionnelle : MONCINE_WEB_BASE_PATH=/moncine
     */
    public static function webBasePath(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $configured = getenv('MONCINE_WEB_BASE_PATH');
        if (is_string($configured) && trim($configured) !== '') {
            return $cached = rtrim(trim($configured), '/');
        }

        if (PHP_SAPI === 'cli') {
            return $cached = '';
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script === '') {
            return $cached = '';
        }

        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '' || $dir === '/') {
            return $cached = '';
        }

        return $cached = $dir;
    }

    /** Chemin web absolu depuis la racine du site (inclut le préfixe d’installation). */
    public static function webPath(string $path): string
    {
        if ($path === '') {
            return self::webBasePath() !== '' ? self::webBasePath() : '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $base = self::webBasePath();

        return $base === '' ? $path : $base . $path;
    }
}
