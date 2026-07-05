<?php
/**
 * Client API Steam Web — bibliothèque possédée (GetOwnedGames).
 */

declare(strict_types=1);

namespace Moncine;

final class SteamWebApiClient
{
    private const OWNED_GAMES_URL = 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/';

    private const HTTP_TIMEOUT = 25;

    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return list<array{appid: int, name: string, playtime_forever: int, rtime_last_played: int, img_icon_url: string}>
     */
    public function getOwnedGames(string $steamId, ?string $apiKey = null): array
    {
        $steamId = SteamConfig::sanitizeSteamId($steamId);
        if (!SteamConfig::isValidSteamId($steamId)) {
            $this->lastError = 'SteamID64 invalide (15 à 20 chiffres). Renseignez-le dans Paramètres du compte.';

            return [];
        }

        $apiKey = $apiKey ?? SteamConfig::getApiKey();
        if ($apiKey === null || $apiKey === '') {
            $this->lastError = 'Clé API Steam manquante. Configurez-la sur la page Importer.';

            return [];
        }

        $query = http_build_query([
            'key' => $apiKey,
            'steamid' => $steamId,
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
            'skip_unvetted_apps' => 'false',
            'format' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);

        $url = self::OWNED_GAMES_URL . '?' . $query;
        $body = $this->httpGet($url);
        if ($body === null) {
            return [];
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->lastError = 'Réponse Steam illisible.';

            return [];
        }

        if (!is_array($data)) {
            $this->lastError = 'Réponse Steam vide.';

            return [];
        }

        $games = $data['response']['games'] ?? null;
        if (!is_array($games)) {
            $this->lastError = 'Bibliothèque Steam vide ou profil privé / introuvable.';

            return [];
        }

        $out = [];
        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            $appid = (int) ($game['appid'] ?? 0);
            if ($appid <= 0) {
                continue;
            }
            $out[] = [
                'appid' => $appid,
                'name' => trim((string) ($game['name'] ?? '')),
                'playtime_forever' => max(0, (int) ($game['playtime_forever'] ?? 0)),
                'rtime_last_played' => max(0, (int) ($game['rtime_last_played'] ?? 0)),
                'img_icon_url' => trim((string) ($game['img_icon_url'] ?? '')),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    public static function storeUrl(int $appid, string $name = ''): string
    {
        $appid = max(0, $appid);
        $base = 'https://store.steampowered.com/app/' . $appid;
        $slug = SteamTitleMatch::steamStoreSlug($name);
        if ($slug !== '') {
            return $base . '/' . $slug . '/';
        }

        return $base . '/';
    }

    public static function iconUrl(int $appid, string $iconHash): string
    {
        $iconHash = trim($iconHash);
        if ($appid <= 0 || $iconHash === '') {
            return '';
        }

        return 'https://media.steampowered.com/steamcommunity/public/images/apps/'
            . $appid . '/' . $iconHash . '.jpg';
    }

    private function httpGet(string $url): ?string
    {
        $this->lastError = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $this->lastError = 'Impossible d’initialiser cURL.';

                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false) {
                $this->lastError = 'Connexion Steam impossible.';

                return null;
            }
            if ($code >= 400) {
                $this->lastError = 'Steam HTTP ' . $code . '.';

                return null;
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::HTTP_TIMEOUT,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $this->lastError = 'Connexion Steam impossible.';

            return null;
        }

        return $body;
    }
}
