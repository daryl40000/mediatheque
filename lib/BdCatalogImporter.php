<?php
/**
 * Import catalogue BD / Manga depuis un CSV (une ligne = un tome).
 *
 * Catalogue partagé uniquement par défaut ; option d’ajout à la bibliothèque.
 */

declare(strict_types=1);

namespace Moncine;

final class BdCatalogImporter
{
    /** @var array<string, list<string>> Alias déjà normalisés (minuscules, sans accents, `_` → espace). */
    public const COLUMN_ALIASES = [
        'serie' => ['serie', 'series', 'titre serie', 'series titre'],
        'kind' => ['kind', 'type', 'format'],
        'editeur_serie' => ['editeur serie', 'editeur series'],
        'tome_numero' => ['tome numero', 'numero', 'n°', 'n', 'tome'],
        'tome_ordre' => ['tome ordre', 'ordre'],
        'tome_label' => ['tome label', 'label', 'libelle'],
        'hors_serie' => ['hors serie', 'hs', 'est hors serie'],
        'titre' => ['titre', 'titre tome'],
        'annee' => ['annee', 'year'],
        'scenariste' => ['scenariste', 'scenario', 'auteur'],
        'dessinateur' => ['dessinateur', 'dessin'],
        'editeur' => ['editeur'],
        'genre' => ['genre'],
        'synopsis' => ['synopsis', 'resume'],
        'support' => ['support', 'support physique'],
    ];

