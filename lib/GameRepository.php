<?php
/**
 * Jeux vidéo : catalogue (oeuvres + oeuvre_jeu) et collection utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class GameRepository
{
    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return GameCatalogSql::sortableColumns();
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return GameCatalogSql::isValidSortColumn($sortBy);
    }

    private PDO $db;

    private ?GameLinkedGamesQuery $linkedGamesQuery = null;

    private ?GameCatalogWriter $catalogWriterCache = null;

    private ?GameLibraryQuery $libraryQueryCache = null;

    private ?GameCatalogUpdater $catalogUpdaterCache = null;

    private ?GameCatalogCreator $catalogCreatorCache = null;

    private ?GameLibraryAttach $libraryAttachCache = null;

    private ?GamePosterService $posterServiceCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function linkedGames(): GameLinkedGamesQuery
    {
        return $this->linkedGamesQuery ??= new GameLinkedGamesQuery($this->db);
    }

    private function catalogWriter(): GameCatalogWriter
    {
        return $this->catalogWriterCache ??= new GameCatalogWriter($this->db);
    }

    private function libraryQuery(): GameLibraryQuery
    {
        return $this->libraryQueryCache ??= new GameLibraryQuery($this->db);
    }

    private function catalogUpdater(): GameCatalogUpdater
    {
        return $this->catalogUpdaterCache ??= new GameCatalogUpdater($this->db);
    }

    private function catalogCreator(): GameCatalogCreator
    {
        return $this->catalogCreatorCache ??= new GameCatalogCreator($this->db, $this->catalogWriter());
    }

    private function libraryAttach(): GameLibraryAttach
    {
        return $this->libraryAttachCache ??= new GameLibraryAttach($this->db, $this->libraryQuery());
    }

    private function posterService(): GamePosterService
    {
        return $this->posterServiceCache ??= new GamePosterService($this->libraryQuery());
    }

    public static function isAvailable(): bool
    {
        return GameSchema::tableExists() && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function tableExists(): bool
    {
        return GameSchema::tableExists();
    }

    public static function hasEditionColumns(): bool
    {
        return GameSchema::hasEditionColumns();
    }

    public static function hasExtensionColumns(): bool
    {
        return GameSchema::hasExtensionColumns();
    }

    public static function hasIgdbMetadataColumns(): bool
    {
        return GameSchema::hasIgdbMetadataColumns();
    }

    public static function hasRemakeColumns(): bool
    {
        return GameSchema::hasRemakeColumns();
    }

    public static function hasIgdbColumns(): bool
    {
        return GameSchema::hasIgdbColumns();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function validateGameRelationFlags(array $data, int $selfOeuvreId = 0): ?string
    {
        return GameRelations::validateFlags($data, $selfOeuvreId);
    }

    public static function hasTestedOnLinuxColumn(): bool
    {
        return GameSchema::hasTestedOnLinuxColumn();
    }

    public static function hasLinuxNotSupportedColumn(): bool
    {
        return GameSchema::hasLinuxNotSupportedColumn();
    }

    public static function hasNonPretableColumn(): bool
    {
        return GameSchema::hasNonPretableColumn();
    }

    /** @param array<string, mixed> $post */
    public static function nonPretableFromPost(array $post): bool
    {
        return GameFormPayload::nonPretableFromPost($post);
    }

    public static function hasPlatformsColumn(): bool
    {
        return GameSchema::hasPlatformsColumn();
    }

    public static function hasOwnedPlatformsColumn(): bool
    {
        return GameSchema::hasOwnedPlatformsColumn();
    }

    /**
     * @param array<string, mixed> $post
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function catalogPlatformsFromPost(array $post): array
    {
        return GameFormPayload::catalogPlatformsFromPost($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{owned_platforms: string, owned_platform_list: list<string>}
     */
    public static function ownedPlatformsFromPost(array $post, string $catalogPlatformsCsv): array
    {
        return GameFormPayload::ownedPlatformsFromPost($post, $catalogPlatformsCsv);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{platform: string, platforms: string, platform_list: list<string>}
     */
    public static function resolveCatalogPlatformFields(array $data): array
    {
        return GameFormPayload::resolveCatalogPlatformFields($data);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{physical_supports: string, digital_stores: string, is_digital: bool}
     */
    public static function editionPayloadFromPost(array $post): array
    {
        return GameFormPayload::editionPayloadFromPost($post);
    }

    public static function linuxFlagsFromPost(array $post): array
    {
        return GameFormPayload::linuxFlagsFromPost($post);
    }

    /** @deprecated Utiliser linuxFlagsFromPost() */
    public static function testedOnLinuxFromPost(array $post): bool
    {
        return GameFormPayload::testedOnLinuxFromPost($post);
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function catalogPayloadFromPost(array $post): array
    {
        return GameFormPayload::catalogPayloadFromPost($post);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string ID œuvre ou message d’erreur
     */
    public function createCatalogOnly(array $data): int|string
    {
        return $this->catalogCreator()->createCatalogOnly($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?GameListFilter $filter = null
    ): array {
        return $this->libraryQuery()->listInLibrary(
            $userId,
            $foyerId,
            $statut,
            $sortBy,
            $sortDir,
            $searchQuery,
            $filter
        );
    }

    public function promoteToCollection(int $bibId, int $userId, int $foyerId): bool
    {
        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null || ($game['statut'] ?? '') !== LibraryStatut::WISHLIST) {
            return false;
        }

        return (new BibliothequeRepository())->promoteToCollection($bibId, $userId, $foyerId);
    }

    public function deleteById(int $bibId, int $userId, int $foyerId): bool
    {
        if ($this->findByBibId($bibId, $userId, $foyerId) === null) {
            return false;
        }

        $this->db->prepare('DELETE FROM historique WHERE film_id = ?')->execute([$bibId]);

        return (new BibliothequeRepository())->deleteById($bibId, $userId, $foyerId);
    }

    /** @return true|string */
    public function updateTestedOnLinux(int $bibId, int $userId, int $foyerId, bool $testedOnLinux): bool|string
    {
        if (!self::hasTestedOnLinuxColumn()) {
            return 'Option Linux non disponible (migration en cours).';
        }

        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return 'Jeu introuvable.';
        }

        if (GamePlatform::normalize((string) ($game['platform'] ?? '')) !== GamePlatform::PC && $testedOnLinux) {
            return 'Seuls les jeux PC peuvent être marqués comme testés sous Linux.';
        }

        GameLibraryFields::saveLinuxFlags(
            $this->db,
            $bibId,
            GamePlatform::normalize((string) ($game['platform'] ?? '')),
            $testedOnLinux,
            false
        );

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        return $this->libraryQuery()->searchCatalog($query, $limit);
    }

    /** @return array<string, mixed>|null */
    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        return $this->libraryQuery()->findByBibId($bibId, $userId, $foyerId);
    }

    /**
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, platform_short:string, display_label:string}>
     */
    public function listExtensionsForBaseGame(int $baseOeuvreId, int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || !GameSchema::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listLibraryExtensions($baseOeuvreId, $userId, $foyerId);
    }

    /**
     * @return list<array{bib_id:int, oeuvre_id:int, titre:string, annee:int, platform_short:string, display_label:string}>
     */
    public function listRemakesForOriginalGame(int $originalOeuvreId, int $userId, int $foyerId): array
    {
        if (!self::isAvailable() || !GameSchema::hasRemakeColumns() || $originalOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listLibraryRemakes($originalOeuvreId, $userId, $foyerId);
    }

    /** @return array<string, mixed>|null */
    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        return $this->libraryQuery()->findCatalogByOeuvreId($oeuvreId);
    }

    public function findCatalogBySteamAppId(int $appid): ?array
    {
        return $this->libraryQuery()->findCatalogBySteamAppId($appid);
    }

    public function findCatalogByIgdbId(int $igdbId): ?array
    {
        return $this->libraryQuery()->findCatalogByIgdbId($igdbId);
    }

    public function setSteamAppId(int $oeuvreId, int $appid): void
    {
        if (!GameSchema::hasSteamAppIdColumn() || $oeuvreId <= 0 || $appid <= 0) {
            return;
        }

        $this->db->prepare('UPDATE oeuvre_jeu SET steam_appid = ? WHERE oeuvre_id = ?')
            ->execute([$appid, $oeuvreId]);
    }

    public function setSteamAppIdIfEmpty(int $oeuvreId, int $appid): void
    {
        if (!GameSchema::hasSteamAppIdColumn() || $oeuvreId <= 0 || $appid <= 0) {
            return;
        }

        $this->db->prepare(
            'UPDATE oeuvre_jeu SET steam_appid = ? WHERE oeuvre_id = ? AND COALESCE(steam_appid, 0) = 0'
        )->execute([$appid, $oeuvreId]);
    }

    public function mergeDigitalStoreForOeuvre(int $oeuvreId, string $store, string $url = ''): void
    {
        if (!GameSchema::hasEditionColumns() || $oeuvreId <= 0) {
            return;
        }

        $game = $this->findCatalogByOeuvreId($oeuvreId);
        if ($game === null) {
            return;
        }

        $merged = GameDigitalStore::mergeStore((string) ($game['digital_stores'] ?? ''), $store, $url);
        $isDigital = GameDigitalStore::hasDigitalEdition($merged, !empty($game['is_digital']));
        $this->db->prepare(
            'UPDATE oeuvre_jeu SET digital_stores = ?, is_digital = ? WHERE oeuvre_id = ?'
        )->execute([$merged, $isDigital ? 1 : 0, $oeuvreId]);
    }

    /**
     * @param array<string, mixed> $libraryDetails
     */
    public function applyLibraryEditionDetails(int $bibId, int $oeuvreId, array $libraryDetails): void
    {
        $this->libraryAttach()->applyDetailsAfterCatalogAttach($bibId, $oeuvreId, $libraryDetails);
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        return $this->libraryQuery()->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
    }

    /**
     * @return int|string bib_id ou message d’erreur
     */
    public function addFromCatalogOeuvre(
        int $oeuvreId,
        string $statut,
        int $userId,
        int $foyerId,
        array $libraryDetails = []
    ): int|string {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        if ($this->findCatalogByOeuvreId($oeuvreId) === null) {
            return 'Ce jeu n’existe pas dans le catalogue.';
        }

        $statut = LibraryStatut::normalize($statut);
        $bibRepo = new BibliothequeRepository();
        $library = $bibRepo->findByOeuvreId($oeuvreId, $userId, $foyerId);
        if ($library !== null) {
            $bibId = (int) ($library['id'] ?? 0);
            $currentStatut = (string) ($library['statut'] ?? LibraryStatut::COLLECTION);
            if ($currentStatut === $statut) {
                return 'Ce jeu existe déjà dans « ' . LibraryStatut::label($statut) . ' ».';
            }

            $update = ['statut' => $statut];
            if ($statut === LibraryStatut::COLLECTION) {
                $update['foyer_id'] = $foyerId;
            } else {
                $update['foyer_id'] = null;
            }
            $bibRepo->update($bibId, $update);
        } else {
            $bibId = $bibRepo->insert($userId, $foyerId, $oeuvreId, [
                'statut' => $statut,
                'support_physique' => '',
            ]);
        }

        $this->libraryAttach()->applyDetailsAfterCatalogAttach($bibId, $oeuvreId, $libraryDetails);

        return $bibId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCatalogExtensionsForBaseGame(int $baseOeuvreId): array
    {
        if (!self::isAvailable() || !GameSchema::hasExtensionColumns() || $baseOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listCatalogExtensions(
            $baseOeuvreId,
            GameCatalogSql::selectCatalogRow(),
            [GameRowMapper::class, 'hydrateCatalogRow']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCatalogRemakesForOriginalGame(int $originalOeuvreId): array
    {
        if (!self::isAvailable() || !GameSchema::hasRemakeColumns() || $originalOeuvreId <= 0) {
            return [];
        }

        return $this->linkedGames()->listCatalogRemakes(
            $originalOeuvreId,
            GameCatalogSql::selectCatalogRow(),
            [GameRowMapper::class, 'hydrateCatalogRow']
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string bib_id ou message d’erreur
     */
    public function createWithLibrary(
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        return $this->catalogCreator()->createWithLibrary($data, $statut, $userId, $foyerId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalog(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        if (!self::isAvailable()) {
            return 'Module jeux non disponible.';
        }

        $game = $this->findByBibId($bibId, $userId, $foyerId);
        if ($game === null) {
            return 'Jeu introuvable.';
        }

        $oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
        if ($oeuvreId <= 0) {
            return 'Fiche catalogue introuvable.';
        }

        $result = $this->catalogUpdater()->updateByOeuvreId($oeuvreId, $data, includePosterUrl: false);
        if ($result !== true) {
            return $result;
        }

        $platformFields = self::resolveCatalogPlatformFields($data);
        $ownedFields = self::ownedPlatformsFromPost($data, $platformFields['platforms']);
        $linuxPlatform = in_array(GamePlatform::PC, $ownedFields['owned_platform_list'], true)
            ? GamePlatform::PC
            : $platformFields['platform'];
        GameLibraryFields::saveLinuxFlags(
            $this->db,
            $bibId,
            $linuxPlatform,
            !empty($data['tested_on_linux']),
            !empty($data['linux_not_supported'])
        );
        GameLibraryFields::saveNonPretable($this->db, $bibId, !empty($data['non_pretable']));
        GameLibraryFields::saveOwnedPlatforms($this->db, $bibId, $ownedFields['owned_platforms']);

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data): bool|string
    {
        if ($this->findCatalogByOeuvreId($oeuvreId) === null) {
            return 'Jeu introuvable dans le catalogue.';
        }

        return $this->catalogUpdater()->updateByOeuvreId($oeuvreId, $data, includePosterUrl: true);
    }

    public static function editionSummary(array $row): string
    {
        return GameRowMapper::editionSummary($row);
    }

    public static function displayLabel(array $row): string
    {
        return GameRowMapper::displayLabel($row);
    }

    /** @return list<string> */
    public function listKnownGenres(int $limit = 80): array
    {
        return $this->libraryQuery()->listKnownGenres($limit);
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        return $this->posterService()->updatePosterUrl($oeuvreId, $posterUrl);
    }

    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        $this->posterService()->savePoster($oeuvreId, $posterUrlInput, $uploadedBinary);
    }

    public static function relationInsertColumns(): string
    {
        return GameRelations::insertColumns();
    }

    public static function relationInsertPlaceholders(): string
    {
        return GameRelations::insertPlaceholders();
    }

    public static function relationUpdateSet(): string
    {
        return GameRelations::updateSet();
    }

    /** @param array<string, mixed> $data @return list<mixed> */
    public static function relationWriteParams(array $data): array
    {
        return GameRelations::writeParams($data);
    }

    public static function formatAddedAt(string $createdAt): string
    {
        return GameRowMapper::formatAddedAt($createdAt);
    }
}
