<?php
/**
 * Client HTTP IGDB v4 (authentification Twitch OAuth2).
 *
 * Documentation : https://api-docs.igdb.com/
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbClient
{
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';

    private const API_BASE = 'https://api.igdb.com/v4/';

    private const IMAGE_BASE = 'https://images.igdb.com/igdb/image/upload/t_cover_big/';

    private const HTTP_TIMEOUT = 20;

    private const MAX_BODY_BYTES = 512_000;

    private const GAME_FIELDS = 'name, cover.image_id, first_release_date, genres.name,'
        . ' involved_companies.company.name, involved_companies.developer, involved_companies.publisher,'
        . ' franchises.name, game_modes.name, themes.name, alternative_names.name';

    private ?string $lastError = null;

    /** @var array{token: string, expires_at: int}|null */
    private static ?array $tokenCache = null;

    private static ?int $steamExternalGameSourceId = null;

    private static bool $steamExternalGameSourceLookupDone = false;

    /** @param array{client_id?: string, client_secret?: string}|null $credentials */
    public function __construct(
        private readonly ?array $credentials = null
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Recherche un jeu par titre (meilleur résultat).
     *
     * @return array<string, mixed>|null
     */
    public function searchGame(string $title, ?int $year = null): ?array
    {
        $title = trim($title);
        if ($title === '') {
            $this->lastError = 'Titre vide.';

            return null;
        }

        $escaped = str_replace('"', '\\"', $title);
        $body = 'search "' . $escaped . '";'
            . ' fields ' . self::GAME_FIELDS . ';'
            . ' where version_parent = null;'
            . ' limit 8;';

        $rows = $this->queryEndpoint('games', $body);
        if ($rows === null || $rows === []) {
            return null;
        }

        return $this->pickBestMatch($rows, $year);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGameById(int $igdbId): ?array
    {
        if ($igdbId <= 0) {
            $this->lastError = 'Identifiant IGDB invalide.';

            return null;
        }

        $body = 'fields ' . self::GAME_FIELDS . '; where id = ' . $igdbId . '; limit 1;';
        $rows = $this->queryEndpoint('games', $body);
        if ($rows === null || $rows === []) {
            return null;
        }

        return $this->normalizeGameRow($rows[0]);
    }

    /**
     * Résout des AppID Steam vers des identifiants IGDB (external_games, category Steam = 1).
     *
     * @param list<int> $steamAppIds
     * @return array<int, int> appid => igdb game id
     */
    public function mapSteamAppIdsToIgdbIds(array $steamAppIds): array
    {
        $steamAppIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $steamAppIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($steamAppIds === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($steamAppIds, 100) as $chunkIndex => $chunk) {
            if ($chunkIndex > 0) {
                usleep(250_000);
            }

            foreach ($this->queryExternalGamesForSteamAppIds($chunk) as $appid => $igdbId) {
                $map[$appid] = $igdbId;
            }
        }

        return $map;
    }

    /**
     * @param list<int> $steamAppIds
     * @return array<int, int>
     */
    private function queryExternalGamesForSteamAppIds(array $steamAppIds): array
    {
        if ($steamAppIds === []) {
            return [];
        }

        $quotedUids = implode(',', array_map(
            static fn (int $id): string => '"' . $id . '"',
            $steamAppIds
        ));
        $uidFilter = 'uid = (' . $quotedUids . ')';
        $filters = [];
        $sourceId = $this->resolveSteamExternalGameSourceId();
        if ($sourceId !== null) {
            $filters[] = 'external_game_source = ' . $sourceId . ' & ' . $uidFilter;
        }
        $filters[] = 'category = 1 & ' . $uidFilter;

        foreach ($filters as $where) {
            $body = 'fields game, uid; where ' . $where . '; limit 500;';
            $rows = $this->queryEndpoint('external_games', $body);
            if ($rows === null || $rows === []) {
                continue;
            }

            $parsed = $this->parseExternalGameUidMap($rows);
            if ($parsed !== []) {
                return $parsed;
            }
        }

        return [];
    }

    private function resolveSteamExternalGameSourceId(): ?int
    {
        if (self::$steamExternalGameSourceLookupDone) {
            return self::$steamExternalGameSourceId !== null && self::$steamExternalGameSourceId > 0
                ? self::$steamExternalGameSourceId
                : null;
        }

        self::$steamExternalGameSourceLookupDone = true;
        self::$steamExternalGameSourceId = 0;

        $rows = $this->queryEndpoint('external_game_sources', 'fields id, name; where name = "steam"; limit 1;');
        if (is_array($rows) && $rows !== []) {
            $id = (int) ($rows[0]['id'] ?? 0);
            if ($id > 0) {
                self::$steamExternalGameSourceId = $id;

                return $id;
            }
        }

        $rows = $this->queryEndpoint('external_game_sources', 'fields id, name; limit 50;');
        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtolower(trim((string) ($row['name'] ?? ''))) !== 'steam') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                self::$steamExternalGameSourceId = $id;

                return $id;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function parseExternalGameUidMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $uidRaw = trim((string) ($row['uid'] ?? ''));
            if ($uidRaw === '' || !preg_match('/^\d+$/', $uidRaw)) {
                continue;
            }

            $appid = (int) $uidRaw;
            $gameRef = $row['game'] ?? 0;
            $igdbId = is_array($gameRef)
                ? (int) ($gameRef['id'] ?? 0)
                : (int) $gameRef;
            if ($appid > 0 && $igdbId > 0) {
                $map[$appid] = $igdbId;
            }
        }

        return $map;
    }

    /** @internal Tests PHPUnit */
    public static function resetSteamExternalGameSourceCacheForTests(): void
    {
        self::$steamExternalGameSourceId = null;
        self::$steamExternalGameSourceLookupDone = false;
    }

    public function testConnection(): array
    {
        $credentials = $this->resolveCredentials();
        if ($credentials === null) {
            return ['ok' => false, 'message' => 'Identifiants IGDB manquants.'];
        }

        $sample = $this->searchGame('The Witcher 3');
        if ($sample === null) {
            return [
                'ok' => false,
                'message' => $this->lastError ?? 'Impossible de contacter IGDB.',
            ];
        }

        $name = (string) (GameTitle::displayTitle($sample));
        $year = (int) ($sample['annee'] ?? 0);
        $snippet = $name !== '' ? $name . ($year > 0 ? ' (' . $year . ')' : '') : '(exemple vide)';

        return [
            'ok' => true,
            'message' => 'Connexion OK — exemple : ' . $snippet,
        ];
    }

    public static function parseIdFromInput(string $input): ?int
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $input)) {
            $id = (int) $input;

            return $id > 0 ? $id : null;
        }

        if (preg_match('#igdb\.com/games/[^/?#]*-(\d+)#i', $input, $m)) {
            return (int) $m[1];
        }

        if (preg_match('#/games/(\d+)#', $input, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function publicUrl(int $igdbId): string
    {
        return 'https://www.igdb.com/games/' . max(0, $igdbId);
    }

    public static function coverUrlFromImageId(string $imageId): string
    {
        $imageId = trim($imageId);
        if ($imageId === '') {
            return '';
        }

        return self::IMAGE_BASE . rawurlencode($imageId) . '.jpg';
    }

    public static function yearFromTimestamp(mixed $timestamp): int
    {
        $ts = (int) $timestamp;
        if ($ts <= 0) {
            return 0;
        }

        $year = (int) gmdate('Y', $ts);

        return ($year >= 1970 && $year <= 2100) ? $year : 0;
    }

    /** @return list<array<string, mixed>>|null */
    private function queryEndpoint(string $endpoint, string $body): ?array
    {
        $token = $this->fetchAccessToken();
        if ($token === null) {
            return null;
        }

        $credentials = $this->resolveCredentials();
        if ($credentials === null) {
            $this->lastError = 'Identifiants IGDB manquants.';

            return null;
        }

        $result = $this->httpPost(
            self::API_BASE . ltrim($endpoint, '/'),
            $body,
            [
                'Client-ID: ' . $credentials['client_id'],
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
                'Content-Type: text/plain',
            ]
        );
        if ($result === null) {
            return null;
        }

        if ($result['code'] >= 400) {
            $this->lastError = 'IGDB HTTP ' . $result['code'] . '.';

            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $this->lastError = 'Réponse IGDB illisible.';

            return null;
        }

        return $data;
    }

    private function fetchAccessToken(): ?string
    {
        $now = time();
        if (self::$tokenCache !== null && self::$tokenCache['expires_at'] > $now + 60) {
            return self::$tokenCache['token'];
        }

        $credentials = $this->resolveCredentials();
        if ($credentials === null) {
            $this->lastError = 'Identifiants IGDB manquants.';

            return null;
        }

        $url = self::TOKEN_URL . '?' . http_build_query([
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'grant_type' => 'client_credentials',
        ], '', '&', PHP_QUERY_RFC1738);

        $result = $this->httpPost($url, '', ['Accept: application/json']);
        if ($result === null) {
            return null;
        }

        if ($result['code'] >= 400) {
            $this->lastError = 'Authentification Twitch HTTP ' . $result['code'] . '.';

            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['access_token'])) {
            $this->lastError = 'Token Twitch illisible.';

            return null;
        }

        $token = trim((string) $data['access_token']);
        if ($token === '') {
            $this->lastError = 'Token Twitch vide.';

            return null;
        }

        $expiresIn = max(300, (int) ($data['expires_in'] ?? 3600));
        self::$tokenCache = [
            'token' => $token,
            'expires_at' => $now + $expiresIn,
        ];

        return $token;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function pickBestMatch(array $rows, ?int $year): ?array
    {
        $candidates = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeGameRow($row);
            if ($normalized === null) {
                continue;
            }
            $candidates[] = $normalized;
        }

        if ($candidates === []) {
            $this->lastError = 'Aucun jeu trouvé sur IGDB pour ce titre.';

            return null;
        }

        if ($year !== null && $year > 0) {
            foreach ($candidates as $candidate) {
                $candidateYear = (int) ($candidate['annee'] ?? 0);
                if ($candidateYear > 0 && abs($candidateYear - $year) <= 1) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normalizeGameRow(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            return null;
        }

        $cover = is_array($row['cover'] ?? null) ? $row['cover'] : [];
        $imageId = trim((string) ($cover['image_id'] ?? ''));

        $genres = [];
        foreach ((array) ($row['genres'] ?? []) as $genreRow) {
            if (!is_array($genreRow)) {
                continue;
            }
            $genreName = trim((string) ($genreRow['name'] ?? ''));
            if ($genreName !== '') {
                $genres[] = $genreName;
            }
        }

        $studio = '';
        $editeur = '';
        foreach ((array) ($row['involved_companies'] ?? []) as $companyRow) {
            if (!is_array($companyRow)) {
                continue;
            }
            $company = is_array($companyRow['company'] ?? null) ? $companyRow['company'] : [];
            $companyName = trim((string) ($company['name'] ?? ''));
            if ($companyName === '') {
                continue;
            }
            if ($studio === '' && !empty($companyRow['developer'])) {
                $studio = $companyName;
            }
            if ($editeur === '' && !empty($companyRow['publisher'])) {
                $editeur = $companyName;
            }
        }

        $franchises = [];
        foreach ((array) ($row['franchises'] ?? []) as $franchiseRow) {
            if (!is_array($franchiseRow)) {
                continue;
            }
            $franchiseName = trim((string) ($franchiseRow['name'] ?? ''));
            if ($franchiseName !== '') {
                $franchises[] = $franchiseName;
            }
        }

        $gameModes = [];
        foreach ((array) ($row['game_modes'] ?? []) as $modeRow) {
            if (!is_array($modeRow)) {
                continue;
            }
            $modeName = trim((string) ($modeRow['name'] ?? ''));
            if ($modeName !== '') {
                $gameModes[] = $modeName;
            }
        }

        $themes = [];
        foreach ((array) ($row['themes'] ?? []) as $themeRow) {
            if (!is_array($themeRow)) {
                continue;
            }
            $themeName = trim((string) ($themeRow['name'] ?? ''));
            if ($themeName !== '') {
                $themes[] = $themeName;
            }
        }

        $alternativeNames = [];
        foreach ((array) ($row['alternative_names'] ?? []) as $altRow) {
            if (is_array($altRow)) {
                $altName = trim((string) ($altRow['name'] ?? ''));
            } else {
                $altName = trim((string) $altRow);
            }
            if ($altName !== '') {
                $alternativeNames[] = $altName;
            }
        }

        return [
            'igdb_id' => $id,
            'titre_original' => $name,
            'titre' => $this->fetchFrenchTitle($id),
            'annee' => self::yearFromTimestamp($row['first_release_date'] ?? 0),
            'poster_url' => self::coverUrlFromImageId($imageId),
            'studio' => $studio,
            'editeur' => $editeur,
            'genre' => IgdbGenreMap::translateList($genres),
            'franchise' => self::formatFranchise($franchises),
            'game_mode' => IgdbGameModeMap::translateList($gameModes),
            'theme' => IgdbThemeMap::translateList($themes),
            'alternative_names' => IgdbAlternativeNameFilter::serializeAcronyms($alternativeNames, $name),
        ];
    }

    /**
     * @param list<string> $franchises
     */
    private static function formatFranchise(array $franchises): string
    {
        if ($franchises === []) {
            return '';
        }

        return $franchises[0];
    }

    private function fetchFrenchTitle(int $gameId): string
    {
        if ($gameId <= 0) {
            return '';
        }

        $body = 'fields name, language.locale; where game = ' . $gameId . '; limit 20;';
        $rows = $this->queryEndpoint('game_localizations', $body);
        if ($rows === null) {
            return '';
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $language = is_array($row['language'] ?? null) ? $row['language'] : [];
            $locale = strtolower(trim((string) ($language['locale'] ?? '')));
            if ($locale === '' || !str_starts_with($locale, 'fr')) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /** @return array{client_id: string, client_secret: string}|null */
    private function resolveCredentials(): ?array
    {
        if ($this->credentials !== null) {
            $clientId = IgdbConfig::sanitize((string) ($this->credentials['client_id'] ?? ''));
            $clientSecret = IgdbConfig::sanitize((string) ($this->credentials['client_secret'] ?? ''));
            if ($clientId !== '' && $clientSecret !== '') {
                return ['client_id' => $clientId, 'client_secret' => $clientSecret];
            }
        }

        return IgdbConfig::getCredentials();
    }

    /**
     * @param list<string> $headers
     * @return array{body: string, code: int}|null
     */
    private function httpPost(string $url, string $body, array $headers = []): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $responseBody = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($responseBody === false) {
                $this->lastError = 'Erreur réseau vers IGDB / Twitch.';

                return null;
            }
            $responseBody = (string) $responseBody;
            if (strlen($responseBody) > self::MAX_BODY_BYTES) {
                $this->lastError = 'Réponse IGDB trop volumineuse.';

                return null;
            }

            return ['body' => $responseBody, 'code' => $code];
        }

        $headerLines = implode("\r\n", $headers);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => self::HTTP_TIMEOUT,
                'header' => $headerLines . "\r\n",
                'content' => $body,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $ctx);
        if ($responseBody === false) {
            $this->lastError = 'Impossible d\'accéder à IGDB (allow_url_fopen ?).';

            return null;
        }

        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }

        return ['body' => (string) $responseBody, 'code' => $code];
    }

    /** @internal Tests PHPUnit */
    public static function resetTokenCacheForTests(): void
    {
        self::$tokenCache = null;
    }
}
