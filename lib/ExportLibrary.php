<?php
/**
 * Export bibliothèque personnelle (léger) : collection + envies + historique.
 */

declare(strict_types=1);

namespace Moncine;

final class ExportLibrary
{
    public function __construct(
        private readonly FilmRepository $films = new FilmRepository()
    ) {
    }

    public function libraryEntryCount(): int
    {
        if (!$this->films->usesCatalogModel()) {
            return $this->films->count();
        }

        return (new CatalogFilmRepository())->countLibraryEntries();
    }

    /**
     * @return array<string, int>
     */
    public function libraryEntryCountByDomain(): array
    {
        if (!$this->films->usesCatalogModel()) {
            return [MediaDomain::FILM => $this->films->count()];
        }

        return (new CatalogFilmRepository())->countLibraryEntriesByDomain();
    }

    public function sendCsvDownload(): void
    {
        $rows = [LibraryExportSchema::headers()];
        foreach ($this->libraryRows() as $film) {
            $rows[] = LibraryExportSchema::rowToExport($film);
        }

        ExportSpreadsheet::sendCsv(
            $rows,
            ExportSpreadsheet::buildFilename('moncine-bibliotheque', 'csv')
        );
    }

    public function sendOdsDownload(): void
    {
        $bibRows = [LibraryExportSchema::headers()];
        foreach ($this->libraryRows() as $film) {
            $bibRows[] = LibraryExportSchema::rowToExport($film);
        }

        $histRows = [LibraryExportSchema::HISTORIQUE_HEADERS];
        foreach ((new HistoriqueRepository())->findAllWithFilmTitles() as $row) {
            $histRows[] = [
                (string) (int) ($row['oeuvre_id'] ?? 0),
                (string) ($row['titre'] ?? ''),
                (string) ($row['realisateur'] ?? ''),
                ExportCollection::formatVueDateForExport((string) ($row['date_vue'] ?? '')),
                $row['note'] !== null && $row['note'] !== '' ? (string) $row['note'] : '',
            ];
        }

        ExportSpreadsheet::sendOds([
            LibraryExportSchema::SHEET_BIBLIOTHEQUE => $bibRows,
            LibraryExportSchema::SHEET_HISTORIQUE => $histRows,
        ], ExportSpreadsheet::buildFilename('moncine-bibliotheque', 'ods'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function libraryRows(): array
    {
        if ($this->films->usesCatalogModel()) {
            return (new CatalogFilmRepository())->findAllLibraryForExport();
        }

        return $this->films->findAllForExport();
    }
}
