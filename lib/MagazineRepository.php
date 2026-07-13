<?php
/**
 * Numéros de magazines : catalogue (oeuvres + oeuvre_magazine) et collection.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineRepository
{
    public const POSSESSION_ALL = 'all';
    public const POSSESSION_OWNED = 'owned';
    public const POSSESSION_UNOWNED = 'unowned';
    public const FILTER_HORS_SERIE = 'hors_serie';

    /** Numéros affichés par page sur la liste série (8 colonnes × 6 lignes). */
    public const ISSUES_PER_PAGE = 48;

    private PDO $db;

    private ?MagazineLibraryQuery $libraryQueryCache = null;

    private ?MagazineCatalogWriter $catalogWriterCache = null;

    private ?MagazineCatalogValidator $catalogValidatorCache = null;

    private ?MagazineLibraryAttach $libraryAttachCache = null;

    private ?MagazineLibraryMutations $libraryMutationsCache = null;

    private ?MagazineCatalogCreator $catalogCreatorCache = null;

    private ?MagazineCatalogUpdater $catalogUpdaterCache = null;

    private ?MagazinePdfService $pdfServiceCache = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function libraryQuery(): MagazineLibraryQuery
    {
        return $this->libraryQueryCache ??= new MagazineLibraryQuery($this->db);
    }

    private function catalogWriter(): MagazineCatalogWriter
    {
        return $this->catalogWriterCache ??= new MagazineCatalogWriter();
    }

    private function catalogValidator(): MagazineCatalogValidator
    {
        return $this->catalogValidatorCache ??= new MagazineCatalogValidator($this->libraryQuery());
    }

    private function libraryAttach(): MagazineLibraryAttach
    {
        return $this->libraryAttachCache ??= new MagazineLibraryAttach($this->db, $this->libraryQuery());
    }

    private function libraryMutations(): MagazineLibraryMutations
    {
        return $this->libraryMutationsCache ??= new MagazineLibraryMutations(
            $this->db,
            $this->libraryQuery(),
            $this->libraryAttach()
        );
    }

    private function catalogCreator(): MagazineCatalogCreator
    {
        return $this->catalogCreatorCache ??= new MagazineCatalogCreator(
            $this->db,
            $this->catalogWriter(),
            $this->catalogValidator(),
            $this->libraryQuery(),
            $this->libraryAttach(),
            $this->libraryMutations()
        );
    }

    private function catalogUpdater(): MagazineCatalogUpdater
    {
        return $this->catalogUpdaterCache ??= new MagazineCatalogUpdater(
            $this->db,
            $this->catalogValidator(),
            $this->libraryQuery(),
            $this->libraryMutations()
        );
    }

    private function pdfService(): MagazinePdfService
    {
        return $this->pdfServiceCache ??= new MagazinePdfService(
            $this->db,
            $this->libraryQuery(),
            $this->libraryMutations()
        );
    }

    public static function isAvailable(): bool
    {
        return SeriesRepository::tableExists()
            && self::seriesLibraryTableExists()
            && CatalogSchema::usesCatalogTables(Database::getInstance());
    }

    /** Titre catalogue d’un numéro (suffixe HS pour éviter les doublons de titre œuvre). */
    public static function buildCatalogIssueTitle(string $seriesTitre, string $numero, bool $horsSerie = false): string
    {
        $seriesTitre = trim($seriesTitre);
        $numero = trim($numero);
        $base = $seriesTitre !== '' ? $seriesTitre . ' — n°' . $numero : 'n°' . $numero;

        return $horsSerie ? $base . ' (HS)' : $base;
    }

    public static function seriesLibraryTableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'series_bibliotheque' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function pdfTextPreviewColumnExists(): bool
    {
        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_magazine)');
        if ($stmt === false) {
            return false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (($column['name'] ?? '') === 'pdf_text_preview') {
                return true;
            }
        }

        return false;
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

    /** Affiche une taille en Go (ou Mo si très petit). */
    public static function formatPdfStorageGigabytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Go';
        }

        $gigabytes = $bytes / (1024 ** 3);
        if ($gigabytes >= 1) {
            return number_format($gigabytes, 1, ',', ' ') . ' Go';
        }
        if ($gigabytes >= 0.01) {
            return number_format($gigabytes, 2, ',', ' ') . ' Go';
        }

        $megabytes = $bytes / (1024 ** 2);

        return number_format($megabytes, 0, ',', ' ') . ' Mo';
    }

    /** Chemin relatif type d’un PDF magazine (sous MONCINE_MEDIA_PATH). */
    public static function pdfStorageHint(): string
    {
        return MediaStorage::rootPath() . '/magazines/{revue}/{annee}/{revue}-{numero}.pdf';
    }

    /**
     * Chemin relatif d’un PDF magazine : revue / année / revue-numero.pdf
     *
     * @return string|false
     */
    public static function buildMagazinePdfRelativePath(string $seriesTitle, string $numero, string $dateParution): string|false
    {
        return MagazinePdfService::buildMagazinePdfRelativePath($seriesTitle, $numero, $dateParution);
    }

    public function registerSeriesInLibrary(
        int $seriesId,
        string $statut,
        int $userId,
        int $foyerId
    ): bool|string {
        return $this->libraryAttach()->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
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

    public function attachCatalogIssuesToCollection(int $seriesId, int $userId, int $foyerId): int
    {
        return $this->libraryAttach()->attachCatalogIssuesToCollection($seriesId, $userId, $foyerId);
    }

    public function countCatalogIssuesForSeries(int $seriesId): int
    {
        return $this->libraryQuery()->countCatalogIssuesForSeries($seriesId);
    }

    public function countPossessedIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null
    ): int {
        return $this->libraryQuery()->countPossessedIssuesForSeries($seriesId, $userId, $foyerId, $statut);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchCatalogIssues(
        int $seriesId,
        string $query,
        int $userId,
        int $foyerId,
        int $limit = 25
    ): array {
        return $this->libraryQuery()->searchCatalogIssues($seriesId, $query, $userId, $foyerId, $limit);
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

    public function countSeriesInLibrary(int $userId, int $foyerId, ?string $statut = null, string $query = ''): int
    {
        return $this->libraryQuery()->countSeriesInLibrary($userId, $foyerId, $statut, $query);
    }

    public function countIssuesInLibrary(int $userId, int $foyerId, ?string $statut = null): int
    {
        return $this->libraryQuery()->countIssuesInLibrary($userId, $foyerId, $statut);
    }

    /**
     * @return array{count: int, total_bytes: int}
     */
    public function collectionPdfStats(int $userId, int $foyerId): array
    {
        return $this->libraryQuery()->collectionPdfStats($userId, $foyerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $sortBy = 'numero_ordre',
        string $sortDir = 'desc',
        string $searchQuery = '',
        string $possessionFilter = self::POSSESSION_ALL,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        return $this->libraryQuery()->listIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $sortBy,
            $sortDir,
            $searchQuery,
            $possessionFilter,
            $limit,
            $offset
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchIssuesInLibrary(
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        int $limit = 30
    ): array {
        return $this->libraryQuery()->searchIssuesInLibrary($userId, $foyerId, $statut, $searchQuery, $limit);
    }

    public function countIssuesForSeries(
        int $seriesId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        string $searchQuery = '',
        string $possessionFilter = self::POSSESSION_ALL
    ): int {
        return $this->libraryQuery()->countIssuesForSeries(
            $seriesId,
            $userId,
            $foyerId,
            $statut,
            $searchQuery,
            $possessionFilter
        );
    }

    public function findIssueByBibId(int $bibId, int $userId, int $foyerId): ?array
    {
        return $this->libraryQuery()->findIssueByBibId($bibId, $userId, $foyerId);
    }

    public function maxNumeroOrdreForSeries(int $seriesId): float
    {
        return $this->libraryQuery()->maxNumeroOrdreForSeries($seriesId);
    }

    public function resolveIssueBibIdForRedirect(int $oeuvreId, int $userId, int $foyerId, int $fallbackBibId = 0): int
    {
        return $this->libraryQuery()->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $fallbackBibId);
    }

    public function findLibraryBibIdForCatalogOeuvre(int $oeuvreId, int $userId, int $foyerId): ?int
    {
        return $this->libraryQuery()->findLibraryBibIdForCatalogOeuvre($oeuvreId, $userId, $foyerId);
    }

    public function findCatalogIssueBySeriesNumero(
        int $seriesId,
        string $numero,
        ?bool $horsSerie = null,
        ?int $excludeOeuvreId = null
    ): ?array {
        return $this->libraryQuery()->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie, $excludeOeuvreId);
    }

    public function validateNumeroForSeries(
        int $seriesId,
        string $numero,
        bool $horsSerie,
        ?int $excludeOeuvreId = null
    ): ?string {
        return $this->catalogValidator()->validateNumeroForSeries($seriesId, $numero, $horsSerie, $excludeOeuvreId);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function createCatalogIssue(int $seriesId, array $data): int|string
    {
        return $this->catalogCreator()->createCatalogIssue($seriesId, $data);
    }

    public function findCatalogIssueByOeuvreId(int $oeuvreId): ?array
    {
        return $this->libraryQuery()->findCatalogIssueByOeuvreId($oeuvreId);
    }

    public function addFromCatalogOeuvre(int $oeuvreId, string $statut, int $userId, int $foyerId): int|string
    {
        return $this->libraryAttach()->addFromCatalogOeuvre($oeuvreId, $statut, $userId, $foyerId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateCatalogByOeuvreId(int $oeuvreId, array $data): bool|string
    {
        return $this->catalogUpdater()->updateCatalogByOeuvreId($oeuvreId, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function createIssueWithLibrary(
        int $seriesId,
        array $data,
        string $statut,
        int $userId,
        int $foyerId
    ): int|string {
        return $this->catalogCreator()->createIssueWithLibrary($seriesId, $data, $statut, $userId, $foyerId);
    }

    /**
     * @param array<string, mixed> $data
     * @return true|string
     */
    public function updateIssue(int $bibId, array $data, int $userId, int $foyerId): bool|string
    {
        return $this->catalogUpdater()->updateIssue($bibId, $data, $userId, $foyerId);
    }

    public function deleteFromLibrary(int $bibId, int $userId, int $foyerId): bool|string
    {
        return $this->libraryMutations()->deleteFromLibrary($bibId, $userId, $foyerId);
    }

    /**
     * @return array{removed_issues: int}|string
     */
    public function removeSeriesFromLibrary(int $seriesId, string $statut, int $userId, int $foyerId): array|string
    {
        return $this->libraryMutations()->removeSeriesFromLibrary($seriesId, $statut, $userId, $foyerId);
    }

    public function isSeriesInLibrary(int $seriesId, string $statut, int $userId, int $foyerId): bool
    {
        return $this->libraryAttach()->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
    }

    public function addIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        return $this->libraryAttach()->addIssueToWishlist($bibId, $userId, $foyerId);
    }

    /** @deprecated Utiliser addIssueToWishlist() */
    public function moveIssueToWishlist(int $bibId, int $userId, int $foyerId): bool|string
    {
        return $this->libraryAttach()->moveIssueToWishlist($bibId, $userId, $foyerId);
    }

    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $foyerId): bool
    {
        return $this->libraryMutations()->userCanAccessStoredObject($storedObjectId, $userId, $foyerId);
    }

    public function attachPdf(int $oeuvreId, string $tmpPath, string $originalName, int $fileSize): bool|string
    {
        return $this->pdfService()->attachPdf($oeuvreId, $tmpPath, $originalName, $fileSize);
    }

    public function detachPdf(int $oeuvreId): bool|string
    {
        return $this->pdfService()->detachPdf($oeuvreId);
    }

    public function syncSupportTagsForOeuvre(int $oeuvreId, ?bool $hasPaper = null): void
    {
        $this->pdfService()->syncSupportTagsForOeuvre($oeuvreId, $hasPaper);
    }

    public function applyCoverFromPdfIfMissing(int $oeuvreId, string $absolutePdfPath): void
    {
        $this->pdfService()->applyCoverFromPdfIfMissing($oeuvreId, $absolutePdfPath);
    }

    public function applyPageCountFromPdf(int $oeuvreId, string $absolutePdfPath, bool $force = false): void
    {
        $this->pdfService()->applyPageCountFromPdf($oeuvreId, $absolutePdfPath, $force);
    }

    public function indexPdfTextPreviewFromFile(int $oeuvreId, string $absolutePdfPath): void
    {
        $this->pdfService()->indexPdfTextPreviewFromFile($oeuvreId, $absolutePdfPath);
    }

    /**
     * @return array{indexed: int, skipped: int, errors: int}
     */
    public function reindexPdfTextPreviewsForSeries(int $seriesId, int $userId, int $foyerId, ?string $statut = null): array
    {
        return $this->pdfService()->reindexPdfTextPreviewsForSeries($seriesId, $userId, $foyerId, $statut);
    }
}
