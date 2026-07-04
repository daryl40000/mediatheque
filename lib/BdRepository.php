<?php
/**
 * BD / Manga : catalogue (oeuvres + oeuvre_bd) et collection utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class BdRepository
{
    public const POSSESSION_ALL = 'all';
    public const POSSESSION_OWNED = 'owned';
    public const POSSESSION_UNOWNED = 'unowned';
    public const FILTER_HORS_SERIE = 'hors_serie';

    private PDO $db;

    private ?BdLibraryQuery $libraryQueryCache = null;

    private ?BdCatalogWriter $catalogWriterCache = null;

    private ?BdCatalogUpdater $catalogUpdaterCache = null;

    private ?BdCatalogCreator $catalogCreatorCache = null;

    private ?BdLibraryAttach $libraryAttachCache = null;

    private ?BdPosterService $posterServiceCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function libraryQuery(): BdLibraryQuery
    {
        return $this->libraryQueryCache ??= new BdLibraryQuery($this->db);
    }

    private function catalogWriter(): BdCatalogWriter
    {
        return $this->catalogWriterCache ??= new BdCatalogWriter($this->db);
    }

    private function catalogUpdater(): BdCatalogUpdater
    {
        return $this->catalogUpdaterCache ??= new BdCatalogUpdater(
            $this->db,
            $this->libraryQuery(),
            $this->catalogWriter()
        );
    }

    private function catalogCreator(): BdCatalogCreator
    {
        return $this->catalogCreatorCache ??= new BdCatalogCreator(
            $this->db,
            $this->catalogWriter(),
            $this->libraryAttach()
        );
    }

    private function libraryAttach(): BdLibraryAttach
    {
        return $this->libraryAttachCache ??= new BdLibraryAttach($this->db, $this->libraryQuery());
    }

    private function posterService(): BdPosterService
    {
        return $this->posterServiceCache ??= new BdPosterService($this->libraryQuery());
    }

    /** @return list<string> */
    public static function sortableColumns(): array
    {
        return BdCatalogSql::sortableColumns();
    }

    public static function isValidSortColumn(string $sortBy): bool
    {
        return BdCatalogSql::isValidSortColumn($sortBy);
    }

    public static function normalizePossessionFilter(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return match ($raw) {
            self::POSSESSION_OWNED, 'possede', 'possédé', 'owned' => self::POSSESSION_OWNED,
            self::POSSESSION_UNOWNED, 'non_possede', 'non-possede', 'unowned' => self::POSSESSION_UNOWNED,
            self::FILTER_HORS_SERIE, 'hors-serie', 'hors_série', 'special' => self::FILTER_HORS_SERIE,
            default => self::POSSESSION_ALL,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function resolveTomeOrdre(array $data, int $seriesId, float $fallbackOrdre = 0): float
    {
        return BdTomeOrdre::resolve($data, $seriesId, $fallbackOrdre);
    }

    public static function isAvailable(): bool
    {
        return BdSchema::tableExists()
            && self::seriesLibraryTableExists()
            && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    public static function seriesLibraryTableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series_bibliotheque' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        return $this->libraryAttach()->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
    }

    public function isSeriesInLibrary(int $seriesId, string $statut, int $userId, int $foyerId): bool
    {
        return $this->libraryAttach()->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSeriesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'titre',
        string $sortDir = 'asc',
        string $query = ''
    ): array {
        return $this->libraryQuery()->listSeriesInLibrary($userId, $foyerId, $statut, $sortBy, $sortDir, $query);
    }

    public function countSeriesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $query = ''
    ): int {
        return $this->libraryQuery()->countSeriesInLibrary($userId, $foyerId, $statut, $query);
    }

    public function countTomesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        return $this->libraryQuery()->countTomesInLibrary($userId, $foyerId, $statut);
    }

    public function countCatalogTomesForSeries(int $seriesId): int
    {
        return $this->libraryQuery()->countCatalogTomesForSeries($seriesId);
    }

    public function countPossessedTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null
    ): int {
        return $this->libraryQuery()->countPossessedTomesForSeries($seriesId, $userId, $foyerId, $statut);
    }

    public function maxTomeNumeroForSeries(int $seriesId): int
    {
        return $this->libraryQuery()->maxTomeNumeroForSeries($seriesId);
    }

    public function maxTomeOrdreForSeries(int $seriesId): float
    {
        return $this->libraryQuery()->maxTomeOrdreForSeries($seriesId);
    }

    public static function suggestNextTomeNumero(int $lastTome): int
    {
        return BdTomeOrdre::suggestNextTomeNumero($lastTome);
    }

    public static function suggestNextTomeOrdre(float $lastOrdre): float
    {
        return BdTomeOrdre::suggestNextTomeOrdre($lastOrdre);
    }

    public function findStandardTomeBySeriesAndNumero(int $seriesId, int $tomeNumero): ?array
    {
        return $this->libraryQuery()->findStandardTomeBySeriesAndNumero($seriesId, $tomeNumero);
    }

    public function validateTomeNumeroForSeries(
        int $seriesId,
        int $tomeNumero,
        bool $horsSerie,
        ?int $excludeOeuvreId = null
    ): ?string {
        if ($seriesId <= 0 || $tomeNumero < 0 || $horsSerie) {
            return null;
        }

        $existing = $this->findStandardTomeBySeriesAndNumero($seriesId, $tomeNumero);
        if ($existing === null) {
            return null;
        }

        if ($excludeOeuvreId !== null && (int) ($existing['oeuvre_id'] ?? 0) === $excludeOeuvreId) {
            return null;
        }

        return 'Un autre tome avec ce numéro existe déjà pour cette série.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'tome',
        string $sortDir = 'asc',
        string $searchQuery = '',
        ?string $possessionFilter = null
    ): array {
        return $this->libraryQuery()->listTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $sortBy,
            $sortDir,
            $searchQuery,
            $possessionFilter
        );
    }

    public function countTomesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        ?string $possessionFilter = null
    ): int {
        return $this->libraryQuery()->countTomesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $searchQuery,
            $possessionFilter
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalogSeries(
        string $query,
        int $userId,
        int $foyerId,
        int $limit = 25
    ): array {
        return $this->libraryQuery()->searchCatalogSeries($query, $userId, $foyerId, $limit);
    }

    public function attachCatalogTomesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        return $this->libraryAttach()->attachCatalogTomesToCollection($seriesId, $userId, $foyerId);
    }

    public function findCatalogTomeBySeriesAndNumero(int $seriesId, int $tomeNumero): ?array
    {
        return $this->libraryQuery()->findCatalogTomeBySeriesAndNumero($seriesId, $tomeNumero);
    }

    public static function tableExists(): bool
    {
        return BdSchema::tableExists();
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
        ?BdListFilter $filter = null
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

    public function countInLibrary(
        int $userId,
        int $foyerId,
        string $statut = LibraryStatut::COLLECTION,
        string $searchQuery = '',
        ?BdListFilter $filter = null
    ): int {
        return $this->libraryQuery()->countInLibrary($userId, $foyerId, $statut, $searchQuery, $filter);
    }

    public function findByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        return $this->libraryQuery()->findByBibId($bibId, $userId, $foyerId);
    }

    public function findCatalogByOeuvreId(int $oeuvreId): ?array
    {
        return $this->libraryQuery()->findCatalogByOeuvreId($oeuvreId);
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        return $this->libraryQuery()->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, int $limit = 20): array
    {
        return $this->libraryQuery()->searchCatalog($query, $limit);
    }

    /** @return list<string> */
    public function listKnownGenres(int $limit = 40): array
    {
        return $this->libraryQuery()->listKnownGenres($limit);
    }

    /**
     * @param array<string, mixed> $libraryDetails
     * @return int|string
     */
    public function addFromCatalogOeuvre(
        int $oeuvreId,
        string $statut,
        int $userId,
        int $foyerId,
        array $libraryDetails = []
    ): int|string {
        return $this->libraryAttach()->addFromCatalogOeuvre($oeuvreId, $statut, $userId, $foyerId, $libraryDetails);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function createTomeWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        $data['series_id'] = $seriesId;

        return $this->createWithLibrary($data, $statut, $userId, $foyerId);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string
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
            return 'Module BD non disponible.';
        }

        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return 'Album introuvable.';
        }

        return $this->updateCatalogByOeuvreId((int) ($album['oeuvre_id'] ?? 0), $data, $bibId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data, ?int $bibId = null): bool|string
    {
        return $this->catalogUpdater()->updateByOeuvreId($oeuvreId, $data, $bibId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateTome(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null) {
            return 'Tome introuvable.';
        }

        $oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
        $seriesId = (int) ($album['series_id'] ?? 0);
        if ($seriesId <= 0) {
            return 'Série introuvable pour ce tome.';
        }

        $data['series_id'] = $seriesId;
        $tomeNum = max(0, (int) ($data['tome_numero'] ?? $album['tome_numero'] ?? 0));
        $horsSerie = array_key_exists('est_hors_serie', $data)
            ? !empty($data['est_hors_serie'])
            : !empty($album['est_hors_serie']);
        $numeroError = $this->validateTomeNumeroForSeries($seriesId, $tomeNum, $horsSerie, $oeuvreId);
        if ($numeroError !== null) {
            return $numeroError;
        }

        if (array_key_exists('support_possede', $data)) {
            if (!empty($data['support_possede'])) {
                $support = BdPhysicalSupport::normalize((string) ($data['support_physique'] ?? ''));
                if ($support === '') {
                    $support = BdPhysicalSupport::normalize((string) ($album['support_physique'] ?? ''));
                }
                if ($support === '') {
                    $support = BdPhysicalSupport::ALBUM;
                }
                $data['support_physique'] = $support;
            } else {
                $data['support_physique'] = '';
            }
        }

        return $this->updateCatalogByOeuvreId($oeuvreId, $data, $bibId);
    }

    public static function supportFromPost(array $post): string
    {
        if (empty($post['support_possede'])) {
            return '';
        }

        $support = BdPhysicalSupport::normalize((string) ($post['support_physique'] ?? ''));

        return $support !== '' ? $support : BdPhysicalSupport::ALBUM;
    }

    public function updatePosterUrl(int $oeuvreId, string $posterUrl): bool
    {
        return $this->posterService()->updatePosterUrl($oeuvreId, $posterUrl);
    }

    public function savePoster(int $oeuvreId, string $posterUrlInput, ?string $uploadedBinary = null): void
    {
        $this->posterService()->savePoster($oeuvreId, $posterUrlInput, $uploadedBinary);
    }

    public function promoteToCollection(int $bibId, int $userId, int $foyerId): bool
    {
        $album = $this->findByBibId($bibId, $userId, $foyerId);
        if ($album === null || ($album['statut'] ?? '') !== LibraryStatut::WISHLIST) {
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
}
