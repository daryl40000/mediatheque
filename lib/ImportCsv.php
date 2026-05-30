<?php
/**
 * Import CSV — export catalogue ou bibliothèque (détection automatique).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportCsv
{
    public function __construct(
        private readonly ImportRunner $runner = new ImportRunner()
    ) {
    }

    /**
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public function importFromPath(
        string $path,
        string $delimiter = MONCINE_CSV_DELIMITER,
        bool $replaceCatalog = false,
        bool $systemInstallSeed = false
    ): array {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['imported' => 0, 'vues' => 0, 'errors' => ['Impossible d\'ouvrir le fichier.']];
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return ['imported' => 0, 'vues' => 0, 'errors' => ['Fichier vide ou invalide.']];
        }

        $header = array_map(
            static fn ($cell): ?string => $cell === null
                ? null
                : ImportFilmRows::stripHeaderWrapping((string) $cell),
            $header
        );

        $dataRows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $dataRows[] = $row;
        }
        fclose($handle);

        return $this->runner->importFilmsSheet($dataRows, $header, $replaceCatalog, $systemInstallSeed);
    }

    /** Convertit « 1h56 », « 2h30 », « 90 » en minutes. */
    public static function parseDurationMinutes(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }

        if (preg_match('/^(\d+)\s*h\s*(\d{1,2})\s*$/iu', $raw, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }

        if (preg_match('/^(\d+)\s*h\s*$/iu', $raw, $m)) {
            return (int) $m[1] * 60;
        }

        if (preg_match('/^(\d+)\s*min/iu', $raw, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^\d+$/', $raw)) {
            return (int) $raw;
        }

        return 0;
    }

    /** Date au format jj/mm/aaaa → yyyy-mm-dd pour SQLite. */
    public static function parseVueDate(string $raw): ?string
    {
        return HistoriqueRepository::parseVueDate($raw);
    }

    /** Note sur 10. */
    public static function parseNote(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $note = (int) round((float) $raw);
        if ($note < 1) {
            return null;
        }

        return min($note, 10);
    }
}
