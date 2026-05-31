<?php
/**
 * Clé API TMDB (https://www.themoviedb.org/settings/api).
 * Synopsis en français via language=fr-FR.
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbConfig
{
    public const SOURCE_ENVIRONMENT = 'environment';

    public const SOURCE_FILE = 'file';

    public static function getApiKey(): ?string
    {
        $fromEnv = getenv('MONCINE_TMDB_API_KEY');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::sanitizeKey($fromEnv);
        }

        if (!is_readable(MONCINE_TMDB_KEY_FILE)) {
            return null;
        }
        $key = self::sanitizeKey((string) @file_get_contents(MONCINE_TMDB_KEY_FILE));
        return $key !== '' ? $key : null;
    }

    public static function hasApiKey(): bool
    {
        return self::getApiKey() !== null;
    }

    /** D’où vient la clé active : `environment`, `file`, ou `null` si aucune. */
    public static function getKeySource(): ?string
    {
        $fromEnv = getenv('MONCINE_TMDB_API_KEY');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::SOURCE_ENVIRONMENT;
        }

        if (!is_readable(MONCINE_TMDB_KEY_FILE)) {
            return null;
        }

        $key = self::sanitizeKey((string) @file_get_contents(MONCINE_TMDB_KEY_FILE));

        return $key !== '' ? self::SOURCE_FILE : null;
    }

    /** Supprime le fichier `tmdb_api_key.txt` (sans effet si la clé vient de `MONCINE_TMDB_API_KEY`). */
    public static function clearStoredApiKey(): bool
    {
        if (self::getKeySource() === self::SOURCE_ENVIRONMENT) {
            return false;
        }

        if (!is_file(MONCINE_TMDB_KEY_FILE)) {
            return true;
        }

        return @unlink(MONCINE_TMDB_KEY_FILE);
    }

    public static function saveApiKey(string $key): bool
    {
        $key = self::sanitizeKey($key);
        if ($key === '') {
            return false;
        }
        if (!is_dir(MONCINE_DATA)) {
            mkdir(MONCINE_DATA, 0750, true);
        }
        $written = file_put_contents(MONCINE_TMDB_KEY_FILE, $key . "\n", LOCK_EX);
        if ($written === false) {
            return false;
        }
        @chmod(MONCINE_TMDB_KEY_FILE, 0600);
        return true;
    }

    public static function sanitizeKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        $key = preg_replace('/\s+/', '', $key) ?? $key;
        return $key;
    }
}
