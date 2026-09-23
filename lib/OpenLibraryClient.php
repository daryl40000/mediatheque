<?php
/**
 * Client HTTP Open Library (sans clé API).
 *
 * Doc : https://openlibrary.org/developers/api
 * Swagger : https://openlibrary.org/swagger/docs
 *
 * Endpoints utilisés :
 * - Books API (ISBN / OLID) : /api/books?bibkeys=…&format=json&jscmd=data
 * - Search API : /search.json
 * - Édition / œuvre JSON : /books/OLxxxM.json , /works/OLxxxW.json
 * - Covers : https://covers.openlibrary.org/b/isbn/…-L.jpg
 */

declare(strict_types=1);

namespace Moncine;

final class OpenLibraryClient
{
    private const API_BASE = 'https://openlibrary.org';

    private const COVERS_BASE = 'https://covers.openlibrary.org';

    private const HTTP_TIMEOUT = 20;

    private const MAX_BODY_BYTES = 1_048_576;

    private const USER_AGENT = 'Mediatheque/0.8.18 (+https://github.com/daryl40000/mediatheque; OpenLibrary integration)';

    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Recherche par ISBN (10 ou 13, tirets ignorés).
     *
     * @return array<string, mixed>|null métadonnées normalisées
     */
    public function lookupByIsbn(string $isbn): ?array
    {
        $isbn = self::normalizeIsbn($isbn);
        if ($isbn === '') {
            $this->lastError = 'ISBN vide ou invalide.';

            return null;
        }

        $bibKey = 'ISBN:' . $isbn;
        $url = self::API_BASE . '/api/books?' . http_build_query([
            'bibkeys' => $bibKey,
            'format' => 'json',
            'jscmd' => 'data',
        ], '', '&', PHP_QUERY_RFC3986);

        $payload = $this->httpGetJson($url);
        if ($payload === null) {
            return null;
        }

        $row = $payload[$bibKey] ?? null;
        if (!is_array($row)) {
            $this->lastError = 'Aucun livre trouvé sur Open Library pour cet ISBN.';

            return null;
        }

        $meta = $this->normalizeBooksApiRow($row, $isbn);
        if ($meta === null) {
            return null;
        }

        return $this->enrichWithWorkDescription($meta);
    }

    /**
     * Recherche par titre (+ auteur optionnel).
     *
     * @return array<string, mixed>|null
     */
    public function searchByTitle(string $title, string $author = '', ?int $year = null): ?array
    {
        $title = trim($title);
        if ($title === '') {
            $this->lastError = 'Titre vide.';

            return null;
        }

        $params = [
            'title' => $title,
            'limit' => 8,
        ];
        $author = trim($author);
        if ($author !== '') {
            $params['author'] = $author;
        }

        $url = self::API_BASE . '/search.json?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $payload = $this->httpGetJson($url);
        if ($payload === null) {
            return null;
        }

        $docs = $payload['docs'] ?? null;
        if (!is_array($docs) || $docs === []) {
            $this->lastError = 'Aucun livre trouvé sur Open Library pour ce titre.';

            return null;
        }

        /** @var list<mixed> $docList */
        $docList = array_values($docs);
        $best = $this->pickBestSearchDoc($docList, $year);
        if ($best === null) {
            $this->lastError = 'Aucun livre trouvé sur Open Library pour ce titre.';

            return null;
        }

        // Préférer une édition précise (ISBN / cover_edition) pour pages, éditeur, couverture.
        $editionKey = trim((string) ($best['cover_edition_key'] ?? ''));
        $isbnList = $best['isbn'] ?? [];
        $isbn = '';
        if (is_array($isbnList)) {
            foreach ($isbnList as $candidate) {
                $normalized = self::normalizeIsbn((string) $candidate);
                if (strlen($normalized) === 13) {
                    $isbn = $normalized;
                    break;
                }
                if ($isbn === '' && $normalized !== '') {
                    $isbn = $normalized;
                }
            }
        }

        if ($isbn !== '') {
            $byIsbn = $this->lookupByIsbn($isbn);
            if ($byIsbn !== null) {
                return $byIsbn;
            }
        }

        if ($editionKey !== '') {
            $byEdition = $this->lookupByEditionKey($editionKey);
            if ($byEdition !== null) {
                return $byEdition;
            }
        }

        return $this->normalizeSearchDoc($best);
    }

