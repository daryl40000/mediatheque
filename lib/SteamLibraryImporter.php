<?php
/**
 * Import bibliothèque Steam : récupération API, correspondance IGDB, ajout collection.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SteamLibraryImporter
{
    private const SESSION_PREVIEW_KEY = 'steam_import_preview';

    private const SESSION_PREVIEW_TTL = 3600;

    private PDO $db;

    public function __construct(
        private readonly SteamWebApiClient $steam = new SteamWebApiClient(),
        private readonly IgdbClient $igdb = new IgdbClient(),
        private readonly GameRepository $games = new GameRepository(),
        private readonly GameSteamStatsRepository $steamStats = new GameSteamStatsRepository(),
        private readonly GameEnricher $enricher = new GameEnricher(),
        ?PDO $db = null,
    ) {
        $this->db = $db ?? Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return GameRepository::isAvailable()
            && GameSchema::hasSteamAppIdColumn()
            && GameSchema::steamStatsTableExists()
            && GameSchema::hasUserSteamIdColumn();
    }

    public static function canImport(): bool
    {
        return self::isAvailable() && SteamConfig::hasApiKey();
    }

    /** Seuls les administrateurs peuvent créer des fiches catalogue à l’import. */
    public static function canCreateCatalogEntries(): bool
    {
        return UserContext::canManageCatalog();
    }

    public function getUserSteamId(int $userId): string
    {
        $user = (new UtilisateurRepository())->findById($userId);

        return SteamConfig::sanitizeSteamId((string) ($user['steam_id'] ?? ''));
    }

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   summary: array{total: int, in_library: int, catalog_only: int, new: int, with_igdb: int},
     *   error?: string
     * }
     */
    public function buildPreview(int $userId, int $foyerId): array
    {
        $emptySummary = ['total' => 0, 'in_library' => 0, 'catalog_only' => 0, 'new' => 0, 'with_igdb' => 0];

        if (!self::canImport()) {
            return [
                'rows' => [],
                'summary' => $emptySummary,
                'error' => 'Import Steam indisponible (migration ou clé API manquante).',
            ];
        }

        $steamId = $this->getUserSteamId($userId);
        if (!SteamConfig::isValidSteamId($steamId)) {
            return [
                'rows' => [],
                'summary' => $emptySummary,
                'error' => 'SteamID64 manquant ou invalide. Renseignez-le dans Paramètres du compte.',
            ];
        }

        $owned = $this->steam->getOwnedGames($steamId);
        if ($owned === []) {
            return [
                'rows' => [],
                'summary' => $emptySummary,
                'error' => $this->steam->getLastError() ?? 'Bibliothèque Steam vide.',
            ];
        }

        $appIds = array_map(static fn (array $game): int => (int) ($game['appid'] ?? 0), $owned);
        $igdbMap = [];
        if (IgdbConfig::hasCredentials() && GameRepository::hasIgdbColumns()) {
            $igdbMap = $this->igdb->mapSteamAppIdsToIgdbIds($appIds);
        }

        $resolver = SteamGameResolver::forUser($userId, $foyerId);

        $rows = [];
        $summary = [
            'total' => count($owned),
            'in_library' => 0,
            'catalog_only' => 0,
            'new' => 0,
            'with_igdb' => 0,
        ];

        foreach ($owned as $game) {
            $appid = (int) ($game['appid'] ?? 0);
            $igdbId = (int) ($igdbMap[$appid] ?? 0);
            if ($igdbId > 0) {
                $summary['with_igdb']++;
            }

            $row = $this->buildPreviewRow($game, $igdbId, $resolver);
            $rows[] = $row;

            $status = (string) ($row['status'] ?? 'new');
            if ($status === 'in_library') {
                $summary['in_library']++;
            } elseif ($status === 'catalog_only') {
                $summary['catalog_only']++;
            } else {
                $summary['new']++;
            }
        }

        $this->storePreviewInSession($userId, $rows);

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @param array<string, mixed> $game
     * @return array<string, mixed>
     */
    private function buildPreviewRow(array $game, int $igdbId, SteamGameResolver $resolver): array
    {
        $appid = (int) ($game['appid'] ?? 0);
        $playtime = (int) ($game['playtime_forever'] ?? 0);

        $name = trim((string) ($game['name'] ?? ''));
        if ($name === '') {
            $name = 'AppID ' . $appid;
        }

        $resolved = $resolver->resolve($appid, $igdbId, $name);
        $oeuvreId = (int) ($resolved['oeuvre_id'] ?? 0);
        $bibId = (int) ($resolved['bib_id'] ?? 0);
        $matchType = (string) ($resolved['match_type'] ?? '');
        $displayIgdbId = $igdbId > 0 ? $igdbId : (int) ($resolved['catalog_igdb_id'] ?? 0);

        $status = match (true) {
            $bibId > 0 => 'in_library',
            $oeuvreId > 0 => 'catalog_only',
            default => 'new',
        };

        $actionLabel = match ($status) {
            'in_library' => 'Mettre à jour (magasin Steam + temps de jeu)',
            'catalog_only' => 'Ajouter à ma bibliothèque',
            default => self::canCreateCatalogEntries()
                ? ($igdbId > 0
                    ? 'Créer fiche catalogue (IGDB) + ajouter'
                    : 'Créer fiche minimale + ajouter')
                : 'Proposer au catalogue (validation admin)',
        };

        return [
            'appid' => $appid,
            'name' => $name,
            'playtime_forever' => $playtime,
            'playtime_label' => GameRowMapper::formatSteamPlaytime($playtime),
            'rtime_last_played' => (int) ($game['rtime_last_played'] ?? 0),
            'igdb_id' => $displayIgdbId,
            'steam_igdb_id' => $igdbId,
            'oeuvre_id' => $oeuvreId,
            'bib_id' => $bibId,
            'status' => $status,
            'row_kind' => $oeuvreId > 0 ? 'import' : 'proposal',
            'match_type' => $matchType,
            'action_label' => $actionLabel,
            'img_icon_url' => (string) ($game['img_icon_url'] ?? ''),
        ];
    }

    /**
     * @param list<int> $importAppIds
     * @param list<int> $proposeAppIds
     * @return array{added: int, updated: int, proposed: int, skipped: int, errors: list<string>}
     */
    public function applySelected(
        int $userId,
        int $foyerId,
        array $importAppIds,
        array $proposeAppIds = []
    ): array {
        $preview = $this->loadPreviewFromSession($userId);
        if ($preview === null) {
            return [
                'added' => 0,
                'updated' => 0,
                'proposed' => 0,
                'skipped' => 0,
                'errors' => ['Aperçu expiré — relancez la préparation depuis la page Importer.'],
            ];
        }

        $importSelected = [];
        foreach ($importAppIds as $appid) {
            $appid = (int) $appid;
            if ($appid > 0) {
                $importSelected[$appid] = true;
            }
        }

        $proposeSelected = [];
        foreach ($proposeAppIds as $appid) {
            $appid = (int) $appid;
            if ($appid > 0) {
                $proposeSelected[$appid] = true;
            }
        }

        $added = 0;
        $updated = 0;
        $proposed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($preview as $row) {
            $appid = (int) ($row['appid'] ?? 0);
            if ($appid <= 0) {
                $skipped++;
                continue;
            }

            if (isset($importSelected[$appid])) {
                $result = $this->applyOne($row, $userId, $foyerId);
                if ($result === 'added') {
                    $added++;
                } elseif ($result === 'updated') {
                    $updated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } elseif (is_string($result)) {
                    $skipped++;
                    if (count($errors) < 20) {
                        $errors[] = $result;
                    }
                }
                continue;
            }

            if (isset($proposeSelected[$appid])) {
                $result = $this->proposeOne($row, $userId);
                if ($result === 'proposed') {
                    $proposed++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } elseif (is_string($result)) {
                    $skipped++;
                    if (count($errors) < 20) {
                        $errors[] = $result;
                    }
                }
                continue;
            }

            $skipped++;
        }

        $this->clearPreviewSession();

        return [
            'added' => $added,
            'updated' => $updated,
            'proposed' => $proposed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return 'proposed'|'skipped'|string
     */
    private function proposeOne(array $row, int $userId): string
    {
        if (!CatalogSubmission::canSubmit()) {
            return 'Seuls les utilisateurs peuvent proposer des jeux au catalogue.';
        }

        $appid = (int) ($row['appid'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $name = 'AppID ' . $appid;
        }

        $igdbId = (int) ($row['steam_igdb_id'] ?? 0);
        $storeUrl = SteamWebApiClient::storeUrl($appid, $name);
        $iconUrl = SteamWebApiClient::iconUrl($appid, (string) ($row['img_icon_url'] ?? ''));

        $payload = [
            'titre' => $name,
            'submission_domain' => MediaDomain::JEU,
            'platform' => GamePlatform::PC,
            'platforms' => GamePlatform::PC,
            'platform_list' => [GamePlatform::PC],
        ];
        if ($iconUrl !== '') {
            $payload['poster_url'] = $iconUrl;
        }
        if ($igdbId > 0) {
            $payload['igdb_id'] = $igdbId;
        }

        $note = sprintf('Import Steam — AppID %d — %s', $appid, $storeUrl);
        $result = (new CatalogSubmission())->submit($userId, $payload, $note);
        if (!is_int($result)) {
            return $result;
        }

        return 'proposed';
    }

    /**
     * @param array<string, mixed> $row
     * @return 'added'|'updated'|'skipped'|string
     */
    private function applyOne(array $row, int $userId, int $foyerId): string
    {
        $appid = (int) ($row['appid'] ?? 0);
        $igdbId = (int) ($row['steam_igdb_id'] ?? $row['igdb_id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $name = 'AppID ' . $appid;
        }

        $playtime = (int) ($row['playtime_forever'] ?? 0);
        $lastPlayed = (int) ($row['rtime_last_played'] ?? 0);
        $storeUrl = SteamWebApiClient::storeUrl($appid, $name);
        $iconUrl = SteamWebApiClient::iconUrl($appid, (string) ($row['img_icon_url'] ?? ''));
        $libraryDetails = $this->steamLibraryDetails($storeUrl);

        $resolver = SteamGameResolver::forUser($userId, $foyerId);
        $resolved = $resolver->resolve($appid, $igdbId, $name);
        $oeuvreId = (int) ($resolved['oeuvre_id'] ?? 0);
        $bibId = (int) ($resolved['bib_id'] ?? 0);
        $wasInLibrary = $bibId > 0;

        if ($oeuvreId <= 0) {
            if (!self::canCreateCatalogEntries()) {
                return $name . ' : introuvable au catalogue — utilisez « Proposer au catalogue ».';
            }

            $created = $this->createCatalogEntry($appid, $name, $igdbId, $iconUrl, $storeUrl);
            if (!is_int($created)) {
                return (string) $created;
            }
            $oeuvreId = $created;
        } else {
            $this->games->mergeDigitalStoreForOeuvre($oeuvreId, GameDigitalStore::STEAM, $storeUrl);
            $this->games->setSteamAppIdIfEmpty($oeuvreId, $appid);
        }

        if ($bibId <= 0) {
            $bibId = (int) ($this->games->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId) ?? 0);
        }

        if ($bibId <= 0) {
            $result = $this->games->addFromCatalogOeuvre(
                $oeuvreId,
                LibraryStatut::COLLECTION,
                $userId,
                $foyerId,
                $libraryDetails
            );
            if (!is_int($result)) {
                return (string) $result;
            }
            $bibId = $result;
        } else {
            $this->games->applyLibraryEditionDetails($bibId, $oeuvreId, $libraryDetails);
        }

        $this->games->setSteamAppId($oeuvreId, $appid);
        $this->steamStats->upsert($bibId, $appid, $playtime, $lastPlayed);

        return $wasInLibrary ? 'updated' : 'added';
    }

    /**
     * @return array<string, mixed>
     */
    private function steamLibraryDetails(string $storeUrl): array
    {
        return [
            'is_digital' => true,
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => $storeUrl],
            ]),
            'owned_platforms' => GamePlatform::PC,
            'platform' => GamePlatform::PC,
        ];
    }

    /** @return int|string oeuvre_id */
    private function createCatalogEntry(
        int $appid,
        string $name,
        int $igdbId,
        string $iconUrl,
        string $storeUrl
    ): int|string {
        $catalogCreator = new GameCatalogCreator($this->db, new GameCatalogWriter($this->db));
        $data = [
            'titre' => $name,
            'platform' => GamePlatform::PC,
            'platforms' => GamePlatform::PC,
            'is_digital' => true,
            'digital_stores' => GameDigitalStore::serializeList([
                ['store' => GameDigitalStore::STEAM, 'url' => $storeUrl],
            ]),
        ];
        if ($iconUrl !== '') {
            $data['poster_url'] = $iconUrl;
        }

        $oeuvreId = $catalogCreator->createCatalogOnly($data);
        if (!is_int($oeuvreId)) {
            return $oeuvreId;
        }

        $this->games->setSteamAppId($oeuvreId, $appid);
        $this->games->mergeDigitalStoreForOeuvre($oeuvreId, GameDigitalStore::STEAM, $storeUrl);

        if ($igdbId > 0 && GameEnricher::canEnrich()) {
            $this->enricher->correctOeuvreWithIgdbId($oeuvreId, (string) $igdbId, keepPoster: true);
        }

        return $oeuvreId;
    }

    /** @param list<array<string, mixed>> $rows */
    private function storePreviewInSession(int $userId, array $rows): void
    {
        $_SESSION[self::SESSION_PREVIEW_KEY] = [
            'user_id' => $userId,
            'created_at' => time(),
            'rows' => $rows,
        ];
    }

    /** @return list<array<string, mixed>>|null */
    private function loadPreviewFromSession(int $userId): ?array
    {
        $payload = $_SESSION[self::SESSION_PREVIEW_KEY] ?? null;
        if (!is_array($payload)) {
            return null;
        }

        if ((int) ($payload['user_id'] ?? 0) !== $userId) {
            return null;
        }

        $createdAt = (int) ($payload['created_at'] ?? 0);
        if ($createdAt <= 0 || (time() - $createdAt) > self::SESSION_PREVIEW_TTL) {
            $this->clearPreviewSession();

            return null;
        }

        $rows = $payload['rows'] ?? null;

        return is_array($rows) ? $rows : null;
    }

    public function clearPreviewSession(): void
    {
        unset($_SESSION[self::SESSION_PREVIEW_KEY]);
    }

    /**
     * Enregistre un lien manuel AppID → catalogue et met à jour l’aperçu en session.
     *
     * @return true|string
     */
    public function saveManualMapping(int $userId, int $foyerId, int $appid, int $oeuvreId): bool|string
    {
        if ($appid <= 0) {
            return 'AppID Steam invalide.';
        }

        if ($oeuvreId <= 0) {
            return 'Sélectionnez un jeu du catalogue.';
        }

        $mapRepo = new GameSteamAppIdMapRepository();
        $saved = $mapRepo->upsert($appid, $oeuvreId, $userId);
        if ($saved !== true) {
            return $saved;
        }

        $this->games->setSteamAppIdIfEmpty($oeuvreId, $appid);

        $preview = $this->loadPreviewFromSession($userId);
        if ($preview === null) {
            return true;
        }

        $resolver = SteamGameResolver::forUser($userId, $foyerId);
        $updated = false;
        foreach ($preview as $index => $row) {
            if ((int) ($row['appid'] ?? 0) !== $appid) {
                continue;
            }

            $preview[$index] = $this->buildPreviewRow([
                'appid' => $appid,
                'name' => (string) ($row['name'] ?? ''),
                'playtime_forever' => (int) ($row['playtime_forever'] ?? 0),
                'rtime_last_played' => (int) ($row['rtime_last_played'] ?? 0),
                'img_icon_url' => (string) ($row['img_icon_url'] ?? ''),
            ], (int) ($row['steam_igdb_id'] ?? 0), $resolver);
            $updated = true;
            break;
        }

        if ($updated) {
            $this->storePreviewInSession($userId, $preview);
        }

        return true;
    }

    /** @return list<array<string, mixed>>|null */
    public function getStoredPreview(int $userId): ?array
    {
        return $this->loadPreviewFromSession($userId);
    }

    public static function matchTypeLabel(string $matchType): string
    {
        return match ($matchType) {
            'manual_map' => 'Lien manuel',
            'steam_appid' => 'AppID catalogue',
            'igdb_id' => 'IGDB',
            'steam_url' => 'Lien Steam enregistré',
            'title_library' => 'Titre (ma collection)',
            'title_catalog' => 'Titre (catalogue)',
            'title_fuzzy_library' => 'Titre approx. (ma collection)',
            default => '—',
        };
    }
}
