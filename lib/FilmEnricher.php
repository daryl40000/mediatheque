<?php
/**
 * Enrichit les films via TMDB (synopsis FR, affiche, année, réalisateur, acteurs).
 */

declare(strict_types=1);

namespace Moncine;

final class FilmEnricher
{
    public function __construct(
        private readonly FilmRepository $films = new FilmRepository(),
        private readonly TmdbClient $tmdb = new TmdbClient()
    ) {
    }

    public function countPending(): int
    {
        return $this->films->countNeedingEnrichment();
    }

    public static function canEnrich(): bool
    {
        return TmdbConfig::hasApiKey();
    }

    /**
     * @return array{processed: int, enriched: int, not_found: int, errors: list<string>}
     */
    public function enrichBatch(int $limit = MONCINE_ENRICH_BATCH_SIZE, bool $force = false): array
    {
        if (!self::canEnrich()) {
            return [
                'processed' => 0,
                'enriched' => 0,
                'not_found' => 0,
                'errors' => ['Clé API TMDB manquante. Configurez-la sur la page Importer.'],
            ];
        }

        $batch = $this->films->findNeedingEnrichment($limit, $force);
        $enriched = 0;
        $notFound = 0;
        $errors = [];

        foreach ($batch as $film) {
            $id = (int) $film['id'];
            $title = (string) $film['titre'];

            usleep(250_000);

            try {
                $meta = $this->lookupByTitle($title);
            } catch (\Throwable $e) {
                $errors[] = $title . ' : ' . $e->getMessage();
                $this->films->markEnrichmentAttempt($id, false);
                continue;
            }

            if ($meta === null) {
                $notFound++;
                $err = $this->tmdb->getLastError();
                if ($err !== null && count($errors) < 15) {
                    $errors[] = $title . ' : ' . $err;
                }
                $this->films->markEnrichmentAttempt($id, false);
                continue;
            }

            $this->films->updateEnrichmentMetadata($id, $meta);
            $enriched++;
        }

        return [
            'processed' => count($batch),
            'enriched' => $enriched,
            'not_found' => $notFound,
            'errors' => $errors,
        ];
    }

