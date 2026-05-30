<?php
/**
 * Export du catalogue partagé (admin) + archive des affiches locales.
 */

declare(strict_types=1);

namespace Moncine;

final class ExportCatalog
{
    public function __construct(
        private readonly OeuvreRepository $oeuvres = new OeuvreRepository()
    ) {
    }

    public function catalogEntryCount(): int
    {
        if (!CatalogSchema::usesCatalogTables(Database::getInstance())) {
            return 0;
        }

        $stmt = Database::getInstance()->query('SELECT COUNT(*) FROM oeuvres');

        return (int) $stmt->fetchColumn();
    }

    public function sendCsvDownload(): void
    {
        $rows = [CatalogExportSchema::headers()];
        foreach ($this->oeuvres->findAllForExport() as $oeuvre) {
            $rows[] = CatalogExportSchema::rowToExport($oeuvre);
        }

        ExportSpreadsheet::sendCsv(
            $rows,
            ExportSpreadsheet::buildFilename('moncine-catalogue', 'csv')
        );
    }

    public function sendOdsDownload(): void
    {
        $rows = [CatalogExportSchema::headers()];
        foreach ($this->oeuvres->findAllForExport() as $oeuvre) {
            $rows[] = CatalogExportSchema::rowToExport($oeuvre);
        }

        ExportSpreadsheet::sendOds([
            CatalogExportSchema::SHEET_CATALOGUE => $rows,
        ], ExportSpreadsheet::buildFilename('moncine-catalogue', 'ods'));
    }

    /** Archive ZIP des fichiers dans www/posters/ référencés par le catalogue. */
    public function sendPostersZipDownload(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extension PHP ZipArchive requise pour l’archive des affiches.');
        }

        $dir = PosterStorage::postersFilesystemDir();
        if (!is_dir($dir)) {
            throw new \RuntimeException('Dossier des affiches introuvable.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'moncine_posters_');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer l’archive temporaire.');
        }

        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l’archive ZIP.');
        }

        $added = 0;
        foreach ($this->oeuvres->findAllForExport() as $oeuvre) {
            $oeuvreId = (int) ($oeuvre['id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $path = $dir . '/' . $oeuvreId . '.' . $ext;
                if (!is_file($path)) {
                    continue;
                }
                $zip->addFile($path, 'posters/' . $oeuvreId . '.' . $ext);
                $added++;
                break;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            throw new \RuntimeException('Aucune affiche locale à exporter (dossier posters/ vide).');
        }

        header('Content-Type: application/zip');
        header(
            'Content-Disposition: attachment; filename="'
            . ExportSpreadsheet::buildFilename('moncine-affiches', 'zip')
            . '"'
        );
        header('Content-Length: ' . (string) filesize($zipPath));
        header('Cache-Control: no-store');

        readfile($zipPath);
        @unlink($zipPath);
    }
}
