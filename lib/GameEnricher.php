<?php
/**
 * Enrichit les jeux via IGDB (jaquette, année, studio, éditeur, genres).
 */

declare(strict_types=1);

namespace Moncine;

final class GameEnricher
{
    public function __construct(
        private readonly GameRepository $games = new GameRepository(),
        private readonly GameCatalogEnrichment $enrichment = new GameCatalogEnrichment(),
        private readonly IgdbClient $igdb = new IgdbClient()
    ) {
    }

    public function countPending(): int
    {
        return $this->enrichment->countNeedingEnrichment();
    }

    public static function canEnrich(): bool
    {
        return IgdbConfig::hasCredentials() && GameRepository::hasIgdbColumns();
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
                'errors' => ['Identifiants IGDB manquants ou migration non appliquée. Configurez IGDB sur la page Importer.'],
            ];
        }

        $batch = $this->enrichment->findNeedingEnrichment($limit, $force);
        $enriched = 0;
        $notFound = 0;
        $errors = [];

        foreach ($batch as $game) {
            $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
            $title = GameTitle::lookupTitle($game);

            usleep(250_000);

            try {
                $meta = $this->lookupByTitle($title, (int) ($game['annee'] ?? 0));
            } catch (\Throwable $e) {
                $errors[] = $title . ' : ' . $e->getMessage();
                $this->enrichment->markEnrichmentAttempt($oeuvreId);
                continue;
            }

            if ($meta === null) {
                $notFound++;
                $err = $this->igdb->getLastError();
                if ($err !== null && count($errors) < 15) {
                    $errors[] = $title . ' : ' . $err;
                }
                $this->enrichment->markEnrichmentAttempt($oeuvreId);
                continue;
            }

            $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta);
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
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOne(int $bibId, int $userId, int $foyerId): array
    {
        if (!GameRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module jeux non disponible.',
            ];
        }

        $game = $this->games->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Jeu introuvable.',
            ];
        }

        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Fiche catalogue introuvable pour ce jeu.',
            ];
        }

        return $this->enrichOeuvre($oeuvreId);
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function correctWithIgdbId(int $bibId, string $igdbInput, int $userId, int $foyerId): array
    {
        if (!GameRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module jeux non disponible.',
            ];
        }

        $game = $this->games->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Jeu introuvable.',
            ];
        }

        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Fiche catalogue introuvable pour ce jeu.',
            ];
        }

        return $this->correctOeuvreWithIgdbId($oeuvreId, $igdbInput);
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function enrichOeuvre(int $oeuvreId): array
    {
        if (!GameRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module jeux non disponible.',
            ];
        }

        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Identifiants IGDB manquants. Configurez-les sur la page Importer.',
            ];
        }

        $game = $this->games->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Jeu introuvable dans le catalogue.',
            ];
        }

        $meta = $this->lookupForGame($game);
        if ($meta === null) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->igdb->getLastError() ?? 'Jeu non trouvé sur IGDB pour ce titre.',
            ];
        }

        $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche jeu enrichie via IGDB (jaquette, année, studio, éditeur, genres).',
        ];
    }

    /**
     * @return array{ok: bool, not_found: bool, message: string}
     */
    public function correctOeuvreWithIgdbId(int $oeuvreId, string $igdbInput): array
    {
        if (!GameRepository::isAvailable()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Module jeux non disponible.',
            ];
        }

        if (!self::canEnrich()) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Identifiants IGDB manquants. Configurez-les sur la page Importer.',
            ];
        }

        $game = $this->games->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Jeu introuvable dans le catalogue.',
            ];
        }

        $igdbId = IgdbClient::parseIdFromInput($igdbInput);
        if ($igdbId === null || $igdbId <= 0) {
            return [
                'ok' => false,
                'not_found' => false,
                'message' => 'Identifiant IGDB invalide (nombre ou URL igdb.com/games/…).',
            ];
        }

        $meta = $this->igdb->getGameById($igdbId);
        if ($meta === null) {
            $this->enrichment->markEnrichmentAttempt($oeuvreId);

            return [
                'ok' => false,
                'not_found' => true,
                'message' => $this->igdb->getLastError() ?? 'Jeu introuvable sur IGDB pour cet identifiant.',
            ];
        }

        $this->enrichment->updateEnrichmentMetadata($oeuvreId, $meta, true);

        return [
            'ok' => true,
            'not_found' => false,
            'message' => 'Fiche jeu corrigée via IGDB #' . $igdbId . '.',
        ];
    }

    /**
     * @param array<string, mixed> $game
     * @return array<string, mixed>|null
     */
    private function lookupForGame(array $game): ?array
    {
        $storedId = (int) ($game['igdb_id'] ?? 0);
        if ($storedId > 0) {
            $byId = $this->igdb->getGameById($storedId);
            if ($byId !== null) {
                return $byId;
            }
        }

        $title = GameTitle::lookupTitle($game);
        if ($title === '') {
            $this->igdb->getLastError();

            return null;
        }

        $year = (int) ($game['annee'] ?? 0);

        return $this->lookupByTitle($title, $year > 0 ? $year : null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lookupByTitle(string $title, ?int $year = null): ?array
    {
        return $this->igdb->searchGame($title, $year);
    }
}