    /**
     * Enrichit un seul film par son titre (recherche TMDB).
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOne(int $filmId): array
    {
        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Clé API TMDB manquante. Configurez-la sur la page Importer.',
            ];
        }

        $film = $this->films->findById($filmId);
        if ($film === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Film introuvable.',
            ];
        }

        $meta = $this->lookupForFilm($film);
        if ($meta === null) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->tmdb->getLastError() ?? 'Contenu non trouvé sur TMDB pour ce titre et cette catégorie.',
            ];
        }

        $this->films->updateEnrichmentMetadata($filmId, $meta);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche enrichie via TMDB (film, série, documentaire ou spectacle).',
        ];
    }

    /**
     * Corrige la fiche avec un identifiant TMDB précis.
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    /**
     * Enrichit une œuvre du catalogue partagé (sans entrée bibliothèque).
     *
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOeuvre(int $oeuvreId): array
    {
        if (!$this->films->usesCatalogModel()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Le catalogue partagé n’est pas disponible sur cette installation.',
            ];
        }

        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Clé API TMDB manquante. Configurez-la sur la page Importer.',
            ];
        }

        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        if ($oeuvre === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Œuvre introuvable.',
            ];
        }

        $meta = $this->lookupForFilm($oeuvre);
        if ($meta === null) {
            $this->films->markOeuvreEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->tmdb->getLastError() ?? 'Contenu non trouvé sur TMDB pour ce titre et cette catégorie.',
            ];
        }

        $this->films->updateOeuvreEnrichmentMetadata($oeuvreId, $meta);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche catalogue enrichie via TMDB.',
        ];
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function correctOeuvreWithTmdbId(int $oeuvreId, string $tmdbInput): array
    {
        if (!$this->films->usesCatalogModel()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Le catalogue partagé n’est pas disponible sur cette installation.',
            ];
        }

        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Clé API TMDB manquante. Configurez-la sur la page Importer.',
            ];
        }

        $oeuvre = (new OeuvreRepository())->findById($oeuvreId);
        if ($oeuvre === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Œuvre introuvable.',
            ];
        }

        $ref = TmdbClient::normalizeTmdbReference($tmdbInput);
        if ($ref === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Identifiant TMDB invalide (ex. 78, /movie/78 ou /tv/1396).',
            ];
        }

        $storedType = TmdbMediaType::normalize((string) ($oeuvre['tmdb_media_type'] ?? ''));
        $preferredType = $ref['type'] !== '' ? $ref['type'] : ($storedType !== '' ? $storedType : $this->preferredMediaTypeFromFilm($oeuvre));

        $tmdb = $this->tmdb->resolveById($ref['id'], $preferredType);
        if ($tmdb === null) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->tmdb->getLastError() ?? 'Contenu introuvable sur TMDB.',
            ];
        }

        $this->films->updateOeuvreEnrichmentMetadata($oeuvreId, $this->metaFromTmdbRecord($tmdb, true), true);

        $label = TmdbMediaType::label(
            (string) ($tmdb['media_type'] ?? TmdbMediaType::MOVIE),
            (string) ($tmdb['tv_kind'] ?? '')
        );

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche catalogue mise à jour (' . $label . ' TMDB #' . $ref['id'] . ').',
        ];
    }

    public function correctWithTmdbId(int $filmId, string $tmdbInput): array
    {
        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Clé API TMDB manquante. Configurez-la sur la page Importer.',
            ];
        }

        $film = $this->films->findById($filmId);
        if ($film === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Film introuvable.',
            ];
        }

        $ref = TmdbClient::normalizeTmdbReference($tmdbInput);
        if ($ref === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Identifiant TMDB invalide (ex. 78, /movie/78 ou /tv/1396).',
            ];
        }

        $storedType = TmdbMediaType::normalize((string) ($film['tmdb_media_type'] ?? ''));
        $preferredType = $ref['type'] !== '' ? $ref['type'] : ($storedType !== '' ? $storedType : $this->preferredMediaTypeFromFilm($film));

        $tmdb = $this->tmdb->resolveById($ref['id'], $preferredType);
        if ($tmdb === null) {
            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->tmdb->getLastError() ?? 'Contenu introuvable sur TMDB.',
            ];
        }

        $this->films->updateEnrichmentMetadata($filmId, $this->metaFromTmdbRecord($tmdb, true), true);

        $label = TmdbMediaType::label(
            (string) ($tmdb['media_type'] ?? TmdbMediaType::MOVIE),
            (string) ($tmdb['tv_kind'] ?? '')
        );

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche mise à jour (' . $label . ' TMDB #' . $ref['id'] . ').',
        ];
    }

    /**
     * Met à jour les fiches sélectionnées via leur identifiant TMDB déjà enregistré.
     *
     * @param list<int> $filmIds
     * @return array{
     *   selected: int,
     *   updated: int,
     *   skipped_no_tmdb: int,
     *   failed: int,
     *   errors: list<string>
     * }
     */
    public function enrichSelectedByTmdbId(array $filmIds): array
    {
        $selected = count($filmIds);
        if (!self::canEnrich()) {
            return [
                'selected' => $selected,
                'updated' => 0,
                'skipped_no_tmdb' => 0,
                'failed' => 0,
                'errors' => ['Clé API TMDB manquante. Configurez-la sur la page Importer.'],
            ];
        }

        $updated = 0;
        $skippedNoTmdb = 0;
        $failed = 0;
        $errors = [];

        foreach ($filmIds as $filmId) {
            $film = $this->films->findById($filmId);
            if ($film === null) {
                continue;
            }

            $tmdbId = (int) ($film['tmdb_id'] ?? 0);
            if ($tmdbId <= 0) {
                $skippedNoTmdb++;
                continue;
            }

            usleep(250_000);

            $mediaType = TmdbMediaType::normalize((string) ($film['tmdb_media_type'] ?? ''));
            $preferredType = $mediaType !== '' ? $mediaType : $this->preferredMediaTypeFromFilm($film);
            $titre = (string) $film['titre'];

            try {
                $record = $this->tmdb->resolveById($tmdbId, $preferredType);
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < 15) {
                    $errors[] = $titre . ' : ' . $e->getMessage();
                }
                continue;
            }

            if ($record === null) {
                $failed++;
                $err = $this->tmdb->getLastError();
                if ($err !== null && count($errors) < 15) {
                    $errors[] = $titre . ' : ' . $err;
                }
                continue;
            }

            $this->films->updateEnrichmentMetadata($filmId, $this->metaFromTmdbRecord($record), true);
            $updated++;
        }

