<?php
/**
 * Import catalogue magazines depuis un export JSON (ex. abandonware-magazines.org).
 *
 * Crée séries et numéros au niveau catalogue partagé uniquement — sans bibliothèque utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineCatalogImporter
{
    public const NOTES_ABM_PREFIX = 'abm_magazine_id=';

    /** Nombre de couvertures téléchargées par passage d’import (défaut). */
    public const DEFAULT_COVER_BATCH_SIZE = 20;

    public const MIN_COVER_BATCH_SIZE = 1;

    public const MAX_COVER_BATCH_SIZE = 40;

    /** Pause entre deux téléchargements HTTP (microsecondes). */
    private const COVER_DOWNLOAD_DELAY_MICROSECONDS = 300_000;

    /**
     * @param array{
     *   dry_run?: bool,
     *   skip_existing?: bool,
     *   download_covers?: bool,
     *   cover_batch_size?: int,
     *   series_filter?: list<string>,
     *   series_id_filter?: list<int>
     * } $options
     * @return array{
     *   dry_run: bool,
     *   series_created: int,
     *   series_reused: int,
     *   issues_created: int,
     *   issues_skipped: int,
     *   series_logos_cached: int,
     *   issue_covers_cached: int,
     *   issue_covers_failed: int,
     *   issue_covers_remaining: int,
     *   cover_batch_size: int,
     *   cover_batch_reached: bool,
     *   errors: list<string>
     * }
     */
    public function importFromExportArray(array $export, array $options = []): array
    {
        if (!MagazineRepository::isAvailable()) {
            return $this->emptyResult(['Module magazines non disponible.']);
        }

        $seriesList = $export['series'] ?? null;
        if (!is_array($seriesList)) {
            return $this->emptyResult(['Format JSON invalide : champ « series » manquant.']);
        }

        $dryRun = !empty($options['dry_run']);
        $skipExisting = ($options['skip_existing'] ?? true) !== false;
        $downloadCovers = !empty($options['download_covers']);
        $coverBatchSize = self::normalizeCoverBatchSize((int) ($options['cover_batch_size'] ?? self::DEFAULT_COVER_BATCH_SIZE));
        $titleFilters = $this->normalizeTitleFilters($options['series_filter'] ?? []);
        $idFilters = $this->normalizeIdFilters($options['series_id_filter'] ?? []);

        $result = $this->emptyResult();
        $result['dry_run'] = $dryRun;
        $result['cover_batch_size'] = $coverBatchSize;

        $magRepo = new MagazineRepository();
        $seriesRepo = new SeriesRepository();
        $catalogAdmin = new CatalogAdmin();
        $posterStorage = new PosterStorage();
        $coversAttemptedThisRun = 0;

        /** @var list<array{series_id: int, issues: list<array<string, mixed>>}> $processedSeriesIssues */
        $processedSeriesIssues = [];

        foreach ($seriesList as $serie) {
            if (!is_array($serie)) {
                continue;
            }

            $abmMagId = (int) ($serie['abm_magazine_id'] ?? 0);
            $titre = trim((string) ($serie['titre'] ?? ''));
            if ($titre === '') {
                continue;
            }

            if ($idFilters !== [] && ($abmMagId <= 0 || !in_array($abmMagId, $idFilters, true))) {
                continue;
            }
            if ($titleFilters !== [] && !$this->titleMatchesFilters($titre, $titleFilters)) {
                continue;
            }

            $seriesRow = $this->resolveSeries($seriesRepo, $abmMagId, $titre, $serie);
            if ($seriesRow === null) {
                if ($dryRun) {
                    $result['series_created']++;
                } else {
                    $create = $this->createCatalogSeries($seriesRepo, $abmMagId, $serie);
                    if (!is_int($create)) {
                        $result['errors'][] = 'Série « ' . $titre . ' » : ' . $create;
                        continue;
                    }
                    $seriesRow = $seriesRepo->findById($create, MediaDomain::MAGAZINE);
                    if ($seriesRow === null) {
                        $result['errors'][] = 'Série « ' . $titre . ' » : création impossible.';
                        continue;
                    }
                    $result['series_created']++;

                    if ($downloadCovers) {
                        $logoUrl = self::normalizePosterUrl((string) ($serie['logo_url'] ?? ''));
                        if ($logoUrl !== '' && $this->cacheSeriesLogo($posterStorage, (int) $seriesRow['id'], $logoUrl)) {
                            $result['series_logos_cached']++;
                        }
                    }
                }
            } else {
                $result['series_reused']++;
                if (!$dryRun && $downloadCovers) {
                    $logoUrl = self::normalizePosterUrl((string) ($serie['logo_url'] ?? ''));
                    $seriesPoster = trim((string) ($seriesRow['poster_url'] ?? ''));
                    if ($logoUrl !== '' && ($seriesPoster === '' || PosterStorage::isRemoteUrl($seriesPoster))) {
                        if ($this->cacheSeriesLogo($posterStorage, (int) $seriesRow['id'], $logoUrl)) {
                            $result['series_logos_cached']++;
                        }
                    }
                }
            }

            if ($dryRun) {
                $issues = is_array($serie['issues'] ?? null) ? $serie['issues'] : [];
                foreach ($issues as $issue) {
                    if (!is_array($issue)) {
                        continue;
                    }
                    $numero = trim((string) ($issue['numero'] ?? ''));
                    if ($numero === '') {
                        continue;
                    }
                    $result['issues_created']++;
                    if ($downloadCovers && self::normalizePosterUrl((string) ($issue['cover_url'] ?? '')) !== '') {
                        $result['issue_covers_remaining']++;
                    }
                }
                continue;
            }

            if ($seriesRow === null) {
                continue;
            }

            $seriesId = (int) ($seriesRow['id'] ?? 0);
            $issues = is_array($serie['issues'] ?? null) ? $serie['issues'] : [];
            $processedSeriesIssues[] = ['series_id' => $seriesId, 'issues' => $issues];

            foreach ($issues as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $numero = trim((string) ($issue['numero'] ?? ''));
                if ($numero === '') {
                    continue;
                }

                $coverUrl = self::normalizePosterUrl((string) ($issue['cover_url'] ?? ''));
                $horsSerie = !empty($issue['hors_serie']);
                $existing = $magRepo->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie);

                if ($existing !== null && $skipExisting) {
                    $result['issues_skipped']++;
                    if ($downloadCovers && $this->issueNeedsCoverDownload($existing, $coverUrl)) {
                        $this->tryCacheIssueCover(
                            $catalogAdmin,
                            (int) ($existing['oeuvre_id'] ?? 0),
                            $coverUrl,
                            $result,
                            $coversAttemptedThisRun,
                            $coverBatchSize
                        );
                    }
                    continue;
                }

                $payload = $this->issuePayloadFromExport($seriesRow, $issue);
                $oeuvreId = $magRepo->createCatalogIssue($seriesId, $payload);
                if (!is_int($oeuvreId)) {
                    $result['errors'][] = $titre . ' n°' . $numero . ' : ' . $oeuvreId;
                    continue;
                }

                $result['issues_created']++;

                if ($downloadCovers && $coverUrl !== '') {
                    $this->tryCacheIssueCover(
                        $catalogAdmin,
                        $oeuvreId,
                        $coverUrl,
                        $result,
                        $coversAttemptedThisRun,
                        $coverBatchSize
                    );
                }
            }
        }

        if ($downloadCovers && !$dryRun) {
            $result['issue_covers_remaining'] = $this->countPendingCoversForProcessedSeries(
                $magRepo,
                $processedSeriesIssues
            );
        }

        return $result;
    }

    public static function normalizeCoverBatchSize(int $size): int
    {
        if ($size <= 0) {
            return self::DEFAULT_COVER_BATCH_SIZE;
        }

        return max(self::MIN_COVER_BATCH_SIZE, min(self::MAX_COVER_BATCH_SIZE, $size));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseJsonFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function parseJsonString(string $json): ?array
    {
        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    public static function abmNotesForMagazineId(int $abmMagazineId): string
    {
        return $abmMagazineId > 0 ? self::NOTES_ABM_PREFIX . $abmMagazineId : '';
    }

    public static function parseAbmMagazineIdFromNotes(string $notes): int
    {
        if (preg_match('/' . preg_quote(self::NOTES_ABM_PREFIX, '/') . '(\d+)/', $notes, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public static function normalizePosterUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return SecureUrl::sanitizePosterUrl($url);
    }

    /**
     * @param list<string> $errors
     * @return array{
     *   dry_run: bool,
     *   series_created: int,
     *   series_reused: int,
     *   issues_created: int,
     *   issues_skipped: int,
     *   series_logos_cached: int,
     *   issue_covers_cached: int,
     *   issue_covers_failed: int,
     *   issue_covers_remaining: int,
     *   cover_batch_size: int,
     *   cover_batch_reached: bool,
     *   errors: list<string>
     * }
     */
    private function emptyResult(array $errors = []): array
    {
        return [
            'dry_run' => false,
            'series_created' => 0,
            'series_reused' => 0,
            'issues_created' => 0,
            'issues_skipped' => 0,
            'series_logos_cached' => 0,
            'issue_covers_cached' => 0,
            'issue_covers_failed' => 0,
            'issue_covers_remaining' => 0,
            'cover_batch_size' => self::DEFAULT_COVER_BATCH_SIZE,
            'cover_batch_reached' => false,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $serie
     * @return array<string, mixed>|null
     */
    private function resolveSeries(SeriesRepository $seriesRepo, int $abmMagId, string $titre, array $serie): ?array
    {
        if ($abmMagId > 0) {
            $byAbm = $this->findSeriesByAbmId($seriesRepo, $abmMagId);
            if ($byAbm !== null) {
                return $byAbm;
            }
        }

        $byTitre = $seriesRepo->findByTitre($titre, MediaDomain::MAGAZINE);
        if ($byTitre !== null) {
            return $byTitre;
        }

        return null;
    }

    private function findSeriesByAbmId(SeriesRepository $seriesRepo, int $abmMagId): ?array
    {
        if ($abmMagId <= 0) {
            return null;
        }

        $notes = self::abmNotesForMagazineId($abmMagId);
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM series
             WHERE media_domain = ? AND notes = ?
             LIMIT 1'
        );
        $stmt->execute([MediaDomain::MAGAZINE, $notes]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $serie
     * @return int|string
     */
    private function createCatalogSeries(SeriesRepository $seriesRepo, int $abmMagId, array $serie): int|string
    {
        $logoUrl = self::normalizePosterUrl((string) ($serie['logo_url'] ?? ''));

        return $seriesRepo->create([
            'titre' => trim((string) ($serie['titre'] ?? '')),
            'publication_type' => 'mensuel',
            'poster_url' => $logoUrl,
            'notes' => self::abmNotesForMagazineId($abmMagId),
        ], MediaDomain::MAGAZINE);
    }

    /**
     * @param array<string, mixed> $seriesRow
     * @param array<string, mixed> $issue
     * @return array<string, mixed>
     */
    private function issuePayloadFromExport(array $seriesRow, array $issue): array
    {
        $numero = trim((string) ($issue['numero'] ?? ''));
        $horsSerie = !empty($issue['hors_serie']);
        $numeroOrdre = (float) ($issue['numero_ordre'] ?? 0);
        if ($numeroOrdre <= 0) {
            $numeroOrdre = AbmApiParser::guessNumeroOrdre($numero, $horsSerie);
        }

        return [
            'numero' => $numero,
            'numero_ordre' => $numeroOrdre,
            'date_parution' => self::resolveIssueDateParution($issue),
            'annee' => max(0, (int) ($issue['annee'] ?? AbmApiParser::extractYear(
                (string) ($issue['date_label'] ?? '')
            ) ?? 0)),
            'est_hors_serie' => $horsSerie,
            'poster_url' => self::normalizePosterUrl((string) ($issue['cover_url'] ?? '')),
            'series_titre' => (string) ($seriesRow['titre'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $issue
     */
    private function resolveIssueDateParution(array $issue): string
    {
        $raw = trim((string) ($issue['date_parution'] ?? $issue['date_label'] ?? ''));
        if ($raw === '') {
            return '';
        }

        $parsed = PublicationType::parseParutionDateLabel($raw);
        if ($parsed !== null) {
            return $parsed;
        }

        return $raw;
    }

    private function cacheSeriesLogo(PosterStorage $posterStorage, int $seriesId, string $logoUrl): bool
    {
        if ($seriesId <= 0 || $logoUrl === '') {
            return false;
        }

        usleep(self::COVER_DOWNLOAD_DELAY_MICROSECONDS);
        $local = $posterStorage->cacheRemoteForSeries($seriesId, $logoUrl);
        if ($local === '') {
            return false;
        }

        (new SeriesRepository())->update($seriesId, ['poster_url' => $local]);

        return true;
    }

    /**
     * @param array<string, mixed> $existingRow
     */
    private function issueNeedsCoverDownload(array $existingRow, string $coverUrl): bool
    {
        if ($coverUrl === '') {
            return false;
        }

        $poster = trim((string) ($existingRow['poster_url'] ?? ''));

        return $poster === '' || PosterStorage::isRemoteUrl($poster);
    }

    /**
     * @param array{
     *   dry_run: bool,
     *   series_created: int,
     *   series_reused: int,
     *   issues_created: int,
     *   issues_skipped: int,
     *   series_logos_cached: int,
     *   issue_covers_cached: int,
     *   issue_covers_failed: int,
     *   issue_covers_remaining: int,
     *   cover_batch_size: int,
     *   cover_batch_reached: bool,
     *   errors: list<string>
     * } $result
     */
    private function tryCacheIssueCover(
        CatalogAdmin $catalogAdmin,
        int $oeuvreId,
        string $coverUrl,
        array &$result,
        int &$coversAttemptedThisRun,
        int $coverBatchSize
    ): void {
        if ($oeuvreId <= 0 || $coverUrl === '') {
            return;
        }

        if ($coversAttemptedThisRun >= $coverBatchSize) {
            $result['cover_batch_reached'] = true;

            return;
        }

        $coversAttemptedThisRun++;
        usleep(self::COVER_DOWNLOAD_DELAY_MICROSECONDS);

        if ($catalogAdmin->cachePosterForOeuvre($oeuvreId, $coverUrl)) {
            $result['issue_covers_cached']++;
        } else {
            $result['issue_covers_failed']++;
        }
    }

    /**
     * @param list<array{series_id: int, issues: list<array<string, mixed>>}> $processedSeriesIssues
     */
    private function countPendingCoversForProcessedSeries(
        MagazineRepository $magRepo,
        array $processedSeriesIssues
    ): int {
        $pending = 0;

        foreach ($processedSeriesIssues as $entry) {
            $seriesId = (int) ($entry['series_id'] ?? 0);
            $issues = $entry['issues'] ?? [];
            if ($seriesId <= 0 || !is_array($issues)) {
                continue;
            }

            foreach ($issues as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $numero = trim((string) ($issue['numero'] ?? ''));
                $coverUrl = self::normalizePosterUrl((string) ($issue['cover_url'] ?? ''));
                if ($numero === '' || $coverUrl === '') {
                    continue;
                }

                $horsSerie = !empty($issue['hors_serie']);
                $existing = $magRepo->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie);
                if ($existing === null || $this->issueNeedsCoverDownload($existing, $coverUrl)) {
                    $pending++;
                }
            }
        }

        return $pending;
    }

    /** @param list<string> $filters */
    private function titleMatchesFilters(string $titre, array $filters): bool
    {
        $haystack = mb_strtolower($titre);
        foreach ($filters as $needle) {
            if ($needle !== '' && str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function normalizeTitleFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $filter) {
            $filter = trim((string) $filter);
            if ($filter !== '') {
                $out[] = $filter;
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function normalizeIdFilters(array $filters): array
    {
        $out = [];
        foreach ($filters as $filter) {
            $id = (int) $filter;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
