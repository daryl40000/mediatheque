<?php
declare(strict_types=1);
namespace Moncine;
use PDO;
final class MagazinePdfService {
    public function __construct(private readonly PDO $db, private readonly MagazineLibraryQuery $libraryQuery, private readonly MagazineLibraryMutations $libraryMutations) {}
    /**
     * Chemin relatif d’un PDF magazine : revue / année / revue-numero.pdf
     *
     * @return string|false
     */
    public static function buildMagazinePdfRelativePath(string $seriesTitle, string $numero, string $dateParution): string|false
    {
        $seriesSlug = MagazineNumeroOrdre::slugifyForPath($seriesTitle, 'revue');
        $numeroSlug = MagazineNumeroOrdre::slugifyForPath($numero, 'numero');
        $year = MagazineNumeroOrdre::extractParutionYear($dateParution);
        $fileName = $seriesSlug . '-' . $numeroSlug . '.pdf';

        return MediaStorage::relativePath('magazine', $seriesSlug, $year, $fileName);
    }
    public function attachPdf(int $oeuvreId, string $tmpPath, string $originalName, int $fileSize): bool|string
    {
        if ($oeuvreId <= 0 || !is_readable($tmpPath)) {
            return 'Fichier PDF invalide.';
        }

        $maxBytes = UploadLimits::maxPdfBytes();
        if ($fileSize <= 0 || $fileSize > $maxBytes) {
            return UploadLimits::pdfTooLargeApplicationMessage();
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if (!$this->isPdfMime($mime, $tmpPath)) {
            return 'Le fichier doit être un PDF.';
        }

        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $meta = $this->findMagazinePdfMeta($oeuvreId);
        if ($meta === null) {
            return 'Numéro magazine introuvable.';
        }

        // Remplacement : supprime l’ancien PDF rattaché à ce numéro.
        $this->removeStoredPdfForOeuvre($oeuvreId);

        $relative = self::buildMagazinePdfRelativePath(
            (string) ($meta['series_titre'] ?? ''),
            (string) ($meta['numero'] ?? ''),
            (string) ($meta['date_parution'] ?? '')
        );
        if ($relative === false) {
            return 'Chemin de stockage invalide.';
        }

        $absolute = MediaStorage::absolutePath($relative);
        if ($absolute === '') {
            return 'Chemin de stockage invalide.';
        }

        $this->purgeStoredObjectAtRelativePath($relative);

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de créer le dossier médias.';
        }

        if (!@move_uploaded_file($tmpPath, $absolute)) {
            if (!@rename($tmpPath, $absolute) && !@copy($tmpPath, $absolute)) {
                return 'Impossible d’enregistrer le PDF (vérifiez les droits d’écriture sur '
                    . dirname($absolute) . ').';
            }
        }

        @chmod($absolute, 0640);

        $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        if ($stored === null) {
            $this->purgeStoredObjectAtRelativePath($relative);
            $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        }
        if ($stored === null) {
            @unlink($absolute);

            return 'Enregistrement du PDF en base impossible (chemin déjà utilisé ?).';
        }

        $this->db->prepare('UPDATE oeuvre_magazine SET stored_object_id = ? WHERE oeuvre_id = ?')
            ->execute([(int) $stored['id'], $oeuvreId]);

        $this->syncSupportTagsForOeuvre($oeuvreId);

        $this->schedulePdfPostProcessing($oeuvreId, $absolute);

        return true;
    }
    public function syncSupportTagsForOeuvre(int $oeuvreId, ?bool $hasPaper = null): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT b.id, b.support_physique, om.stored_object_id
             FROM bibliotheque b
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = b.oeuvre_id
             WHERE b.oeuvre_id = ? AND b.statut = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, LibraryStatut::COLLECTION]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }

        if ($hasPaper === null) {
            $hasPaper = MagazineSupport::hasPaper((string) ($row['support_physique'] ?? ''));
        }

        $hasPdf = (int) ($row['stored_object_id'] ?? 0) > 0;
        $this->db->prepare('UPDATE bibliotheque SET support_physique = ? WHERE id = ?')
            ->execute([
                MagazineSupport::formatTagsForStorage($hasPaper, $hasPdf),
                (int) ($row['id'] ?? 0),
            ]);

        $this->libraryMutations->clearWishlistEntriesWhenPossessed($oeuvreId);
    }
    public function schedulePdfPostProcessing(int $oeuvreId, string $absolutePdfPath): void
    {
        register_shutdown_function(function () use ($oeuvreId, $absolutePdfPath): void {
            if (!is_readable($absolutePdfPath)) { return; }
            @set_time_limit(300);
            try {
                $this->indexPdfTextPreviewFromFile($oeuvreId, $absolutePdfPath);
                $this->applyCoverFromPdfIfMissing($oeuvreId, $absolutePdfPath);
                $this->applyPageCountFromPdf($oeuvreId, $absolutePdfPath);
            } catch (\Throwable $e) {
                error_log('MagazinePdfService::schedulePdfPostProcessing: ' . $e->getMessage());
            }
        });
    }
    public function applyCoverFromPdfIfMissing(int $oeuvreId, string $absolutePdfPath): void
    {
        if ($oeuvreId <= 0 || !is_readable($absolutePdfPath) || !MagazinePdfCoverExtractor::isAvailable()) {
            return;
        }

        $stmt = $this->db->prepare('SELECT poster_url FROM oeuvres WHERE id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        $posterUrl = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($posterUrl !== '') {
            return;
        }

        $binary = MagazinePdfCoverExtractor::renderFirstPageJpeg($absolutePdfPath);
        if ($binary === '') {
            return;
        }

        $webPath = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
        if ($webPath === '') {
            return;
        }

        $this->db->prepare('UPDATE oeuvres SET poster_url = ? WHERE id = ?')
            ->execute([$webPath, $oeuvreId]);
    }
    public function applyPageCountFromPdf(int $oeuvreId, string $absolutePdfPath, bool $force = false): void
    {
        if ($oeuvreId <= 0 || !is_readable($absolutePdfPath) || !MagazinePdfInfo::isAvailable()) {
            return;
        }

        $pageCount = MagazinePdfInfo::readPageCount($absolutePdfPath);
        if ($pageCount <= 0) {
            return;
        }

        if (!$force) {
            $stmt = $this->db->prepare('SELECT pages FROM oeuvre_magazine WHERE oeuvre_id = ? LIMIT 1');
            $stmt->execute([$oeuvreId]);
            if ((int) ($stmt->fetchColumn() ?: 0) > 0) {
                return;
            }
        }

        $this->db->prepare('UPDATE oeuvre_magazine SET pages = ? WHERE oeuvre_id = ?')
            ->execute([$pageCount, $oeuvreId]);
    }
    public function indexPdfTextPreviewFromFile(int $oeuvreId, string $absolutePdfPath): void
    {
        if ($oeuvreId <= 0 || !MagazineRepository::pdfTextPreviewColumnExists()) {
            return;
        }

        $text = MagazinePdfTextExtractor::extractFirstPages($absolutePdfPath);
        $this->db->prepare('UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE oeuvre_id = ?')
            ->execute([$text, $oeuvreId]);
        // Trigger SQL met à jour magazine_issue_fts ; secours si index désynchronisé.
        MagazineIssueFts::upsert($oeuvreId);
    }
    public function reindexPdfTextPreviewsForSeries(int $seriesId, int $userId, int $foyerId, ?string $statut = null): array
    {
        $result = ['indexed' => 0, 'skipped' => 0, 'errors' => 0];
        if (!MagazineRepository::pdfTextPreviewColumnExists()) {
            return $result;
        }

        $canIndexText = MagazinePdfTextExtractor::isAvailable();
        $canReadMeta = MagazinePdfInfo::isAvailable() || MagazinePdfCoverExtractor::isAvailable();
        if (!$canIndexText && !$canReadMeta) {
            return $result;
        }

        $issues = $this->libraryQuery->listIssuesForSeries($seriesId, $userId, $foyerId, $statut);
        $storage = new LocalFilesystemObjectStorage();
        $storedRepo = new StoredObjectRepository();

        foreach ($issues as $issue) {
            $storedObjectId = (int) ($issue['stored_object_id'] ?? 0);
            if ($storedObjectId <= 0) {
                $result['skipped']++;

                continue;
            }

            $row = $storedRepo->findById($storedObjectId);
            if ($row === null) {
                $result['errors']++;

                continue;
            }

            $relative = (string) ($row['relative_path'] ?? '');
            $absolute = MediaStorage::absolutePath($relative);
            if ($absolute === '' || !$storage->exists($relative)) {
                $result['errors']++;

                continue;
            }

            try {
                $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
                if ($canIndexText) {
                    $this->indexPdfTextPreviewFromFile($oeuvreId, $absolute);
                }
                $this->applyCoverFromPdfIfMissing($oeuvreId, $absolute);
                $this->applyPageCountFromPdf($oeuvreId, $absolute);
                $result['indexed']++;
            } catch (\Throwable $e) {
                error_log('reindexPdfTextPreviewsForSeries: ' . $e->getMessage());
                $result['errors']++;
            }
        }

        return $result;
    }
    private function findMagazinePdfMeta(int $oeuvreId): ?array
    {
        if ($oeuvreId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT s.titre AS series_titre, om.numero, om.date_parution
             FROM oeuvre_magazine om
             INNER JOIN series s ON s.id = om.series_id
             WHERE om.oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
    private function purgeStoredObjectAtRelativePath(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '') {
            return;
        }

        $storedRepo = new StoredObjectRepository();
        $existing = $storedRepo->findByRelativePath($relativePath);
        if ($existing === null) {
            return;
        }

        (new LocalFilesystemObjectStorage())->delete($relativePath);
        $storedRepo->deleteById((int) ($existing['id'] ?? 0));
    }
    private function removeStoredPdfForOeuvre(int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $stmt = $this->db->prepare('SELECT stored_object_id FROM oeuvre_magazine WHERE oeuvre_id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        $storedObjectId = (int) ($stmt->fetchColumn() ?: 0);
        if ($storedObjectId <= 0) {
            return;
        }

        $storedRepo = new StoredObjectRepository();
        $row = $storedRepo->findById($storedObjectId);
        if ($row !== null) {
            $relative = (string) ($row['relative_path'] ?? '');
            if ($relative !== '') {
                (new LocalFilesystemObjectStorage())->delete($relative);
            }
            $storedRepo->deleteById($storedObjectId);
        }

        $this->db->prepare(
            'UPDATE oeuvre_magazine SET stored_object_id = NULL WHERE oeuvre_id = ?'
        )->execute([$oeuvreId]);

        $this->syncSupportTagsForOeuvre($oeuvreId);

        if (MagazineRepository::pdfTextPreviewColumnExists()) {
            $this->db->prepare('UPDATE oeuvre_magazine SET pdf_text_preview = ? WHERE oeuvre_id = ?')
                ->execute(['', $oeuvreId]);
            MagazineIssueFts::upsert($oeuvreId);
        }
    }
    private function isPdfMime(mixed $mime, string $path): bool
    {
        if ($mime === 'application/pdf' || $mime === 'application/x-pdf') {
            return true;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = (string) fread($handle, 5);
        fclose($handle);

        return str_starts_with($header, '%PDF-');
    }
}
