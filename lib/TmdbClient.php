<?php
/**
 * Client HTTP TMDB v3 — synopsis et affiches en français (fr-FR).
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbClient
{
    private const API_BASE = 'https://api.themoviedb.org/3/';
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p/w500';
    private const LANG = 'fr-FR';
    /** Délai réseau (secondes) — les fiches TV avec énorme casting peuvent être lentes. */
    private const HTTP_TIMEOUT = 25;
    /** Taille max. d’une réponse « credits » (certaines séries longues dépassent 1 Mo). */
    private const CREDITS_MAX_BYTES = 1_500_000;

    private ?string $lastError = null;

    public function __construct(
        private readonly ?string $apiKey = null
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Charge un film par son identifiant TMDB (correction manuelle).
     *
     * @return array{
     *   tmdb_id: int,
     *   overview: string,
     *   poster_url: string,
     *   runtime: int,
     *   annee: int,
     *   imdb_id: string
     * }|null
     */
    public function getMovieById(int $tmdbId): ?array
    {
        $this->lastError = null;
        if ($tmdbId <= 0) {
            $this->lastError = 'Identifiant TMDB invalide.';
            return null;
        }

        $data = $this->request('movie/' . $tmdbId, [
            'append_to_response' => 'external_ids',
        ]);
        if ($data === null || !isset($data['id'])) {
            $this->lastError = 'Film TMDB #' . $tmdbId . ' introuvable.';
            return null;
        }

        $people = $this->loadCreditsPeople(TmdbMediaType::MOVIE, $tmdbId);

        return self::buildMovieRecord(
            (int) $data['id'],
            trim((string) ($data['overview'] ?? '')),
            self::posterUrl((string) ($data['poster_path'] ?? '')),
            max(0, (int) ($data['runtime'] ?? 0)),
            self::yearFromDate((string) ($data['release_date'] ?? '')),
            $people,
            TmdbMediaType::MOVIE,
            TmdbTvKind::classifyMovieDetail($data),
            self::extractOriginalTitle($data, TmdbMediaType::MOVIE),
            TmdbCountries::nationaliteFromDetail($data),
            TmdbGenres::stylesFromDetail($data),
            self::extractLocalizedTitle($data, TmdbMediaType::MOVIE),
        );
    }

    /**
     * Charge une série TV par identifiant TMDB (correction manuelle).
     *
     * @return array<string, mixed>|null
     */
    public function getTvById(int $tmdbId): ?array
    {
        $this->lastError = null;
        if ($tmdbId <= 0) {
            $this->lastError = 'Identifiant TMDB invalide.';
            return null;
        }

        $data = $this->request('tv/' . $tmdbId);
        if ($data === null || !isset($data['id'])) {
            $this->lastError = 'Émission / série TMDB #' . $tmdbId . ' introuvable.';
            return null;
        }

        $people = $this->loadCreditsPeople(TmdbMediaType::TV, $tmdbId);
        $people = self::mergeTvCreator($people, $data);

        return self::buildMovieRecord(
            (int) $data['id'],
            trim((string) ($data['overview'] ?? '')),
            self::posterUrl((string) ($data['poster_path'] ?? '')),
            self::averageEpisodeRuntime($data['episode_run_time'] ?? null),
            self::yearFromDate((string) ($data['first_air_date'] ?? '')),
            $people,
            TmdbMediaType::TV,
            TmdbTvKind::classifyTvDetail($data),
            self::extractOriginalTitle($data, TmdbMediaType::TV),
            TmdbCountries::nationaliteFromDetail($data),
            TmdbGenres::stylesFromDetail($data),
            self::extractLocalizedTitle($data, TmdbMediaType::TV),
        );
    }

    /**
     * Film ou série selon l’URL / le type indiqué ; sinon essaie film puis série.
     *
     * @return array<string, mixed>|null
     */
    public function resolveById(int $tmdbId, ?string $preferredType = null): ?array
    {
        $this->lastError = null;
        if ($tmdbId <= 0) {
            $this->lastError = 'Identifiant TMDB invalide.';
            return null;
        }

        $type = TmdbMediaType::normalize($preferredType ?? '');

        if ($type === TmdbMediaType::TV) {
            return $this->getTvById($tmdbId);
        }
        if ($type === TmdbMediaType::MOVIE) {
            return $this->getMovieById($tmdbId);
        }

        $movie = $this->getMovieById($tmdbId);
        $movieError = $this->lastError;
        $this->lastError = null;
        $tv = $this->getTvById($tmdbId);
        $tvError = $this->lastError;
        $this->lastError = null;

        if ($movie !== null && $tv !== null) {
            $this->lastError = 'L’identifiant TMDB #' . $tmdbId
                . ' existe à la fois comme film et comme série. Indiquez /tv/' . $tmdbId
                . ' ou /movie/' . $tmdbId . ' (ou la catégorie « Série » sur la fiche).';

            return null;
        }

        if ($movie !== null) {
            return $movie;
        }
        if ($tv !== null) {
            return $tv;
        }

        $this->lastError = $movieError ?? $tvError ?? 'Contenu TMDB #' . $tmdbId . ' introuvable (film ou série).';
        return null;
    }

    /**
     * @return array{id: int, type: string}|null type = movie|tv|'' (inconnu)
     */
    public static function normalizeTmdbReference(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('#themoviedb\.org/(movie|tv)/(\d+)#i', $raw, $m)) {
            $id = (int) $m[2];
            return $id > 0 ? ['id' => $id, 'type' => strtolower($m[1])] : null;
        }
        if (preg_match('#/(movie|tv)/(\d+)#i', $raw, $m)) {
            $id = (int) $m[2];
            return $id > 0 ? ['id' => $id, 'type' => strtolower($m[1])] : null;
        }
        if (preg_match('/^(\d+)$/', $raw, $m)) {
            $id = (int) $m[1];
            return $id > 0 ? ['id' => $id, 'type' => ''] : null;
        }
        return null;
    }

    public static function normalizeTmdbId(string $raw): ?int
    {
        $ref = self::normalizeTmdbReference($raw);
        return $ref !== null ? $ref['id'] : null;
    }

    /**
     * @return array{overview: string, poster_url: string, runtime: int}|null
     */
    public function searchMovie(string $title): ?array
    {
        $this->lastError = null;
        $title = trim($title);
        if ($title === '') {
            $this->lastError = 'Titre vide.';
            return null;
        }

        $data = $this->request('search/movie', [
            'query' => $title,
            'include_adult' => 'false',
        ]);
        if ($data === null) {
            return null;
        }

        $results = $data['results'] ?? [];
        if (!is_array($results) || $results === []) {
            $this->lastError = 'Aucun résultat TMDB pour « ' . $title . ' ».';
            return null;
        }

        return $this->parseResult($results[0], TmdbMediaType::MOVIE);
    }

    /**
     * Recherche une série TV par titre.
     *
     * @return array<string, mixed>|null
     */
    public function searchTv(string $title): ?array
    {
        $this->lastError = null;
        $title = trim($title);
        if ($title === '') {
            $this->lastError = 'Titre vide.';
            return null;
        }

        $data = $this->request('search/tv', [
            'query' => $title,
            'include_adult' => 'false',
        ]);
        if ($data === null) {
            return null;
        }

        $results = $data['results'] ?? [];
        if (!is_array($results) || $results === []) {
            $this->lastError = 'Aucune émission / série TMDB pour « ' . $title . ' ».';
            return null;
        }

        return $this->parseResult($results[0], TmdbMediaType::TV);
    }

    /**
     * Recherche combinée film + TV (documentaires, émissions, séries).
     *
     * @return array<string, mixed>|null
     */
    public function searchByTitle(string $title): ?array
    {
        $this->lastError = null;
        $title = trim($title);
        if ($title === '') {
            $this->lastError = 'Titre vide.';
            return null;
        }

        $multi = $this->searchMultiBest($title);
        if ($multi !== null && self::recordHasUsefulData($multi)) {
            return $multi;
        }

        $multiError = $this->lastError;
        $this->lastError = null;

        $movie = $this->searchMovie($title);
        if ($movie !== null && self::recordHasUsefulData($movie)) {
            return $movie;
        }

        $movieError = $this->lastError;
        $this->lastError = null;
        $tv = $this->searchTv($title);
        if ($tv !== null) {
            return $tv;
        }

        if ($multi !== null) {
            return $multi;
        }
        if ($movie !== null) {
            return $movie;
        }

        if ($this->lastError === null && $movieError !== null) {
            $this->lastError = $movieError;
        }
        if ($this->lastError === null && $multiError !== null) {
            $this->lastError = $multiError;
        }
        if ($this->lastError === null) {
            $this->lastError = 'Aucun film, documentaire ou émission TMDB pour « ' . $title . ' ».';
        }

        return null;
    }

    /**
     * Recherche globale TMDB (films + émissions / séries TV).
     *
     * @return array<string, mixed>|null
     */
    private function searchMultiBest(string $title): ?array
    {
        $data = $this->request('search/multi', [
            'query' => $title,
            'include_adult' => 'false',
        ]);
        if ($data === null) {
            return null;
        }

        $results = $data['results'] ?? [];
        if (!is_array($results) || $results === []) {
            $this->lastError = 'Aucun résultat TMDB pour « ' . $title . ' ».';
            return null;
        }

        $candidates = [];
        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mediaType = (string) ($item['media_type'] ?? '');
            if ($mediaType === 'movie' || $mediaType === 'tv') {
                $candidates[] = $item;
            }
        }

        if ($candidates === []) {
            $this->lastError = 'Aucun film ni émission TMDB pour « ' . $title . ' ».';
            return null;
        }

        $best = self::pickBestTitleMatch($candidates, $title);
        $apiType = (string) ($best['media_type'] ?? '') === 'tv'
            ? TmdbMediaType::TV
            : TmdbMediaType::MOVIE;

        return $this->parseResult($best, $apiType);
    }

    /**
     * Choisit le résultat dont le titre correspond le mieux (puis popularité TMDB).
     *
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private static function pickBestTitleMatch(array $items, string $query): array
    {
        $queryNorm = self::normalizeTitleForMatch($query);
        $best = $items[0];
        $bestScore = -1.0;

        foreach ($items as $item) {
            $name = trim((string) ($item['title'] ?? $item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $nameNorm = self::normalizeTitleForMatch($name);
            $score = 0.0;
            if ($nameNorm === $queryNorm) {
                $score = 1000.0;
            } elseif (str_starts_with($nameNorm, $queryNorm) || str_starts_with($queryNorm, $nameNorm)) {
                $score = 500.0;
            } else {
                similar_text($queryNorm, $nameNorm, $percent);
                $score = (float) $percent;
            }
            $score += min(100.0, (float) ($item['popularity'] ?? 0)) / 10.0;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return $best;
    }

    private static function normalizeTitleForMatch(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);
        $title = (string) preg_replace('/\s+/u', ' ', $title);

        return $title;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function recordHasUsefulData(array $record): bool
    {
        return trim((string) ($record['overview'] ?? '')) !== ''
            || trim((string) ($record['poster_url'] ?? '')) !== ''
            || (int) ($record['runtime'] ?? 0) > 0
            || (int) ($record['annee'] ?? 0) > 0
            || trim((string) ($record['director'] ?? '')) !== ''
            || trim((string) ($record['acteur_1'] ?? '')) !== ''
            || trim((string) ($record['acteur_2'] ?? '')) !== ''
            || trim((string) ($record['acteur_3'] ?? '')) !== '';
    }

    /** @return array{ok: bool, message: string} */
    public function testConnection(): array
    {
        $key = TmdbConfig::sanitizeKey($this->apiKey ?? TmdbConfig::getApiKey() ?? '');
        if ($key === '') {
            return ['ok' => false, 'message' => 'Aucune clé API TMDB enregistrée.'];
        }

        $sample = $this->searchMovie('Blade Runner');
        if ($sample === null) {
            return [
                'ok' => false,
                'message' => $this->lastError ?? 'Impossible de contacter TMDB.',
            ];
        }

        $snippet = $sample['overview'] !== ''
            ? mb_substr($sample['overview'], 0, 60) . '…'
            : '(synopsis vide pour cet exemple)';

        return [
            'ok' => true,
            'message' => 'Connexion OK — exemple Blade Runner (FR) : ' . $snippet,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *   tmdb_id: int,
     *   overview: string,
     *   poster_url: string,
     *   runtime: int,
     *   annee: int,
     *   imdb_id: string,
     *   director: string,
     *   acteur_1: string,
     *   acteur_2: string,
     *   acteur_3: string
     * }
     */
    private function parseResult(array $item, string $type): array
    {
        $tmdbId = isset($item['id']) ? (int) $item['id'] : 0;

        if ($type === TmdbMediaType::MOVIE && $tmdbId > 0) {
            $full = $this->getMovieById($tmdbId);
            if ($full !== null) {
                return $full;
            }
        }

        if ($type === TmdbMediaType::TV && $tmdbId > 0) {
            $full = $this->getTvById($tmdbId);
            if ($full !== null) {
                return $full;
            }
        }

        $overview = trim((string) ($item['overview'] ?? ''));
        $poster = self::posterUrl((string) ($item['poster_path'] ?? ''));
        $annee = self::yearFromDate((string) ($item['release_date'] ?? $item['first_air_date'] ?? ''));
        $people = self::parseCredits(null);

        $tvKind = $type === TmdbMediaType::TV ? TmdbTvKind::SERIES : '';
        $originalTitle = self::extractOriginalTitle($item, $type);

        $nationalite = TmdbCountries::nationaliteFromDetail($item);

        return self::buildMovieRecord(
            $tmdbId,
            $overview,
            $poster,
            0,
            $annee,
            $people,
            $type,
            $tvKind,
            $originalTitle,
            $nationalite,
            TmdbGenres::stylesFromDetail($item),
            self::extractLocalizedTitle($item, $type),
        );
    }

    /**
     * @param array<string, mixed>|null $credits
     * @return array{
     *   director: string,
     *   director_tmdb_id: int,
     *   acteur_1: string,
     *   acteur_1_tmdb_id: int,
     *   acteur_2: string,
     *   acteur_2_tmdb_id: int,
     *   acteur_3: string,
     *   acteur_3_tmdb_id: int
     * }
     */
    public static function parseCredits(?array $credits): array
    {
        $empty = [
            'director' => '',
            'director_tmdb_id' => 0,
            'acteur_1' => '',
            'acteur_1_tmdb_id' => 0,
            'acteur_2' => '',
            'acteur_2_tmdb_id' => 0,
            'acteur_3' => '',
            'acteur_3_tmdb_id' => 0,
        ];

        if (!is_array($credits)) {
            return $empty;
        }

        $director = '';
        $directorId = 0;

        foreach ($credits['crew'] ?? [] as $person) {
            if (!is_array($person)) {
                continue;
            }
            $job = (string) ($person['job'] ?? '');
            if (strcasecmp($job, 'Director') === 0 || strcasecmp($job, 'Réalisateur') === 0) {
                $director = trim((string) ($person['name'] ?? ''));
                $directorId = max(0, (int) ($person['id'] ?? 0));
                if ($director !== '') {
                    break;
                }
            }
        }

        $actors = ['', '', ''];
        $actorIds = [0, 0, 0];
        $cast = $credits['cast'] ?? [];
        if (is_array($cast)) {
            $best = [];
            foreach ($cast as $person) {
                if (!is_array($person)) {
                    continue;
                }
                $name = trim((string) ($person['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $order = (int) ($person['order'] ?? 999);
                $best[] = ['order' => $order, 'name' => $name, 'id' => max(0, (int) ($person['id'] ?? 0))];
            }
            usort(
                $best,
                static fn (array $a, array $b): int => $a['order'] <=> $b['order']
            );
            $index = 0;
            foreach ($best as $person) {
                if ($index >= 3) {
                    break;
                }
                $actors[$index] = $person['name'];
                $actorIds[$index] = $person['id'];
                $index++;
            }
        }

        return [
            'director' => $director,
            'director_tmdb_id' => $directorId,
            'acteur_1' => $actors[0],
            'acteur_1_tmdb_id' => $actorIds[0],
            'acteur_2' => $actors[1],
            'acteur_2_tmdb_id' => $actorIds[1],
            'acteur_3' => $actors[2],
            'acteur_3_tmdb_id' => $actorIds[2],
        ];
    }

    /**
     * @param array{
     *   director: string,
     *   director_tmdb_id: int,
     *   acteur_1: string,
     *   acteur_1_tmdb_id: int,
     *   acteur_2: string,
     *   acteur_2_tmdb_id: int,
     *   acteur_3: string,
     *   acteur_3_tmdb_id: int
     * } $people
     * @return array{
     *   tmdb_id: int,
     *   overview: string,
     *   poster_url: string,
     *   runtime: int,
     *   annee: int,
     *   imdb_id: string,
     *   director: string,
     *   director_tmdb_id: int,
     *   acteur_1: string,
     *   acteur_1_tmdb_id: int,
     *   acteur_2: string,
     *   acteur_2_tmdb_id: int,
     *   acteur_3: string,
     *   acteur_3_tmdb_id: int
     * }
     */
    /**
     * @param array<string, mixed>|null $episodeRunTime
     */
    private static function averageEpisodeRuntime(mixed $episodeRunTime): int
    {
        if (!is_array($episodeRunTime) || $episodeRunTime === []) {
            return 0;
        }
        $values = array_filter(array_map(static fn ($v): int => max(0, (int) $v), $episodeRunTime));
        if ($values === []) {
            return 0;
        }
        return (int) round(array_sum($values) / count($values));
    }

    /**
     * @param array<string, mixed> $people
     * @param array<string, mixed> $tvData
     * @return array<string, mixed>
     */
    private static function mergeTvCreator(array $people, array $tvData): array
    {
        $createdBy = $tvData['created_by'] ?? [];
        if (!is_array($createdBy) || $createdBy === []) {
            return $people;
        }
        $first = $createdBy[0] ?? null;
        if (!is_array($first)) {
            return $people;
        }
        $name = trim((string) ($first['name'] ?? ''));
        if ($name === '') {
            return $people;
        }
        $people['director'] = $name;
        $people['director_tmdb_id'] = max(0, (int) ($first['id'] ?? 0));
        return $people;
    }

    /**
     * Titre affiché TMDB en français (fr-FR) : title pour un film, name pour une série.
     *
     * @param array<string, mixed> $data
     */
    public static function extractLocalizedTitle(array $data, string $mediaType): string
    {
        $isTv = TmdbMediaType::normalize($mediaType) === TmdbMediaType::TV;

        return trim((string) ($isTv ? ($data['name'] ?? '') : ($data['title'] ?? '')));
    }

    /**
     * Titre original TMDB s’il diffère du titre localisé (fr-FR).
     *
     * @param array<string, mixed> $data
     */
    public static function extractOriginalTitle(array $data, string $mediaType): string
    {
        $isTv = TmdbMediaType::normalize($mediaType) === TmdbMediaType::TV;
        $localized = trim((string) ($isTv ? ($data['name'] ?? '') : ($data['title'] ?? '')));
        $original = trim((string) ($isTv ? ($data['original_name'] ?? '') : ($data['original_title'] ?? '')));

        if ($original === '') {
            return '';
        }
        if ($localized !== '' && mb_strtolower($original) === mb_strtolower($localized)) {
            return '';
        }

        return $original;
    }

    private static function buildMovieRecord(
        int $tmdbId,
        string $overview,
        string $posterUrl,
        int $runtime,
        int $annee,
        array $people,
        string $mediaType = TmdbMediaType::MOVIE,
        string $tvKind = '',
        string $originalTitle = '',
        string $nationalite = '',
        string $styles = '',
        string $localizedTitle = ''
    ): array {
        $normalizedType = TmdbMediaType::normalize($mediaType) !== ''
            ? TmdbMediaType::normalize($mediaType)
            : TmdbMediaType::MOVIE;
        $normalizedKind = TmdbTvKind::normalize($tvKind);
        if ($normalizedType !== TmdbMediaType::TV && !TmdbTvKind::isMovieMetadata($normalizedKind)) {
            $normalizedKind = '';
        }

        return [
            'tmdb_id' => $tmdbId,
            'media_type' => $normalizedType,
            'tv_kind' => $normalizedKind,
            'original_title' => trim($originalTitle),
            'localized_title' => trim($localizedTitle),
            'nationalite' => trim($nationalite),
            'overview' => $overview,
            'poster_url' => $posterUrl,
            'runtime' => $runtime,
            'annee' => $annee,
            'director' => $people['director'],
            'director_tmdb_id' => $people['director_tmdb_id'],
            'acteur_1' => $people['acteur_1'],
            'acteur_1_tmdb_id' => $people['acteur_1_tmdb_id'],
            'acteur_2' => $people['acteur_2'],
            'acteur_2_tmdb_id' => $people['acteur_2_tmdb_id'],
            'acteur_3' => $people['acteur_3'],
            'acteur_3_tmdb_id' => $people['acteur_3_tmdb_id'],
            'styles' => trim($styles),
        ];
    }

    public static function yearFromDate(string $date): int
    {
        if (preg_match('/^(\d{4})/', trim($date), $m)) {
            $year = (int) $m[1];
            return ($year >= 1888 && $year <= 2100) ? $year : 0;
        }
        return 0;
    }

    private static function posterUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === 'null') {
            return '';
        }
        return self::IMAGE_BASE . $path;
    }

    /**
     * Charge réalisateur / acteurs via l’endpoint credits (réponse séparée, taille plafonnée).
     *
     * @return array{
     *   director: string,
     *   director_tmdb_id: int,
     *   acteur_1: string,
     *   acteur_1_tmdb_id: int,
     *   acteur_2: string,
     *   acteur_2_tmdb_id: int,
     *   acteur_3: string,
     *   acteur_3_tmdb_id: int
     * }
     */
    private function loadCreditsPeople(string $mediaType, int $tmdbId): array
    {
        $savedError = $this->lastError;
        $this->lastError = null;

        $segment = $mediaType === TmdbMediaType::TV ? 'tv' : 'movie';
        $data = $this->request($segment . '/' . $tmdbId . '/credits', [], self::CREDITS_MAX_BYTES);
        $this->lastError = $savedError;

        if ($data === null) {
            return self::parseCredits(null);
        }

        return self::parseCredits($data);
    }

    /** @return array<string, mixed>|null */
    private function request(string $endpoint, array $params = [], ?int $maxBodyBytes = null): ?array
    {
        $key = TmdbConfig::sanitizeKey($this->apiKey ?? TmdbConfig::getApiKey() ?? '');
        if ($key === '') {
            $this->lastError = 'Clé API TMDB manquante.';
            return null;
        }

        $params['api_key'] = $key;
        $params['language'] = self::LANG;

        $url = self::API_BASE . ltrim($endpoint, '/') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC1738);
        $result = $this->httpGet($url, $maxBodyBytes);
        if ($result === null) {
            return null;
        }

        if ($result['code'] >= 400) {
            $this->lastError = 'TMDB HTTP ' . $result['code'] . '.';
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $this->lastError = 'Réponse TMDB illisible.';
            return null;
        }

        if (isset($data['status_code']) && (int) $data['status_code'] >= 400) {
            $this->lastError = (string) ($data['status_message'] ?? 'Erreur TMDB.');
            return null;
        }

        return $data;
    }

    /** @return array{body: string, code: int}|null */
    private function httpGet(string $url, ?int $maxBodyBytes = null): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ];
            if ($maxBodyBytes !== null && $maxBodyBytes > 0 && defined('CURLOPT_MAXFILESIZE')) {
                $options[CURLOPT_MAXFILESIZE] = $maxBodyBytes;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_errno($ch);
            curl_close($ch);
            if ($body === false) {
                if ($curlErr === CURLE_FILESIZE_EXCEEDED) {
                    $this->lastError = 'Réponse TMDB trop volumineuse (casting très long). Synopsis et affiche seront utilisés sans acteurs.';
                } else {
                    $this->lastError = 'Erreur réseau vers api.themoviedb.org.';
                }
                return null;
            }
            $body = (string) $body;
            if ($maxBodyBytes !== null && strlen($body) > $maxBodyBytes) {
                $this->lastError = 'Réponse TMDB trop volumineuse.';
                return null;
            }

            return ['body' => $body, 'code' => $code];
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::HTTP_TIMEOUT,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            $this->lastError = 'Impossible d\'accéder à api.themoviedb.org (allow_url_fopen ?).';
            return null;
        }
        if ($maxBodyBytes !== null && strlen($body) > $maxBodyBytes) {
            $this->lastError = 'Réponse TMDB trop volumineuse.';
            return null;
        }
        $code = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        return ['body' => $body, 'code' => $code];
    }
}