    /**
     * Charge une édition par clé Open Library (OLxxxxM ou /books/OLxxxxM).
     *
     * @return array<string, mixed>|null
     */
    public function lookupByEditionKey(string $editionKey): ?array
    {
        $olid = self::normalizeEditionId($editionKey);
        if ($olid === '') {
            $this->lastError = 'Identifiant Open Library invalide.';

            return null;
        }

        $bibKey = 'OLID:' . $olid;
        $url = self::API_BASE . '/api/books?' . http_build_query([
            'bibkeys' => $bibKey,
            'format' => 'json',
            'jscmd' => 'data',
        ], '', '&', PHP_QUERY_RFC3986);

        $payload = $this->httpGetJson($url);
        if ($payload === null) {
            return null;
        }

        $row = $payload[$bibKey] ?? null;
        if (!is_array($row)) {
            // Repli : JSON édition brut.
            $edition = $this->httpGetJson(self::API_BASE . '/books/' . rawurlencode($olid) . '.json');
            if (!is_array($edition)) {
                $this->lastError = 'Édition Open Library introuvable.';

                return null;
            }

            $meta = $this->normalizeEditionJson($edition, $olid);
            if ($meta === null) {
                return null;
            }

            return $this->enrichWithWorkDescription($meta);
        }

        $meta = $this->normalizeBooksApiRow($row, '');
        if ($meta === null) {
            return null;
        }

        return $this->enrichWithWorkDescription($meta);
    }

    public static function normalizeIsbn(string $isbn): string
    {
        $isbn = strtoupper(trim($isbn));
        $isbn = preg_replace('/[\s\-]/', '', $isbn) ?? $isbn;
        if ($isbn === '' || !preg_match('/^\d{9}[\dX]$|^\d{13}$/', $isbn)) {
            return '';
        }

        return $isbn;
    }