        return [
            'selected' => $selected,
            'updated' => $updated,
            'skipped_no_tmdb' => $skippedNoTmdb,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Message résumé pour une mise à jour TMDB de masse.
     *
     * @param array{selected: int, updated: int, skipped_no_tmdb: int, failed: int, errors: list<string>} $result
     */
    public static function bulkTmdbSummaryMessage(array $result): string
    {
        $parts = [];
        if ($result['updated'] > 0) {
            $n = $result['updated'];
            $parts[] = $n . ' fiche' . ($n > 1 ? 's' : '') . ' mise' . ($n > 1 ? 's' : '') . ' à jour via TMDB.';
        }
        if ($result['skipped_no_tmdb'] > 0) {
            $n = $result['skipped_no_tmdb'];
            $parts[] = $n . ' ignoré' . ($n > 1 ? 's' : '') . ' (pas d’identifiant TMDB).';
        }
        if ($result['failed'] > 0) {
            $n = $result['failed'];
            $parts[] = $n . ' échec' . ($n > 1 ? 's' : '') . '.';
        }

        if ($parts === []) {
            return 'Aucune fiche mise à jour.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param array{
     *   tmdb_id: int,
     *   overview: string,
     *   poster_url: string,
     *   runtime: int,
     *   annee: int
     * } $tmdb
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $tmdb
     * @return array<string, mixed>
     */
    private function metaFromTmdbRecord(array $tmdb, bool $applyLocalizedTitle = false): array
    {
        $meta = [
            'poster_url' => $tmdb['poster_url'],
            'synopsis' => $tmdb['overview'],
            'realisateur' => (string) ($tmdb['director'] ?? ''),
            'duree_min' => $tmdb['runtime'],
            'annee' => $tmdb['annee'],
            'tmdb_id' => $tmdb['tmdb_id'],
            'tmdb_media_type' => (string) ($tmdb['media_type'] ?? TmdbMediaType::MOVIE),
            'tmdb_tv_kind' => (string) ($tmdb['tv_kind'] ?? ''),
            'titre_original' => (string) ($tmdb['original_title'] ?? ''),
            'nationalite' => (string) ($tmdb['nationalite'] ?? ''),
            'realisateur_tmdb_id' => (int) ($tmdb['director_tmdb_id'] ?? 0),
            'acteur_1' => (string) ($tmdb['acteur_1'] ?? ''),
            'acteur_2' => (string) ($tmdb['acteur_2'] ?? ''),
            'acteur_3' => (string) ($tmdb['acteur_3'] ?? ''),
            'acteur_1_tmdb_id' => (int) ($tmdb['acteur_1_tmdb_id'] ?? 0),
            'acteur_2_tmdb_id' => (int) ($tmdb['acteur_2_tmdb_id'] ?? 0),
            'acteur_3_tmdb_id' => (int) ($tmdb['acteur_3_tmdb_id'] ?? 0),
            'styles' => trim((string) ($tmdb['styles'] ?? '')),
            'moncine_kind' => MoncineContentKind::fromTmdbFields(
                (string) ($tmdb['media_type'] ?? ''),
                (string) ($tmdb['tv_kind'] ?? '')
            ),
        ];

        if ($applyLocalizedTitle) {
            $titre = trim((string) ($tmdb['localized_title'] ?? ''));
            if ($titre !== '') {
                $meta['titre'] = $titre;
            }
        }

        return $meta;
    }

    /**
     * Recherche TMDB adaptée à la catégorie de la fiche (film, série, documentaire, spectacle).
     *
     * @param array<string, mixed> $film
     * @return array<string, mixed>|null
     */
    private function lookupForFilm(array $film): ?array
    {
        $title = trim((string) ($film['titre'] ?? ''));
        if ($title === '') {
            return null;
        }

        $kind = ContentKindFilter::categoryKey($film);

        $tmdb = match ($kind) {
            ContentKindFilter::SERIE => $this->tmdb->searchTv($title),
            ContentKindFilter::DOCUMENTARY => $this->tmdb->searchTv($title) ?? $this->tmdb->searchMovie($title),
            ContentKindFilter::SPECTACLE => $this->tmdb->searchTv($title),
            default => $this->tmdb->searchMovie($title),
        };

        if ($tmdb === null || !TmdbClient::recordHasUsefulData($tmdb)) {
            $tmdb = $this->tmdb->searchByTitle($title);
        }

        if ($tmdb === null || !TmdbClient::recordHasUsefulData($tmdb)) {
            return null;
        }

        return $this->metaFromTmdbRecord($tmdb);
    }

    /** @return array<string, mixed>|null */
    private function lookupByTitle(string $title): ?array
    {
        return $this->lookupForFilm(['titre' => $title, 'moncine_kind' => MoncineContentKind::FILM]);
    }

    /**
     * Quand l’ID TMDB seul est saisi (ex. 11285), évite de prendre le film homonyme
     * au lieu de la série (Les cités d’or vs Cocoon 2 pour le même numéro).
     *
     * @param array<string, mixed> $film
     */
    private function preferredMediaTypeFromFilm(array $film): ?string
    {
        return match (ContentKindFilter::categoryKey($film)) {
            ContentKindFilter::SERIE,
            ContentKindFilter::SPECTACLE => TmdbMediaType::TV,
            ContentKindFilter::FILM => TmdbMediaType::MOVIE,
            default => null,
        };
    }
}
