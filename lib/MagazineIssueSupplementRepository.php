<?php
/**
 * Suppléments / livrets bonus PDF rattachés à un numéro magazine.
 *
 * Un numéro peut avoir plusieurs PDF secondaires : couverture = page 1,
 * texte indexé pour la recherche (fusionné dans le FTS du numéro).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineIssueSupplementRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_issue_supplement' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    public function listForOeuvre(int $oeuvreId): array
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT mis.id, mis.oeuvre_id, mis.stored_object_id, mis.label, mis.sort_order,
                    mis.cover_url, mis.pdf_text_preview, mis.pages, mis.original_filename, mis.created_at,
                    so.mime, so.size_bytes, so.relative_path
             FROM magazine_issue_supplement mis
             INNER JOIN stored_objects so ON so.id = mis.stored_object_id
             WHERE mis.oeuvre_id = ?
             ORDER BY mis.sort_order ASC, mis.id ASC'
        );
        $stmt->execute([$oeuvreId]);

        return ListOf::assocRows(array_map([$this, 'hydrateRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
    }

    /** Textes des suppléments concaténés (pour l’index FTS du numéro). */
    public function concatTextPreviewsForOeuvre(int $oeuvreId): string
    {
        if (!self::isAvailable() || $oeuvreId <= 0) {
            return '';
        }

        $stmt = $this->db->prepare(
            'SELECT pdf_text_preview FROM magazine_issue_supplement
             WHERE oeuvre_id = ? AND TRIM(pdf_text_preview) != \'\'
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$oeuvreId]);
        $parts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $text = trim((string) ($row['pdf_text_preview'] ?? ''));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Accès PDF si le numéro est en collection du foyer ou en envies de l’utilisateur.
     */
    public function userCanAccessStoredObject(int $storedObjectId, int $userId, int $foyerId): bool
    {
        if (!self::isAvailable() || $storedObjectId <= 0 || $userId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM magazine_issue_supplement mis
             INNER JOIN bibliotheque b ON b.oeuvre_id = mis.oeuvre_id
             WHERE mis.stored_object_id = ?
               AND (
                    (b.statut = ? AND b.foyer_id = ?)
                    OR (b.statut = ? AND b.user_id = ?)
               )
             LIMIT 1'
        );
        $stmt->execute([
            $storedObjectId,
            LibraryStatut::COLLECTION,
            $foyerId,
            LibraryStatut::WISHLIST,
            $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return true|string
     */
    public function attachUploadedPdf(
        int $oeuvreId,
        string $tmpPath,
        string $originalName,
        int $fileSize,
        string $label = ''
    ): bool|string {
        if (!self::isAvailable()) {
            return 'Suppléments magazines non disponibles (migration en cours).';
        }

        if ($oeuvreId <= 0 || !$this->oeuvreIsMagazine($oeuvreId)) {
            return 'Numéro magazine introuvable.';
        }

        if ($tmpPath === '' || !is_readable($tmpPath)) {
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
        if (!$this->isPdfMime(is_string($mime) ? $mime : false, $tmpPath)) {
            return 'Le fichier doit être un PDF.';
        }

        $layout = MediaStorage::ensureLayout();
        if ($layout !== true) {
            return (string) $layout;
        }

        $meta = $this->findMagazineMeta($oeuvreId);
        if ($meta === null) {
            return 'Numéro magazine introuvable.';
        }

        $relative = $this->buildRelativePath(
            (string) ($meta['series_titre'] ?? ''),
            (string) ($meta['numero'] ?? ''),
            (string) ($meta['date_parution'] ?? ''),
            !empty($meta['est_hors_serie']),
            $oeuvreId
        );
        if ($relative === false) {
            return 'Chemin de stockage invalide.';
        }

        $absolute = MediaStorage::absolutePath($relative);
        if ($absolute === '') {
            return 'Chemin de stockage invalide.';
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return 'Impossible de créer le dossier médias.';
        }

        if (!@move_uploaded_file($tmpPath, $absolute)) {
            if (!@rename($tmpPath, $absolute) && !@copy($tmpPath, $absolute)) {
                return 'Impossible d’enregistrer le PDF (vérifiez les droits d’écriture).';
            }
        }
        @chmod($absolute, 0640);

        $stored = (new StoredObjectRepository())->create($relative, $fileSize, 'application/pdf');
        if ($stored === null) {
            @unlink($absolute);

            return 'Enregistrement du PDF en base impossible.';
        }

        $storedObjectId = (int) ($stored['id'] ?? 0);
        $label = trim($label);
        $sortOrder = $this->nextSortOrder($oeuvreId);

        $this->db->prepare(
            'INSERT INTO magazine_issue_supplement (
                oeuvre_id, stored_object_id, label, sort_order, original_filename
             ) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $oeuvreId,
            $storedObjectId,
            $label,
            $sortOrder,
            trim($originalName) !== '' ? trim($originalName) : 'supplement.pdf',
        ]);

        $supplementId = (int) $this->db->lastInsertId();
        $this->schedulePostProcessing($supplementId, $oeuvreId, $absolute);

        return true;
    }

    public function deleteById(int $supplementId, int $oeuvreId): bool|string
    {
        if (!self::isAvailable() || $supplementId <= 0 || $oeuvreId <= 0) {
            return 'Supplément introuvable.';
        }

        $stmt = $this->db->prepare(
            'SELECT mis.stored_object_id, mis.cover_url, so.relative_path
             FROM magazine_issue_supplement mis
             INNER JOIN stored_objects so ON so.id = mis.stored_object_id
             WHERE mis.id = ? AND mis.oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$supplementId, $oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return 'Supplément introuvable.';
        }

        $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
        $relativePath = (string) ($row['relative_path'] ?? '');

        $this->db->prepare('DELETE FROM magazine_issue_supplement WHERE id = ? AND oeuvre_id = ?')
            ->execute([$supplementId, $oeuvreId]);

        if ($storedObjectId > 0) {
            (new StoredObjectRepository())->deleteById($storedObjectId);
        }

        if ($relativePath !== '') {
            $absolute = MediaStorage::absolutePath($relativePath);
            if ($absolute !== '' && is_file($absolute)) {
                @unlink($absolute);
            }
        }

        (new PosterStorage())->removeLocalFilesForOeuvreVariant($oeuvreId, 'supp' . $supplementId);

        MagazineIssueFts::upsert($oeuvreId);

        return true;
    }

    public function updateLabel(int $supplementId, int $oeuvreId, string $label): bool|string
    {
        if (!self::isAvailable() || $supplementId <= 0 || $oeuvreId <= 0) {
            return 'Supplément introuvable.';
        }

        $stmt = $this->db->prepare(
            'UPDATE magazine_issue_supplement SET label = ? WHERE id = ? AND oeuvre_id = ?'
        );
        $stmt->execute([trim($label), $supplementId, $oeuvreId]);
        if ($stmt->rowCount() <= 0) {
            return 'Supplément introuvable.';
        }

        return true;
    }

    private function schedulePostProcessing(int $supplementId, int $oeuvreId, string $absolutePdfPath): void
    {
        register_shutdown_function(function () use ($supplementId, $oeuvreId, $absolutePdfPath): void {
            if ($supplementId <= 0 || !is_readable($absolutePdfPath)) {
                return;
            }
            @set_time_limit(300);
            try {
                $this->applyCoverFromPdf($supplementId, $oeuvreId, $absolutePdfPath);
                $this->applyPageCountFromPdf($supplementId, $absolutePdfPath);
                $this->indexPdfTextPreviewFromFile($supplementId, $oeuvreId, $absolutePdfPath);
            } catch (\Throwable $e) {
                error_log('MagazineIssueSupplementRepository::schedulePostProcessing: ' . $e->getMessage());
            }
        });
    }

    private function applyCoverFromPdf(int $supplementId, int $oeuvreId, string $absolutePdfPath): void
    {
        if (!MagazinePdfCoverExtractor::isAvailable()) {
            return;
        }

        $binary = MagazinePdfCoverExtractor::renderFirstPageJpeg($absolutePdfPath);
        if ($binary === '') {
            return;
        }

        $variant = 'supp' . $supplementId;
        $webPath = (new PosterStorage())->importBinaryForOeuvreVariant($oeuvreId, $binary, $variant);
        if ($webPath === '') {
            return;
        }

        $this->db->prepare('UPDATE magazine_issue_supplement SET cover_url = ? WHERE id = ?')
            ->execute([$webPath, $supplementId]);
    }

    private function applyPageCountFromPdf(int $supplementId, string $absolutePdfPath): void
    {
        if (!MagazinePdfInfo::isAvailable()) {
            return;
        }

        $pageCount = MagazinePdfInfo::readPageCount($absolutePdfPath);
        if ($pageCount <= 0) {
            return;
        }

        $this->db->prepare('UPDATE magazine_issue_supplement SET pages = ? WHERE id = ?')
            ->execute([$pageCount, $supplementId]);
    }

    private function indexPdfTextPreviewFromFile(int $supplementId, int $oeuvreId, string $absolutePdfPath): void
    {
        $text = MagazinePdfTextExtractor::extractFirstPages($absolutePdfPath);
        $this->db->prepare('UPDATE magazine_issue_supplement SET pdf_text_preview = ? WHERE id = ?')
            ->execute([$text, $supplementId]);
        MagazineIssueFts::upsert($oeuvreId);
    }

    private function nextSortOrder(int $oeuvreId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM magazine_issue_supplement WHERE oeuvre_id = ?'
        );
        $stmt->execute([$oeuvreId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return string|false */
    private function buildRelativePath(
        string $seriesTitle,
        string $numero,
        string $dateParution,
        bool $horsSerie,
        int $oeuvreId
    ): string|false {
        if ($oeuvreId <= 0) {
            return false;
        }

        $seriesSlug = MagazineNumeroOrdre::slugifyForPath($seriesTitle, 'revue');
        $numeroSlug = MagazineNumeroOrdre::slugifyForPath($numero, 'numero');
        $year = MagazineNumeroOrdre::extractParutionYear($dateParution);
        $hsPart = $horsSerie ? '-hs' : '';
        $uniq = bin2hex(random_bytes(4));
        $fileName = $seriesSlug . '-' . $numeroSlug . $hsPart . '-id' . $oeuvreId . '-supp-' . $uniq . '.pdf';

        return MediaStorage::relativePath('magazine', $seriesSlug, $year, $fileName);
    }

    /** @return array<string, mixed>|null */
    private function findMagazineMeta(int $oeuvreId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.titre AS series_titre, om.numero, om.date_parution, om.est_hors_serie
             FROM oeuvre_magazine om
             INNER JOIN series s ON s.id = om.series_id
             WHERE om.oeuvre_id = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function oeuvreIsMagazine(int $oeuvreId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM oeuvres o
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = o.id
             WHERE o.id = ? AND o.media_domain = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, MediaDomain::MAGAZINE]);

        return (bool) $stmt->fetchColumn();
    }

    private function isPdfMime(string|false $mime, string $path): bool
    {
        if (is_string($mime) && ($mime === 'application/pdf' || $mime === 'application/x-pdf')) {
            return true;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = (string) fread($handle, 5);
        fclose($handle);

        return str_starts_with($header, '%PDF');
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['oeuvre_id'] = (int) ($row['oeuvre_id'] ?? 0);
        $row['stored_object_id'] = (int) ($row['stored_object_id'] ?? 0);
        $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
        $row['pages'] = (int) ($row['pages'] ?? 0);
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['cover_url'] = trim((string) ($row['cover_url'] ?? ''));
        $row['label'] = trim((string) ($row['label'] ?? ''));
        $row['original_filename'] = (string) ($row['original_filename'] ?? '');
        $row['display_label'] = $row['label'] !== ''
            ? $row['label']
            : ($row['original_filename'] !== '' ? $row['original_filename'] : 'Supplément');
        $row['pdf_url'] = $row['stored_object_id'] > 0
            ? '/media-object.php?id=' . $row['stored_object_id']
            : '';
        $row['size_label'] = UploadLimits::formatBytesLabel($row['size_bytes']);

        return $row;
    }
}