    /**
     * @param array{
     *   dry_run?: bool,
     *   skip_existing?: bool,
     *   add_to_library?: bool,
     *   user_id?: int,
     *   foyer_id?: int,
     *   library_statut?: string
     * } $options
     * @return array{
     *   dry_run: bool,
     *   series_created: int,
     *   series_reused: int,
     *   tomes_created: int,
     *   tomes_skipped: int,
     *   library_attached: int,
     *   errors: list<string>
     * }
     */
    public function importFromPath(string $path, array $options = []): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return $this->emptyResult(['Fichier introuvable ou illisible.']);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return $this->emptyResult(['Impossible d’ouvrir le fichier.']);
        }

        $delimiter = MONCINE_CSV_DELIMITER;
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);

            return $this->emptyResult(['Fichier vide ou invalide.']);
        }

        $header = array_map(
            static fn ($cell): ?string => $cell === null
                ? null
                : ImportFilmRows::stripHeaderWrapping((string) $cell),
            $header
        );

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $this->importRows($rows, $header, $options);
    }

    /**
     * @param list<list<string|null>> $rows
     * @param list<string|null> $header
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function importRows(array $rows, array $header, array $options = []): array
    {
        if (!BdRepository::isAvailable()) {
            return $this->emptyResult(['Module BD non disponible.']);
        }

        $map = ImportFilmRows::mapHeaders($header, self::COLUMN_ALIASES);
        if (!isset($map['serie'])) {
            return $this->emptyResult([
                'Colonne « serie » manquante. En-têtes attendus : serie;kind;tome_numero;…',
            ]);
        }

        $dryRun = !empty($options['dry_run']);
        $skipExisting = ($options['skip_existing'] ?? true) !== false;
        $addToLibrary = !empty($options['add_to_library']);
        $userId = max(0, (int) ($options['user_id'] ?? 0));
        $foyerId = max(0, (int) ($options['foyer_id'] ?? 0));
        $statut = LibraryStatut::normalize(
            (string) ($options['library_statut'] ?? LibraryStatut::COLLECTION)
        );

        if ($addToLibrary && ($userId <= 0 || $foyerId <= 0)) {
            return $this->emptyResult([
                'Ajout à la collection demandé mais utilisateur / foyer manquant.',
            ]);
        }

        $result = $this->emptyResult();
        $result['dry_run'] = $dryRun;

        $seriesRepo = new SeriesRepository();
        $bdRepo = new BdRepository();
        /** @var array<string, int> $seriesCache titre_lower => id */
        $seriesCache = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNo = $lineIndex + 2; // +1 header, +1 index 0
            if (!is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            $parsed = $this->parseRow($row, $map);
            if (is_string($parsed)) {
                $result['errors'][] = 'Ligne ' . $lineNo . ' : ' . $parsed;
                continue;
            }

            $serieTitre = $parsed['serie'];
            $serieKey = mb_strtolower($serieTitre, 'UTF-8');

            if (!isset($seriesCache[$serieKey])) {
                $existingSeries = $seriesRepo->findByTitre($serieTitre, MediaDomain::BD);
                if ($existingSeries !== null) {
                    $seriesCache[$serieKey] = (int) $existingSeries['id'];
                    $result['series_reused']++;
                } elseif ($dryRun) {
                    $seriesCache[$serieKey] = -1; // marqueur dry-run
                    $result['series_created']++;
                } else {
                    $created = $seriesRepo->create([
                        'titre' => $serieTitre,
                        'publication_type' => 'irregulier',
                        'editeur' => $parsed['editeur_serie'],
                        'tags' => BdSeriesMetadata::kindForStorage($parsed['kind']),
                    ], MediaDomain::BD);
                    if (!is_int($created)) {
                        $result['errors'][] = 'Ligne ' . $lineNo . ' : ' . (string) $created;
                        continue;
                    }
                    $seriesCache[$serieKey] = $created;
                    $result['series_created']++;
                }
            } elseif ($seriesCache[$serieKey] > 0 || $seriesCache[$serieKey] === -1) {
                // déjà compté créé/réutilisé
            }

            $seriesId = $seriesCache[$serieKey];
            if ($seriesId === -1) {
                // Dry-run : série fictive — on compte le tome comme créé
                $result['tomes_created']++;
                continue;
            }

            $existingTomeId = $bdRepo->findCatalogTomeId(
                $seriesId,
                $parsed['tome_numero'],
                $parsed['est_hors_serie']
            );
            if ($existingTomeId !== null && $skipExisting) {
                $result['tomes_skipped']++;
                if ($addToLibrary && !$dryRun) {
                    $attached = $bdRepo->addFromCatalogOeuvre(
                        $existingTomeId,
                        $statut,
                        $userId,
                        $foyerId,
                        ['support_physique' => $parsed['support_physique']]
                    );
                    if (is_int($attached)) {
                        $result['library_attached']++;
                    }
                }
                continue;
            }
            if ($existingTomeId !== null && !$skipExisting) {
                $result['errors'][] = 'Ligne ' . $lineNo . ' : tome déjà présent ( cochéz « ignorer existants » ).';
                continue;
            }

            if ($dryRun) {
                $result['tomes_created']++;
                continue;
            }

            $payload = [
                'series_id' => $seriesId,
                'kind' => $parsed['kind'],
                'tome_numero' => $parsed['tome_numero'],
                'tome_ordre' => $parsed['tome_ordre'],
                'tome_label' => $parsed['tome_label'],
                'est_hors_serie' => $parsed['est_hors_serie'],
                'titre' => $parsed['titre'],
                'annee' => $parsed['annee'],
                'scenariste' => $parsed['scenariste'],
                'dessinateur' => $parsed['dessinateur'],
                'editeur' => $parsed['editeur'],
                'genre' => $parsed['genre'],
                'synopsis' => $parsed['synopsis'],
                'support_physique' => $parsed['support_physique'],
            ];

            if ($addToLibrary) {
                $bibId = $bdRepo->createTomeWithLibrary($seriesId, $payload, $statut, $userId, $foyerId);
                if (!is_int($bibId)) {
                    $result['errors'][] = 'Ligne ' . $lineNo . ' : ' . (string) $bibId;
                    continue;
                }
                $result['tomes_created']++;
                $result['library_attached']++;
            } else {
                $oeuvreId = $bdRepo->createCatalogOnly($payload);
                if (!is_int($oeuvreId)) {
                    $result['errors'][] = 'Ligne ' . $lineNo . ' : ' . (string) $oeuvreId;
                    continue;
                }
                $result['tomes_created']++;
            }
        }

        return $result;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     * @return array{
     *   serie: string,
     *   kind: string,
     *   editeur_serie: string,
     *   tome_numero: int,
     *   tome_ordre: float|string,
     *   tome_label: string,
     *   est_hors_serie: bool,
     *   titre: string,
     *   annee: int,
     *   scenariste: string,
     *   dessinateur: string,
     *   editeur: string,
     *   genre: string,
     *   synopsis: string,
     *   support_physique: string
     * }|string
     */
    public function parseRow(array $row, array $map): array|string
    {
        $serie = trim(ImportFilmRows::getCell($row, $map, 'serie'));
        if ($serie === '') {
            return 'titre de série vide.';
        }

        $tomeLabel = trim(ImportFilmRows::getCell($row, $map, 'tome_label'));
        $titre = trim(ImportFilmRows::getCell($row, $map, 'titre'));
        $tomeNumeroRaw = trim(ImportFilmRows::getCell($row, $map, 'tome_numero'));
        $tomeNumero = $tomeNumeroRaw !== '' && is_numeric($tomeNumeroRaw)
            ? max(0, (int) $tomeNumeroRaw)
            : 0;

        if ($tomeNumeroRaw === '' && $tomeLabel === '' && $titre === '') {
            return 'indiquez un numéro de tome, un libellé ou un titre.';
        }

        $ordreRaw = trim(ImportFilmRows::getCell($row, $map, 'tome_ordre'));
        $tomeOrdre = $ordreRaw !== '' && is_numeric($ordreRaw) ? $ordreRaw : $tomeNumero;

        $anneeRaw = trim(ImportFilmRows::getCell($row, $map, 'annee'));
        $annee = ($anneeRaw !== '' && preg_match('/^\d{4}$/', $anneeRaw)) ? (int) $anneeRaw : 0;

        $kind = BdKind::normalize(ImportFilmRows::getCell($row, $map, 'kind'));
        $horsSerie = self::parseBool(ImportFilmRows::getCell($row, $map, 'hors_serie'));
        $support = BdPhysicalSupport::normalize(ImportFilmRows::getCell($row, $map, 'support'));

        return [
            'serie' => $serie,
            'kind' => $kind,
            'editeur_serie' => trim(ImportFilmRows::getCell($row, $map, 'editeur_serie')),
            'tome_numero' => $tomeNumero,
            'tome_ordre' => $tomeOrdre,
            'tome_label' => $tomeLabel,
            'est_hors_serie' => $horsSerie,
            'titre' => $titre,
            'annee' => $annee,
            'scenariste' => trim(ImportFilmRows::getCell($row, $map, 'scenariste')),
            'dessinateur' => trim(ImportFilmRows::getCell($row, $map, 'dessinateur')),
            'editeur' => trim(ImportFilmRows::getCell($row, $map, 'editeur')),
            'genre' => trim(ImportFilmRows::getCell($row, $map, 'genre')),
            'synopsis' => trim(ImportFilmRows::getCell($row, $map, 'synopsis')),
            'support_physique' => $support,
        ];
    }

    public static function parseBool(string $raw): bool
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');
        if ($raw === '') {
            return false;
        }

        return in_array($raw, ['1', 'oui', 'yes', 'true', 'vrai', 'hs', 'x'], true);
    }

    /** @param list<string|null> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $errors
     * @return array{
     *   dry_run: bool,
     *   series_created: int,
     *   series_reused: int,
     *   tomes_created: int,
     *   tomes_skipped: int,
     *   library_attached: int,
     *   errors: list<string>
     * }
     */
    private function emptyResult(array $errors = []): array
    {
        return [
            'dry_run' => false,
            'series_created' => 0,
            'series_reused' => 0,
            'tomes_created' => 0,
            'tomes_skipped' => 0,
            'library_attached' => 0,
            'errors' => $errors,
        ];
    }
}
