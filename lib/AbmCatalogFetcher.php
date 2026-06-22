<?php
/**
 * Télécharge et assemble le catalogue magazines depuis abandonware-magazines.org (usage CLI ponctuel).
 */

declare(strict_types=1);

namespace Moncine;

final class AbmCatalogFetcher
{
    public const API_BASE = 'https://www.abandonware-magazines.org/api-dev.php';

    public const CHOIX_MAGAZINES = 12;

    public const CHOIX_ISSUES = 10;

    /**
     * @param list<string> $magazineTitleFilters sous-chaînes insensibles à la casse (vide = tout)
     * @param list<int> $magazineIdFilters ids ABM (vide = tout)
     */
    public function __construct(
        private readonly ?string $cacheDir = null,
        private readonly array $magazineTitleFilters = [],
        private readonly array $magazineIdFilters = []
    ) {
    }

    /**
     * @return array{
     *   format_version: int,
     *   source: string,
     *   generated_at: string,
     *   stats: array<string, int>,
     *   series: list<array<string, mixed>>
     * }
     */
    public function fetchExport(): array
    {
        $magazinesRaw = $this->fetchEndpoint(self::CHOIX_MAGAZINES, 'magazines');
        $issuesRaw = $this->fetchEndpoint(self::CHOIX_ISSUES, 'issues');

        $magazines = AbmApiParser::parseMagazinesList($magazinesRaw);
        $issues = AbmApiParser::parseIssuesDump($issuesRaw);

        $issues = $this->filterIssues($issues);
        $series = AbmApiParser::buildCatalogExport($magazines, $issues);
        $series = $this->filterSeries($series);

        $issueCount = 0;
        $withCover = 0;
        foreach ($series as $serie) {
            foreach ($serie['issues'] as $issue) {
                $issueCount++;
                if (trim((string) ($issue['cover_url'] ?? '')) !== '') {
                    $withCover++;
                }
            }
        }

        return [
            'format_version' => 1,
            'source' => 'https://www.abandonware-magazines.org/',
            'api_documentation' => self::API_BASE,
            'generated_at' => gmdate('c'),
            'stats' => [
                'series_count' => count($series),
                'issue_count' => $issueCount,
                'issues_with_cover_url' => $withCover,
            ],
            'series' => $series,
        ];
    }

    private function fetchEndpoint(int $choixapi, string $cacheKey): string
    {
        $url = self::API_BASE . '?choixapi=' . $choixapi;

        if ($this->cacheDir !== null && $this->cacheDir !== '') {
            $cacheFile = rtrim($this->cacheDir, '/') . '/choixapi_' . $choixapi . '_' . $cacheKey . '.txt';
            if (is_file($cacheFile)) {
                $cached = file_get_contents($cacheFile);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
            }
        }

        $body = $this->httpGet($url);
        if ($body === '') {
            throw new \RuntimeException('Réponse vide pour ' . $url);
        }

        if ($this->cacheDir !== null && $this->cacheDir !== '') {
            if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                throw new \RuntimeException('Impossible de créer le dossier cache : ' . $this->cacheDir);
            }
            $cacheFile = rtrim($this->cacheDir, '/') . '/choixapi_' . $choixapi . '_' . $cacheKey . '.txt';
            file_put_contents($cacheFile, $body);
        }

        return $body;
    }

    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('Impossible d’initialiser cURL pour : ' . $url);
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => 'Moncine-AbmFetch/1.0',
            ]);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode >= 400) {
                $detail = $error !== '' ? ' (' . $error . ')' : '';
                throw new \RuntimeException('Échec du téléchargement : ' . $url . $detail);
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 120,
                'header' => "User-Agent: Moncine-AbmFetch/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Échec du téléchargement : ' . $url);
        }

        return $body;
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @return list<array<string, mixed>>
     */
    private function filterIssues(array $issues): array
    {
        if ($this->magazineIdFilters === [] && $this->magazineTitleFilters === []) {
            return $issues;
        }

        return array_values(array_filter($issues, function (array $issue): bool {
            $magId = (int) ($issue['abm_magazine_id'] ?? 0);
            if ($this->magazineIdFilters !== [] && !in_array($magId, $this->magazineIdFilters, true)) {
                return false;
            }

            if ($this->magazineTitleFilters === []) {
                return true;
            }

            $titre = mb_strtolower((string) ($issue['magazine_titre'] ?? ''));
            foreach ($this->magazineTitleFilters as $needle) {
                $needle = mb_strtolower(trim($needle));
                if ($needle !== '' && str_contains($titre, $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param list<array<string, mixed>> $series
     * @return list<array<string, mixed>>
     */
    private function filterSeries(array $series): array
    {
        if ($this->magazineIdFilters === [] && $this->magazineTitleFilters === []) {
            return $series;
        }

        return array_values(array_filter($series, function (array $serie): bool {
            $magId = (int) ($serie['abm_magazine_id'] ?? 0);
            if ($this->magazineIdFilters !== [] && in_array($magId, $this->magazineIdFilters, true)) {
                return true;
            }

            if ($this->magazineTitleFilters === []) {
                return $this->magazineIdFilters === [];
            }

            $titre = mb_strtolower((string) ($serie['titre'] ?? ''));
            foreach ($this->magazineTitleFilters as $needle) {
                $needle = mb_strtolower(trim($needle));
                if ($needle !== '' && str_contains($titre, $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }
}