    /** Accepte OLxxxxM, /books/OLxxxxM, ou URL openlibrary.org/books/… */
    public static function normalizeEditionId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#/(books|b)/([A-Z0-9]+)#i', $value, $m) === 1) {
            $value = $m[2];
        }

        $value = preg_replace('#^/books/#i', '', $value) ?? $value;
        $value = strtoupper(trim($value));

        return preg_match('/^OL\d+M$/i', $value) === 1 ? strtoupper($value) : '';
    }

    public static function publicEditionUrl(string $editionId): string
    {
        $olid = self::normalizeEditionId($editionId);
        if ($olid === '') {
            return 'https://openlibrary.org/';
        }

        return 'https://openlibrary.org/books/' . $olid;
    }

    public static function coverUrlForIsbn(string $isbn, string $size = 'L'): string
    {
        $isbn = self::normalizeIsbn($isbn);
        if ($isbn === '') {
            return '';
        }

        $size = in_array($size, ['S', 'M', 'L'], true) ? $size : 'L';

        return self::COVERS_BASE . '/b/isbn/' . rawurlencode($isbn) . '-' . $size . '.jpg?default=false';
    }

    public static function coverUrlForOlid(string $olid, string $size = 'L'): string
    {
        $olid = self::normalizeEditionId($olid);
        if ($olid === '') {
            return '';
        }

        $size = in_array($size, ['S', 'M', 'L'], true) ? $size : 'L';

        return self::COVERS_BASE . '/b/olid/' . rawurlencode($olid) . '-' . $size . '.jpg?default=false';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normalizeBooksApiRow(array $row, string $fallbackIsbn): ?array
    {
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $this->lastError = 'Réponse Open Library sans titre.';

            return null;
        }

        $authors = [];
        foreach ($row['authors'] ?? [] as $author) {
            if (!is_array($author)) {
                continue;
            }
            $name = trim((string) ($author['name'] ?? ''));
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $publishers = [];
        foreach ($row['publishers'] ?? [] as $publisher) {
            if (is_array($publisher)) {
                $name = trim((string) ($publisher['name'] ?? ''));
            } else {
                $name = trim((string) $publisher);
            }
            if ($name !== '') {
                $publishers[] = $name;
            }
        }

        $identifiers = is_array($row['identifiers'] ?? null) ? $row['identifiers'] : [];
        $isbn = $fallbackIsbn;
        if ($isbn === '') {
            foreach (['isbn_13', 'isbn_10'] as $key) {
                $list = $identifiers[$key] ?? null;
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $candidate) {
                    $normalized = self::normalizeIsbn((string) $candidate);
                    if ($normalized !== '') {
                        $isbn = $normalized;
                        break 2;
                    }
                }
            }
        }

        $olid = '';
        $olList = $identifiers['openlibrary'] ?? null;
        if (is_array($olList) && $olList !== []) {
            $olid = self::normalizeEditionId((string) $olList[0]);
        }
        if ($olid === '') {
            $key = (string) ($row['key'] ?? '');
            $olid = self::normalizeEditionId($key);
        }

        $coverUrl = '';
        $cover = $row['cover'] ?? null;
        if (is_array($cover)) {
            $coverUrl = trim((string) ($cover['large'] ?? $cover['medium'] ?? $cover['small'] ?? ''));
        }
        if ($coverUrl === '' && $isbn !== '') {
            $coverUrl = self::coverUrlForIsbn($isbn);
        }
        if ($coverUrl === '' && $olid !== '') {
            $coverUrl = self::coverUrlForOlid($olid);
        }

        $subjects = [];
        foreach ($row['subjects'] ?? [] as $subject) {
            if (is_array($subject)) {
                $name = trim((string) ($subject['name'] ?? ''));
            } else {
                $name = trim((string) $subject);
            }
            if ($name !== '' && count($subjects) < 8) {
                $subjects[] = $name;
            }
        }

        $workKey = '';
        // Books API data ne donne pas toujours works ; on le récupère via edition JSON si besoin.
        $publishDate = trim((string) ($row['publish_date'] ?? ''));
        $sousTitre = trim((string) ($row['subtitle'] ?? ''));

        return [
            'openlibrary_id' => $olid,
            'titre' => $title,
            'sous_titre' => $sousTitre,
            'auteur' => implode(', ', $authors),
            'isbn' => $isbn,
            'pages' => max(0, (int) ($row['number_of_pages'] ?? 0)),
            'editeur' => $publishers[0] ?? '',
            'annee' => self::extractYear($publishDate),
            'synopsis' => '',
            'saga' => '',
            'saga_ordre' => 0,
            'poster_url' => $coverUrl,
            'subjects' => $subjects,
            'work_key' => $workKey,
            'langue' => '',
        ];
    }

    /**
     * @param array<string, mixed> $edition
     * @return array<string, mixed>|null
     */
    private function normalizeEditionJson(array $edition, string $olid): ?array
    {
        $title = trim((string) ($edition['title'] ?? ''));
        if ($title === '') {
            $this->lastError = 'Édition Open Library sans titre.';

            return null;
        }

        $isbn = '';
        foreach (['isbn_13', 'isbn_10'] as $key) {
            $list = $edition[$key] ?? null;
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $candidate) {
                $normalized = self::normalizeIsbn((string) $candidate);
                if ($normalized !== '') {
                    $isbn = $normalized;
                    break 2;
                }
            }
        }

        $publishers = [];
        foreach ($edition['publishers'] ?? [] as $publisher) {
            $name = trim((string) $publisher);
            if ($name !== '') {
                $publishers[] = $name;
            }
        }

        $workKey = '';
        $works = $edition['works'] ?? null;
        if (is_array($works) && isset($works[0]['key'])) {
            $workKey = trim((string) $works[0]['key']);
        }

        $langue = '';
        $languages = $edition['languages'] ?? null;
        if (is_array($languages) && isset($languages[0]['key'])) {
            $langKey = (string) $languages[0]['key'];
            if (str_contains($langKey, '/fre')) {
                $langue = 'fr';
            } elseif (str_contains($langKey, '/eng')) {
                $langue = 'en';
            }
        }

        $coverUrl = $isbn !== '' ? self::coverUrlForIsbn($isbn) : self::coverUrlForOlid($olid);
        $sousTitre = trim((string) ($edition['subtitle'] ?? ''));
        $seriesParsed = self::parseSeriesFromEditionField($edition['series'] ?? null);

        return [
            'openlibrary_id' => $olid,
            'titre' => $title,
            'sous_titre' => $sousTitre,
            'auteur' => '',
            'isbn' => $isbn,
            'pages' => max(0, (int) ($edition['number_of_pages'] ?? 0)),
            'editeur' => $publishers[0] ?? '',
            'annee' => self::extractYear((string) ($edition['publish_date'] ?? '')),
            'synopsis' => '',
            'saga' => $seriesParsed['saga'],
            'saga_ordre' => $seriesParsed['saga_ordre'],
            'poster_url' => $coverUrl,
            'subjects' => [],
            'work_key' => $workKey,
            'langue' => $langue,
        ];
    }

    /**
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    private function normalizeSearchDoc(array $doc): array
    {
        $authors = [];
        foreach ($doc['author_name'] ?? [] as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $editionKey = self::normalizeEditionId((string) ($doc['cover_edition_key'] ?? ''));
        $isbnList = $doc['isbn'] ?? [];
        $isbn = '';
        if (is_array($isbnList)) {
            foreach ($isbnList as $candidate) {
                $normalized = self::normalizeIsbn((string) $candidate);
                if (strlen($normalized) === 13) {
                    $isbn = $normalized;
                    break;
                }
                if ($isbn === '' && $normalized !== '') {
                    $isbn = $normalized;
                }
            }
        }

        $coverUrl = '';
        if ($isbn !== '') {
            $coverUrl = self::coverUrlForIsbn($isbn);
        } elseif ($editionKey !== '') {
            $coverUrl = self::coverUrlForOlid($editionKey);
        } elseif ((int) ($doc['cover_i'] ?? 0) > 0) {
            $coverUrl = self::COVERS_BASE . '/b/id/' . (int) $doc['cover_i'] . '-L.jpg?default=false';
        }

        $seriesFromSearch = self::parseSeriesFromSearchDoc($doc);

        return [
            'openlibrary_id' => $editionKey,
            'titre' => trim((string) ($doc['title'] ?? '')),
            'sous_titre' => trim((string) ($doc['subtitle'] ?? '')),
            'auteur' => implode(', ', $authors),
            'isbn' => $isbn,
            'pages' => 0,
            'editeur' => '',
            'annee' => max(0, (int) ($doc['first_publish_year'] ?? 0)),
            'synopsis' => '',
            'saga' => $seriesFromSearch['saga'],
            'saga_ordre' => $seriesFromSearch['saga_ordre'],
            'poster_url' => $coverUrl,
            'subjects' => [],
            'work_key' => trim((string) ($doc['key'] ?? '')),
            'langue' => '',
        ];
    }

    /**
     * @param list<mixed> $docs
     * @return array<string, mixed>|null
     */
    private function pickBestSearchDoc(array $docs, ?int $year): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $title = trim((string) ($doc['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $score = (int) ($doc['edition_count'] ?? 0);
            $docYear = (int) ($doc['first_publish_year'] ?? 0);
            if ($year !== null && $year > 0 && $docYear > 0) {
                $score += max(0, 50 - abs($docYear - $year));
            }
            if ((int) ($doc['cover_i'] ?? 0) > 0) {
                $score += 10;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $doc;
            }
        }

        return $best;
    }

    /**
     * Complète résumé, sous-titre, saga et n° depuis l’édition / l’œuvre Open Library.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function enrichWithWorkDescription(array $meta): array
    {
        $workKey = trim((string) ($meta['work_key'] ?? ''));
        $olid = trim((string) ($meta['openlibrary_id'] ?? ''));

        if ($olid !== '') {
            $edition = $this->httpGetJson(self::API_BASE . '/books/' . rawurlencode($olid) . '.json');
            if (is_array($edition)) {
                $meta = $this->applyEditionExtras($meta, $edition);
                $works = $edition['works'] ?? null;
                if ($workKey === '' && is_array($works) && isset($works[0]['key'])) {
                    $workKey = trim((string) $works[0]['key']);
                }
            }
        }

        if ($workKey === '') {
            return $meta;
        }

        $workKey = '/' . ltrim($workKey, '/');
        $work = $this->httpGetJson(self::API_BASE . $workKey . '.json');
        if (!is_array($work)) {
            return $meta;
        }

        $description = $work['description'] ?? null;
        if (is_array($description)) {
            $description = (string) ($description['value'] ?? '');
        }
        $description = trim((string) $description);
        if ($description !== '' && trim((string) ($meta['synopsis'] ?? '')) === '') {
            $meta['synopsis'] = $description;
        }

        if (trim((string) ($meta['sous_titre'] ?? '')) === '') {
            $workSubtitle = trim((string) ($work['subtitle'] ?? ''));
            if ($workSubtitle !== '') {
                $meta['sous_titre'] = $workSubtitle;
            }
        }

        return $this->applyWorkSeries($meta, $work);
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $edition
     * @return array<string, mixed>
     */
    private function applyEditionExtras(array $meta, array $edition): array
    {
        if (trim((string) ($meta['sous_titre'] ?? '')) === '') {
            $subtitle = trim((string) ($edition['subtitle'] ?? ''));
            if ($subtitle !== '') {
                $meta['sous_titre'] = $subtitle;
            }
        }

        if (trim((string) ($meta['langue'] ?? '')) === '') {
            $languages = $edition['languages'] ?? null;
            if (is_array($languages) && isset($languages[0]['key'])) {
                $langKey = (string) $languages[0]['key'];
                if (str_contains($langKey, '/fre')) {
                    $meta['langue'] = 'fr';
                } elseif (str_contains($langKey, '/eng')) {
                    $meta['langue'] = 'en';
                }
            }
        }

        $parsed = self::parseSeriesFromEditionField($edition['series'] ?? null);
        if (trim((string) ($meta['saga'] ?? '')) === '' && $parsed['saga'] !== '') {
            $meta['saga'] = $parsed['saga'];
        }
        if ((int) ($meta['saga_ordre'] ?? 0) <= 0 && $parsed['saga_ordre'] > 0) {
            $meta['saga_ordre'] = $parsed['saga_ordre'];
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $work
     * @return array<string, mixed>
     */
    private function applyWorkSeries(array $meta, array $work): array
    {
        $needsSaga = trim((string) ($meta['saga'] ?? '')) === '';
        $needsOrdre = (int) ($meta['saga_ordre'] ?? 0) <= 0;
        if (!$needsSaga && !$needsOrdre) {
            return $meta;
        }

        $seriesList = $work['series'] ?? null;
        if (!is_array($seriesList) || $seriesList === []) {
            return $meta;
        }

        foreach ($seriesList as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ($needsOrdre) {
                $ordre = self::parseSeriesPosition((string) ($entry['position'] ?? ''));
                if ($ordre > 0) {
                    $meta['saga_ordre'] = $ordre;
                    $needsOrdre = false;
                }
            }

            if ($needsSaga) {
                $seriesRef = $entry['series'] ?? null;
                $seriesKey = '';
                if (is_array($seriesRef)) {
                    $seriesKey = trim((string) ($seriesRef['key'] ?? ''));
                }
                $name = $this->fetchSeriesName($seriesKey);
                if ($name !== '') {
                    $meta['saga'] = $name;
                    $needsSaga = false;
                }
            }

            if (!$needsSaga && !$needsOrdre) {
                break;
            }
        }

        return $meta;
    }

    private function fetchSeriesName(string $seriesKey): string
    {
        $seriesKey = trim($seriesKey);
        if ($seriesKey === '') {
            return '';
        }

        if (preg_match('#(?:^|/)series/(OL\d+L)$#i', $seriesKey, $m) !== 1) {
            return '';
        }

        $payload = $this->httpGetJson(self::API_BASE . '/series/' . strtoupper($m[1]) . '.json');
        if (!is_array($payload)) {
            return '';
        }

        $name = trim((string) ($payload['name'] ?? $payload['title'] ?? ''));

        return $name;
    }

    /**
     * Décode une chaîne type « Harry Potter, #1 » ou « Discworld #8 ».
     *
     * @return array{saga: string, saga_ordre: int}
     */
    public static function parseSeriesHint(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['saga' => '', 'saga_ordre' => 0];
        }

        if (preg_match('/^(.+?)[,;]?\s*#\s*(\d+)\s*$/u', $raw, $m) === 1) {
            return [
                'saga' => trim($m[1], " \t,"),
                'saga_ordre' => max(0, (int) $m[2]),
            ];
        }

        if (preg_match(
            '/^(.+?)[,;]?\s*(?:book|tome|vol\.?|volume|n[°o]\.?|no\.?)\s*(\d+)\s*$/iu',
            $raw,
            $m
        ) === 1) {
            return [
                'saga' => trim($m[1], " \t,"),
                'saga_ordre' => max(0, (int) $m[2]),
            ];
        }

        if (preg_match('/^(.+?)[,;]\s*(\d+)\s*$/u', $raw, $m) === 1) {
            return [
                'saga' => trim($m[1]),
                'saga_ordre' => max(0, (int) $m[2]),
            ];
        }

        return ['saga' => $raw, 'saga_ordre' => 0];
    }

    /**
     * Position Open Library (« 1 », « 1-3 », « Book 2 ») → entier utilisable.
     */
    public static function parseSeriesPosition(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/(\d+)/', $raw, $m) === 1) {
            return max(0, (int) $m[1]);
        }

        return 0;
    }

    /**
     * @param mixed $seriesField
     * @return array{saga: string, saga_ordre: int}
     */
    public static function parseSeriesFromEditionField(mixed $seriesField): array
    {
        if (!is_array($seriesField)) {
            if (is_string($seriesField)) {
                return self::parseSeriesHint($seriesField);
            }

            return ['saga' => '', 'saga_ordre' => 0];
        }

        foreach ($seriesField as $entry) {
            if (is_string($entry)) {
                $parsed = self::parseSeriesHint($entry);
                if ($parsed['saga'] !== '') {
                    return $parsed;
                }
            }
        }

        return ['saga' => '', 'saga_ordre' => 0];
    }

    /**
     * @param array<string, mixed> $doc
     * @return array{saga: string, saga_ordre: int}
     */
    public static function parseSeriesFromSearchDoc(array $doc): array
    {
        $saga = '';
        $names = $doc['series_name'] ?? null;
        if (is_array($names)) {
            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $saga = $name;
                    break;
                }
            }
        } elseif (is_string($names)) {
            $saga = trim($names);
        }

        $ordre = 0;
        $positions = $doc['series_position'] ?? null;
        if (is_array($positions)) {
            foreach ($positions as $position) {
                $ordre = self::parseSeriesPosition((string) $position);
                if ($ordre > 0) {
                    break;
                }
            }
        } elseif (is_string($positions) || is_int($positions)) {
            $ordre = self::parseSeriesPosition((string) $positions);
        }

        return ['saga' => $saga, 'saga_ordre' => $ordre];
    }

    public static function extractYear(string $publishDate): int
    {
        if (preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $publishDate, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    /** @return array<string, mixed>|null */
    private function httpGetJson(string $url): ?array
    {
        $this->lastError = null;
        $result = $this->httpGet($url);
        if ($result === null) {
            return null;
        }

        if ($result['code'] === 404) {
            $this->lastError = 'Ressource Open Library introuvable (404).';

            return null;
        }

        if ($result['code'] >= 400) {
            $this->lastError = 'Open Library HTTP ' . $result['code'] . '.';

            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            $this->lastError = 'Réponse Open Library illisible.';

            return null;
        }

        return $data;
    }

    /** @return array{code: int, body: string}|null */
    private function httpGet(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $this->lastError = 'Impossible d’initialiser cURL.';

                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (!is_string($body)) {
                $this->lastError = 'Erreur réseau Open Library' . ($err !== '' ? ' : ' . $err : '.');

                return null;
            }
            if (strlen($body) > self::MAX_BODY_BYTES) {
                $body = substr($body, 0, self::MAX_BODY_BYTES);
            }

            return ['code' => $code, 'body' => $body];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::HTTP_TIMEOUT,
                'header' => "Accept: application/json\r\nUser-Agent: " . self::USER_AGENT . "\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            $this->lastError = 'Erreur réseau Open Library.';

            return null;
        }
        $code = 200;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) === 1) {
            $code = (int) $m[1];
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            $body = substr($body, 0, self::MAX_BODY_BYTES);
        }

        return ['code' => $code, 'body' => $body];
    }
}
