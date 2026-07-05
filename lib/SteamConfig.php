<?php
/**
 * Clé API Steam Web (GetOwnedGames).
 * https://steamcommunity.com/dev/apikey
 */

declare(strict_types=1);

namespace Moncine;

final class SteamConfig
{
    public const SOURCE_ENVIRONMENT = 'environment';

    public const SOURCE_FILE = 'file';

    public static function getApiKey(): ?string
    {
        $fromEnv = getenv('MONCINE_STEAM_API_KEY');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::sanitizeKey($fromEnv);
        }

        if (!is_readable(MONCINE_STEAM_API_KEY_FILE)) {
            return null;
        }

        $key = self::sanitizeKey((string) @file_get_contents(MONCINE_STEAM_API_KEY_FILE));

        return $key !== '' ? $key : null;
    }

    public static function hasApiKey(): bool
    {
        return self::getApiKey() !== null;
    }

    /** @return self::SOURCE_*|null */
    public static function getKeySource(): ?string
    {
        $fromEnv = getenv('MONCINE_STEAM_API_KEY');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return self::SOURCE_ENVIRONMENT;
        }

        if (!is_readable(MONCINE_STEAM_API_KEY_FILE)) {
            return null;
        }

        $key = self::sanitizeKey((string) @file_get_contents(MONCINE_STEAM_API_KEY_FILE));

        return $key !== '' ? self::SOURCE_FILE : null;
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

        $written = file_put_contents(MONCINE_STEAM_API_KEY_FILE, $key . "\n", LOCK_EX);
        if ($written === false) {
            return false;
        }

        @chmod(MONCINE_STEAM_API_KEY_FILE, 0600);

        return true;
    }

    public static function clearStoredApiKey(): bool
    {
        if (self::getKeySource() === self::SOURCE_ENVIRONMENT) {
            return false;
        }

        if (!is_file(MONCINE_STEAM_API_KEY_FILE)) {
            return true;
        }

        return @unlink(MONCINE_STEAM_API_KEY_FILE);
    }

    public static function sanitizeKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', '', $value) ?? $value;

        return $value;
    }

    /** SteamID64 : uniquement chiffres, 17 chiffres typiquement. */
    public static function sanitizeSteamId(string $value): string
    {
        $value = preg_replace('/\D+/', '', trim($value)) ?? '';

        return $value;
    }

    public static function isValidSteamId(string $steamId): bool
    {
        $steamId = self::sanitizeSteamId($steamId);

        return strlen($steamId) >= 15 && strlen($steamId) <= 20;
    }
}
