<?php
/**
 * Importe les feuilles Films et Historique (CSV ou ODS export Moncine).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportRunner
{
    public function __construct(
        private readonly FilmRepository $films = new FilmRepository(),
        private readonly HistoriqueRepository $historique = new HistoriqueRepository(),
        private readonly OeuvreRepository $oeuvres = new OeuvreRepository()
    ) {
    }

    /**
     * @param list<list<string|null>> $dataRows lignes sans l’en-tête
     * @param list<string|null> $header
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public function importFilmsSheet(
        array $dataRows,
        array $header,
        bool $replaceCatalog = false,
        bool $systemInstallSeed = false
    ): array {
        $analysis = ImportFormat::analyzeHeader($header);

        $result = match ($analysis['format']) {
            ImportFormat::KIND_LIBRARY => $this->importLibrarySheet($dataRows, $header),
            ImportFormat::KIND_CATALOG => $this->importCatalogSheet(
                $dataRows,
                $header,
                $replaceCatalog,
                $systemInstallSeed
            ),
            default => [
                'imported' => 0,
                'vues' => 0,
                'errors' => [
                    'Format de fichier non reconnu. Utilisez l’export « CSV catalogue » (admin) '
                    . 'ou « CSV bibliothèque » depuis Moncine.',
                ],
                'format' => ImportFormat::KIND_UNKNOWN,
                'format_label' => ImportFormat::label(ImportFormat::KIND_UNKNOWN),
                'has_id_column' => false,
            ],
        };

        return $this->withImportMeta($result, $analysis, $replaceCatalog);
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public function importLibrarySheet(array $dataRows, array $header): array
    {
        $map = ImportFilmRows::mapHeaders($header, LibraryExportSchema::COLUMN_ALIASES);
        if (!isset($map['oeuvre_id']) && !isset($map['titre'])) {
            return [
                'imported' => 0,
                'vues' => 0,
                'errors' => ['Colonne « ID catalogue » ou « Titre » requise.'],
            ];
        }

        $imported = 0;
        $vues = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }
            try {
                $parsed = ImportLibraryRows::rowToLibrary($row, $map);
                if ((int) ($parsed['oeuvre_id'] ?? 0) <= 0 && trim((string) ($parsed['titre'] ?? '')) === '') {
                    continue;
                }

                $vuRaw = (string) ($parsed['_vu'] ?? '');
                $noteRaw = (string) ($parsed['_note'] ?? '');
                $importColumns = (array) ($parsed['_import_columns'] ?? array_keys($map));
                unset($parsed['_vu'], $parsed['_note'], $parsed['_import_columns']);
                if ((int) ($parsed['oeuvre_id'] ?? 0) > 0) {
                    unset($parsed['bibliotheque_id']);
                }

                $this->films->upsertLibraryFromExport($parsed, $importColumns);
                $imported++;

                $film = $this->resolveLibraryFilmAfterImport($parsed);
                if ($film === null) {
                    continue;
                }

                $filmId = (int) $film['id'];
                $dateVue = ImportCsv::parseVueDate($vuRaw);
                if ($dateVue !== null) {
                    $note = ImportCsv::parseNote($noteRaw);
                    if ($this->historique->recordViewing($filmId, $dateVue, $note)) {
                        $vues++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'vues' => $vues, 'errors' => $errors];
    }

    /**
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public function importCatalogSheet(
        array $dataRows,
        array $header,
        bool $replaceCatalog = false,
        bool $systemInstallSeed = false
    ): array {
        if (!$systemInstallSeed && !CatalogAdmin::canAccess()) {
            return [
                'imported' => 0,
                'vues' => 0,
                'errors' => ['L’import catalogue est réservé à l’administrateur.'],
            ];
        }

        $map = ImportFilmRows::mapHeaders($header, CatalogExportSchema::COLUMN_ALIASES);
        if (!isset($map['titre'])) {
            return [
                'imported' => 0,
                'vues' => 0,
                'errors' => ['Colonne « Titre » introuvable (export catalogue).'],
            ];
        }

        if (!isset($map['oeuvre_id'])) {
            return [
                'imported' => 0,
                'vues' => 0,
                'errors' => [
                    'Colonne « ID catalogue » introuvable. Utilisez l’export « CSV catalogue » (admin), '
                    . 'pas l’export bibliothèque ni l’ancien export complet.',
                ],
            ];
        }

        $admin = new CatalogAdmin();
        if ($replaceCatalog) {
            $admin->clearCatalogForImport();
        }

        $imported = 0;
        $errors = [];
        $line = 1;
        $hadExplicitIds = false;
        $missingIdLines = 0;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }
            try {
                $parsed = ImportCatalogRows::rowToOeuvre($row, $map);
                if (trim((string) ($parsed['titre'] ?? '')) === '') {
                    continue;
                }

                $oeuvreId = (int) ($parsed['oeuvre_id'] ?? 0);
                if ($oeuvreId <= 0) {
                    $missingIdLines++;
                    $errors[] = 'Catalogue ligne ' . $line . ' : ID catalogue manquant ou invalide.';
                    continue;
                }

                $hadExplicitIds = true;
                $importColumns = (array) ($parsed['_import_columns'] ?? array_keys($map));
                unset($parsed['_import_columns']);
                $admin->importOeuvreFromExport($parsed, $importColumns);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'Catalogue ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        if ($hadExplicitIds) {
            $this->oeuvres->syncAutoincrementSequence();
        }

        if ($missingIdLines > 0 && $imported === 0) {
            $errors[] = 'Aucune ligne importée : vérifiez que la colonne « ID catalogue » contient bien les numéros '
                . 'de l’ancienne instance (pas un export réalisé après la première importation).';
        }

        $sheet = ['imported' => $imported, 'vues' => 0, 'errors' => $errors, 'has_id_column' => true];

        return $this->withSheetMeta($sheet, ImportFormat::KIND_CATALOG, $replaceCatalog);
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>|null
     */
    private function resolveLibraryFilmAfterImport(array $parsed): ?array
    {
        $libraryId = (int) ($parsed['bibliotheque_id'] ?? 0);
        if ($libraryId > 0) {
            return $this->films->findById($libraryId);
        }

        $oeuvreId = (int) ($parsed['oeuvre_id'] ?? 0);
        if ($oeuvreId > 0 && $this->films->usesCatalogModel()) {
            $repo = new CatalogFilmRepository();
            $library = (new BibliothequeRepository())->findByOeuvreId(
                $oeuvreId,
                UserContext::currentUserId(),
                UserContext::currentFoyerId()
            );
            if ($library !== null) {
                return $repo->findById((int) $library['id']);
            }
        }

        return $this->films->findByTitreAndRealisateur(
            (string) ($parsed['titre'] ?? ''),
            (string) ($parsed['realisateur'] ?? '')
        );
    }

    /**
     * Feuille Historique de l’export ODS (toutes les visions).
     *
     * @param list<list<string|null>> $dataRows
     * @param list<string|null> $header
     * @return array{vues: int, errors: list<string>}
     */
    public function importHistoriqueSheet(array $dataRows, array $header): array
    {
        $map = ImportFilmRows::mapHeaders($header, LibraryExportSchema::HISTORIQUE_COLUMN_ALIASES);
        if (!isset($map['date_vue']) || (!isset($map['titre']) && !isset($map['oeuvre_id']))) {
            $map = ImportFilmRows::mapHeaders($header, ImportFilmRows::HISTORIQUE_MAP);
        }

        if (!isset($map['date_vue']) || (!isset($map['titre']) && !isset($map['oeuvre_id']))) {
            return [
                'vues' => 0,
                'errors' => ['Feuille Historique : « Date vue » et « Titre » ou « ID catalogue » requis.'],
            ];
        }

        $vues = 0;
        $errors = [];
        $line = 1;

        foreach ($dataRows as $row) {
            $line++;
            if (ImportFilmRows::isEmptyRow($row)) {
                continue;
            }
            try {
                $film = $this->resolveFilmForHistoriqueRow($row, $map);
                if ($film === null) {
                    $label = ImportFilmRows::getCell($row, $map, 'titre');
                    if ($label === '' && isset($map['oeuvre_id'])) {
                        $label = 'ID ' . ImportFilmRows::getCell($row, $map, 'oeuvre_id');
                    }
                    $errors[] = 'Historique ligne ' . $line . ' : entrée introuvable (« ' . $label . ' »).';
                    continue;
                }

                $dateIso = ImportCsv::parseVueDate(ImportFilmRows::getCell($row, $map, 'date_vue'));
                if ($dateIso === null) {
                    $errors[] = 'Historique ligne ' . $line . ' : date invalide.';
                    continue;
                }

                $note = ImportCsv::parseNote(ImportFilmRows::getCell($row, $map, 'note'));
                if ($this->historique->recordViewing((int) $film['id'], $dateIso, $note)) {
                    $vues++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'Historique ligne ' . $line . ' : ' . $e->getMessage();
            }
        }

        return ['vues' => $vues, 'errors' => $errors];
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     * @return array<string, mixed>|null
     */
    private function resolveFilmForHistoriqueRow(array $row, array $map): ?array
    {
        if (isset($map['oeuvre_id'])) {
            $raw = trim((string) ($row[$map['oeuvre_id']] ?? ''));
            if ($raw !== '' && preg_match('/^\d+$/', $raw)) {
                $oeuvreId = (int) $raw;
                if ($this->films->usesCatalogModel()) {
                    $library = (new BibliothequeRepository())->findByOeuvreId(
                        $oeuvreId,
                        UserContext::currentUserId(),
                        UserContext::currentFoyerId()
                    );
                    if ($library !== null) {
                        return (new CatalogFilmRepository())->findById((int) $library['id']);
                    }
                }
            }
        }

        $titre = ImportFilmRows::getCell($row, $map, 'titre');
        if ($titre === '') {
            return null;
        }

        return $this->films->findByTitreAndRealisateur(
            $titre,
            ImportFilmRows::getCell($row, $map, 'realisateur')
        );
    }

    /**
     * @param array{imported: int, vues: int, errors: list<string>} $a
     * @param array{imported?: int, vues?: int, errors?: list<string>} $b
     * @return array{imported: int, vues: int, errors: list<string>}
     */
    public static function mergeResults(array $a, array $b): array
    {
        return [
            'imported' => ($a['imported'] ?? 0) + ($b['imported'] ?? 0),
            'vues' => ($a['vues'] ?? 0) + ($b['vues'] ?? 0),
            'errors' => array_merge($a['errors'] ?? [], $b['errors'] ?? []),
            'format' => $b['format'] ?? $a['format'] ?? '',
            'format_label' => $b['format_label'] ?? $a['format_label'] ?? '',
            'has_id_column' => (bool) ($b['has_id_column'] ?? $a['has_id_column'] ?? false),
            'catalog_cleared' => (bool) ($b['catalog_cleared'] ?? $a['catalog_cleared'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array{format: string, has_id_column: bool, label: string} $analysis
     * @return array<string, mixed>
     */
    private function withImportMeta(array $result, array $analysis, bool $replaceCatalog): array
    {
        $result['format'] = $analysis['format'];
        $result['format_label'] = $analysis['label'];
        $result['has_id_column'] = $analysis['has_id_column'];
        $result['catalog_cleared'] = (bool) ($result['catalog_cleared'] ?? false)
            || ($replaceCatalog && $analysis['format'] === ImportFormat::KIND_CATALOG);
        if (!array_key_exists('has_id_column', $result)) {
            $result['has_id_column'] = $analysis['has_id_column'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function withSheetMeta(
        array $result,
        string $format,
        bool $replaceCatalog,
        bool $catalogCleared = false
    ): array {
        $result['format'] = $format;
        $result['format_label'] = ImportFormat::label($format);
        $result['catalog_cleared'] = $catalogCleared
            || ($replaceCatalog && $format === ImportFormat::KIND_CATALOG);
        if (!array_key_exists('has_id_column', $result)) {
            $result['has_id_column'] = false;
        }

        return $result;
    }
}
