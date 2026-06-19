<?php
/**
 * Identifiants API IGDB (Twitch Developer).
 *
 * Créez une application « Confidential » sur https://dev.twitch.tv/console/apps
 * puis enregistrez client_id et client_secret ici ou via les variables d’environnement.
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbConfig
{
    public const SOURCE_ENVIRONMENT = 'environment';

    public const SOURCE_FILE = 'file';

    /** @return array{client_id: string, client_secret: string}|null */
    public static function getCredentials(): ?array
    {
        $fromEnv = self::credentialsFromEnvironment();
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return self::credentialsFromFile();
    }

    public static function hasCredentials(): bool
    {
        return self::getCredentials() !== null;
    }

    /** @return self::SOURCE_*|null */
    public static function getCredentialsSource(): ?string
    {
        if (self::credentialsFromEnvironment() !== null) {
            return self::SOURCE_ENVIRONMENT;
        }

        return self::credentialsFromFile() !== null ? self::SOURCE_FILE : null;
    }

    /**
     * @param array{client_id?: string, client_secret?: string} $input
     */
    public static function saveCredentials(array $input): bool
    {
        $clientId = self::sanitize((string) ($input['client_id'] ?? ''));
        $clientSecret = self::sanitize((string) ($input['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            return false;
        }

        if (!is_dir(MONCINE_DATA)) {
            mkdir(MONCINE_DATA, 0750, true);
        }

        $payload = json_encode(
            ['client_id' => $clientId, 'client_secret' => $clientSecret],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $written = file_put_contents(MONCINE_IGDB_CREDENTIALS_FILE, $payload . "\n", LOCK_EX);
        if ($written === false) {
            return false;
        }

        @chmod(MONCINE_IGDB_CREDENTIALS_FILE, 0600);

        return true;
    }

    public static function clearStoredCredentials(): bool
    {
        if (self::getCredentialsSource() === self::SOURCE_ENVIRONMENT) {
            return false;
        }

        if (!is_file(MONCINE_IGDB_CREDENTIALS_FILE)) {
            return true;
        }

        return @unlink(MONCINE_IGDB_CREDENTIALS_FILE);
    }

    public static function sanitize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/\s+/', '', $value) ?? $value;

        return $value;
    }

    /** @return array{client_id: string, client_secret: string}|null */
    private static function credentialsFromEnvironment(): ?array
    {
        $clientId = getenv('MONCINE_IGDB_CLIENT_ID');
        $clientSecret = getenv('MONCINE_IGDB_CLIENT_SECRET');
        if (!is_string($clientId) || !is_string($clientSecret)) {
            return null;
        }

        $clientId = self::sanitize($clientId);
        $clientSecret = self::sanitize($clientSecret);
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return ['client_id' => $clientId, 'client_secret' => $clientSecret];
    }

    /** @return array{client_id: string, client_secret: string}|null */
    private static function credentialsFromFile(): ?array
    {
        if (!is_readable(MONCINE_IGDB_CREDENTIALS_FILE)) {
            return null;
        }

        $raw = (string) @file_get_contents(MONCINE_IGDB_CREDENTIALS_FILE);
        if ($raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $clientId = self::sanitize((string) ($data['client_id'] ?? ''));
        $clientSecret = self::sanitize((string) ($data['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        return ['client_id' => $clientId, 'client_secret' => $clientSecret];
    }
}
