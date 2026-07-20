<?php
/**
 * Extensions catalogue par domaine média (jeu, magazine, BD) — export / import admin.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogDomainExtensions
{
    /** Colonnes exportées en plus des champs œuvre communs. */
    public const EXTENSION_COLUMNS = [
        'media_domain' => 'Domaine média',
        'jeu_studio' => 'Jeu — studio',
        'jeu_editeur' => 'Jeu — éditeur',
        'jeu_genre' => 'Jeu — genre',
        'jeu_platform' => 'Jeu — plateforme',
        'jeu_is_digital' => 'Jeu — démat',
        'jeu_physical_supports' => 'Jeu — supports physiques',
        'jeu_digital_stores' => 'Jeu — magasins démat',
        'jeu_is_extension' => 'Jeu — extension',
        'jeu_base_game_oeuvre_id' => 'Jeu — ID jeu de base',
        'jeu_is_remake' => 'Jeu — remake',
        'jeu_original_game_oeuvre_id' => 'Jeu — ID jeu d\'origine',
        'mag_series_id' => 'Magazine — ID série',
        'mag_numero' => 'Magazine — numéro',
        'mag_numero_ordre' => 'Magazine — ordre',
        'mag_date_parution' => 'Magazine — date parution',
        'mag_sommaire' => 'Magazine — sommaire',
        'mag_pages' => 'Magazine — pages',
        'mag_est_hors_serie' => 'Magazine — hors-série',
        'bd_series_id' => 'BD — ID série',
        'bd_series_titre' => 'BD — titre série',
        'bd_kind' => 'BD — type',
        'bd_tome_numero' => 'BD — tome n°',
        'bd_tome_ordre' => 'BD — ordre',
        'bd_tome_label' => 'BD — libellé tome',
        'bd_est_hors_serie' => 'BD — hors-série',
        'bd_scenariste' => 'BD — scénariste',
        'bd_dessinateur' => 'BD — dessinateur',
        'bd_editeur' => 'BD — éditeur',
        'bd_genre' => 'BD — genre',
    ];

    /** @var array<string, list<string>> */
    public const COLUMN_ALIASES = [
        'media_domain' => [
            'domaine media',
            'domaine média',
            'media_domain',
            'media domain',
            'type media',
            'type média',
            'domaine',
        ],
        'jeu_studio' => ['jeu studio', 'jeu_studio', 'studio jeu', 'studio'],
        'jeu_editeur' => ['jeu editeur', 'jeu éditeur', 'jeu_editeur', 'editeur jeu'],
        'jeu_genre' => ['jeu genre', 'jeu_genre', 'genre jeu'],
        'jeu_platform' => ['jeu plateforme', 'jeu_platform', 'plateforme jeu', 'platform jeu'],
        'jeu_is_digital' => ['jeu demat', 'jeu démat', 'jeu_is_digital', 'jeu digital'],
        'jeu_physical_supports' => ['jeu supports physiques', 'jeu_physical_supports'],
        'jeu_digital_stores' => ['jeu magasins demat', 'jeu_digital_stores'],
        'jeu_is_extension' => ['jeu extension', 'jeu_is_extension', 'extension'],
        'jeu_base_game_oeuvre_id' => ['jeu id jeu de base', 'jeu_base_game_oeuvre_id', 'base_game_oeuvre_id'],
        'jeu_is_remake' => ['jeu remake', 'jeu_is_remake', 'remake'],
        'jeu_original_game_oeuvre_id' => ['jeu id jeu origine', 'jeu_original_game_oeuvre_id', 'original_game_oeuvre_id'],
        'mag_series_id' => ['magazine id serie', 'magazine id série', 'mag_series_id', 'series_id magazine'],
        'mag_numero' => ['magazine numero', 'magazine numéro', 'mag_numero'],
        'mag_numero_ordre' => ['magazine ordre', 'mag_numero_ordre'],
        'mag_date_parution' => ['magazine date parution', 'mag_date_parution'],
        'mag_sommaire' => ['magazine sommaire', 'mag_sommaire'],
        'mag_pages' => ['magazine pages', 'mag_pages'],
        'mag_est_hors_serie' => ['magazine hors serie', 'magazine hors-série', 'mag_est_hors_serie'],
        'bd_series_id' => ['bd id serie', 'bd id série', 'bd_series_id', 'series_id bd'],
        'bd_series_titre' => ['bd titre serie', 'bd titre série', 'bd_series_titre', 'serie bd'],
        'bd_kind' => ['bd type', 'bd kind', 'bd_kind', 'type bd'],
        'bd_tome_numero' => ['bd tome', 'bd tome n', 'bd tome numero', 'bd_tome_numero', 'tome bd', 'tome numero bd'],
        'bd_tome_ordre' => ['bd ordre', 'bd_tome_ordre', 'ordre tome bd'],
        'bd_tome_label' => ['bd libelle tome', 'bd_tome_label', 'label tome bd'],
        'bd_est_hors_serie' => ['bd hors serie', 'bd hors-série', 'bd_est_hors_serie'],
        'bd_scenariste' => ['bd scenariste', 'bd scénariste', 'bd_scenariste', 'scenariste'],
        'bd_dessinateur' => ['bd dessinateur', 'bd_dessinateur', 'dessinateur'],
        'bd_editeur' => ['bd editeur', 'bd éditeur', 'bd_editeur'],
        'bd_genre' => ['bd genre', 'bd_genre'],
    ];

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function extensionValuesForExport(array $row): array
    {
        $domain = MediaDomain::normalize((string) ($row['media_domain'] ?? MediaDomain::FILM));
        $values = [];
        foreach (array_keys(self::EXTENSION_COLUMNS) as $key) {
            $values[] = match ($key) {
                'media_domain' => $domain,
                'jeu_is_digital' => self::formatBoolForExport((int) ($row['jeu_is_digital'] ?? 0) === 1),
                'jeu_is_extension' => self::formatBoolForExport((int) ($row['jeu_is_extension'] ?? 0) === 1),
                'jeu_base_game_oeuvre_id' => (int) ($row['jeu_base_game_oeuvre_id'] ?? 0) > 0
                    ? (string) (int) $row['jeu_base_game_oeuvre_id']
                    : '',
                'jeu_is_remake' => self::formatBoolForExport((int) ($row['jeu_is_remake'] ?? 0) === 1),
                'jeu_original_game_oeuvre_id' => (int) ($row['jeu_original_game_oeuvre_id'] ?? 0) > 0
                    ? (string) (int) $row['jeu_original_game_oeuvre_id']
                    : '',
                'mag_est_hors_serie' => self::formatBoolForExport((int) ($row['mag_est_hors_serie'] ?? 0) === 1),
                'mag_series_id' => (int) ($row['mag_series_id'] ?? 0) > 0
                    ? (string) (int) $row['mag_series_id']
                    : '',
                'mag_pages' => (int) ($row['mag_pages'] ?? 0) > 0
                    ? (string) (int) $row['mag_pages']
                    : '',
                'bd_series_id' => (int) ($row['bd_series_id'] ?? 0) > 0
                    ? (string) (int) $row['bd_series_id']
                    : '',
                'bd_est_hors_serie' => $domain === MediaDomain::BD
                    ? self::formatBoolForExport((int) ($row['bd_est_hors_serie'] ?? 0) === 1)
                    : '',
                'bd_tome_numero' => $domain === MediaDomain::BD
                    ? (string) (int) ($row['bd_tome_numero'] ?? 0)
                    : '',
                'bd_tome_ordre' => $domain === MediaDomain::BD
                    ? (string) ($row['bd_tome_ordre'] ?? '0')
                    : '',
                default => (string) ($row[$key] ?? ''),
            };
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $importedColumns
     */
    public static function importForOeuvre(int $oeuvreId, array $data, array $importedColumns = []): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        $importSet = $importedColumns !== [] ? array_flip($importedColumns) : null;
        $domain = MediaDomain::normalize(
            (string) ($data['media_domain'] ?? MediaDomain::FILM)
        );

        if ($domain === MediaDomain::JEU && GameRepository::isAvailable()) {
            self::importGameRow($oeuvreId, $data, $importSet);
        }

        if ($domain === MediaDomain::MAGAZINE && self::hasMagazineTable()) {
            self::importMagazineRow($oeuvreId, $data, $importSet);
        }

        if ($domain === MediaDomain::BD && self::hasBdTable()) {
            self::importBdRow($oeuvreId, $data, $importSet);
        }
    }

    private static function hasMagazineTable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_magazine' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    private static function hasBdTable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_bd' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int>|null $importSet
     */
    private static function importGameRow(int $oeuvreId, array $data, ?array $importSet): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT 1 FROM oeuvre_jeu WHERE oeuvre_id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        $exists = (bool) $stmt->fetchColumn();

        $studio = self::cellIfImported($data, $importSet, 'jeu_studio', (string) ($data['jeu_studio'] ?? ''));
        $editeur = self::cellIfImported($data, $importSet, 'jeu_editeur', (string) ($data['jeu_editeur'] ?? ''));
        $genre = GameGenre::normalizeInput(
            self::cellIfImported($data, $importSet, 'jeu_genre', (string) ($data['jeu_genre'] ?? ''))
        );
        $platform = GamePlatform::normalize(
            self::cellIfImported($data, $importSet, 'jeu_platform', (string) ($data['jeu_platform'] ?? ''))
        );
        $isDigital = self::parseBool(
            self::cellIfImported($data, $importSet, 'jeu_is_digital', (string) ($data['jeu_is_digital'] ?? ''))
        ) ? 1 : 0;
        $physicalSupports = self::cellIfImported(
            $data,
            $importSet,
            'jeu_physical_supports',
            (string) ($data['jeu_physical_supports'] ?? '')
        );
        $digitalStores = self::cellIfImported(
            $data,
            $importSet,
            'jeu_digital_stores',
            (string) ($data['jeu_digital_stores'] ?? '')
        );
        $isExtension = self::parseBool(self::cellIfImported(
            $data,
            $importSet,
            'jeu_is_extension',
            (string) ($data['jeu_is_extension'] ?? '')
        )) ? 1 : 0;
        $baseGameId = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'jeu_base_game_oeuvre_id',
            (string) ($data['jeu_base_game_oeuvre_id'] ?? '0')
        ));
        $baseGameId = $isExtension ? $baseGameId : 0;
        $isRemake = self::parseBool(self::cellIfImported(
            $data,
            $importSet,
            'jeu_is_remake',
            (string) ($data['jeu_is_remake'] ?? '')
        )) ? 1 : 0;
        $originalGameId = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'jeu_original_game_oeuvre_id',
            (string) ($data['jeu_original_game_oeuvre_id'] ?? '0')
        ));
        $originalGameId = $isRemake ? $originalGameId : 0;
        $relationData = [
            'is_extension' => $isExtension === 1,
            'base_game_oeuvre_id' => $baseGameId,
            'is_remake' => $isRemake === 1,
            'original_game_oeuvre_id' => $originalGameId,
        ];
        $relationParams = GameRepository::relationWriteParams($relationData);

        if (!$exists && $studio === '' && $editeur === '' && $genre === '' && $platform === '') {
            $db->prepare(
                'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$oeuvreId, '', '', '', '', 0]);

            return;
        }

        if (GameRepository::hasEditionColumns()) {
            if ($exists) {
                $db->prepare(
                    'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?,
                     is_digital = ?, physical_supports = ?, digital_stores = ?'
                    . GameRepository::relationUpdateSet()
                    . ' WHERE oeuvre_id = ?'
                )->execute([
                    $studio, $editeur, $genre, $platform, $isDigital,
                    $physicalSupports, $digitalStores,
                    ...$relationParams,
                    $oeuvreId,
                ]);
            } else {
                $db->prepare(
                    'INSERT INTO oeuvre_jeu (
                        oeuvre_id, studio, editeur, genre, platform, is_digital,
                        physical_supports, digital_stores'
                        . GameRepository::relationInsertColumns()
                        . '
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?'
                        . GameRepository::relationInsertPlaceholders()
                        . ')'
                )->execute([
                    $oeuvreId, $studio, $editeur, $genre, $platform, $isDigital,
                    $physicalSupports, $digitalStores,
                    ...$relationParams,
                ]);
            }

            return;
        }

        if ($exists) {
            $db->prepare(
                'UPDATE oeuvre_jeu SET studio = ?, editeur = ?, genre = ?, platform = ?, is_digital = ?'
                . GameRepository::relationUpdateSet()
                . ' WHERE oeuvre_id = ?'
            )->execute([
                $studio, $editeur, $genre, $platform, $isDigital,
                ...$relationParams,
                $oeuvreId,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO oeuvre_jeu (oeuvre_id, studio, editeur, genre, platform, is_digital'
                . GameRepository::relationInsertColumns()
                . ')
                 VALUES (?, ?, ?, ?, ?, ?'
                . GameRepository::relationInsertPlaceholders()
                . ')'
            )->execute([
                $oeuvreId, $studio, $editeur, $genre, $platform, $isDigital,
                ...$relationParams,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int>|null $importSet
     */
    private static function importMagazineRow(int $oeuvreId, array $data, ?array $importSet): void
    {
        $seriesId = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'mag_series_id',
            (string) ($data['mag_series_id'] ?? '0')
        ));
        if ($seriesId <= 0) {
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT 1 FROM series WHERE id = ? LIMIT 1');
        $stmt->execute([$seriesId]);
        if (!$stmt->fetchColumn()) {
            throw new \RuntimeException(
                'Série magazine ID ' . $seriesId . ' introuvable pour l’œuvre ' . $oeuvreId . '.'
            );
        }

        $numero = self::cellIfImported($data, $importSet, 'mag_numero', (string) ($data['mag_numero'] ?? ''));
        $numeroOrdre = (float) self::cellIfImported(
            $data,
            $importSet,
            'mag_numero_ordre',
            (string) ($data['mag_numero_ordre'] ?? '0')
        );
        $dateParution = self::cellIfImported(
            $data,
            $importSet,
            'mag_date_parution',
            (string) ($data['mag_date_parution'] ?? '')
        );
        $sommaire = self::cellIfImported($data, $importSet, 'mag_sommaire', (string) ($data['mag_sommaire'] ?? ''));
        $pages = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'mag_pages',
            (string) ($data['mag_pages'] ?? '0')
        ));
        $horsSerie = self::parseBool(self::cellIfImported(
            $data,
            $importSet,
            'mag_est_hors_serie',
            (string) ($data['mag_est_hors_serie'] ?? '')
        )) ? 1 : 0;

        $stmt = $db->prepare('SELECT 1 FROM oeuvre_magazine WHERE oeuvre_id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        if ($stmt->fetchColumn()) {
            $db->prepare(
                'UPDATE oeuvre_magazine SET series_id = ?, numero = ?, numero_ordre = ?,
                 date_parution = ?, sommaire = ?, pages = ?, est_hors_serie = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $seriesId, $numero, $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire, $pages, $horsSerie, $oeuvreId,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO oeuvre_magazine (
                    oeuvre_id, series_id, numero, numero_ordre, date_parution,
                    sommaire, pages, est_hors_serie
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId, $seriesId, $numero, $numeroOrdre,
                $dateParution !== '' ? $dateParution : null,
                $sommaire, $pages, $horsSerie,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int>|null $importSet
     */
    private static function importBdRow(int $oeuvreId, array $data, ?array $importSet): void
    {
        $seriesId = self::ensureBdSeriesId($data, $importSet);
        $kind = BdKind::normalize(self::cellIfImported(
            $data,
            $importSet,
            'bd_kind',
            (string) ($data['bd_kind'] ?? 'bd')
        ));
        $tomeNumero = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'bd_tome_numero',
            (string) ($data['bd_tome_numero'] ?? '0')
        ));
        $tomeOrdreRaw = self::cellIfImported(
            $data,
            $importSet,
            'bd_tome_ordre',
            (string) ($data['bd_tome_ordre'] ?? '')
        );
        $tomeOrdre = $tomeOrdreRaw !== '' ? (float) $tomeOrdreRaw : (float) $tomeNumero;
        $tomeLabel = self::cellIfImported(
            $data,
            $importSet,
            'bd_tome_label',
            (string) ($data['bd_tome_label'] ?? '')
        );
        $horsSerie = self::parseBool(self::cellIfImported(
            $data,
            $importSet,
            'bd_est_hors_serie',
            (string) ($data['bd_est_hors_serie'] ?? '')
        )) ? 1 : 0;
        $scenariste = self::cellIfImported(
            $data,
            $importSet,
            'bd_scenariste',
            (string) ($data['bd_scenariste'] ?? '')
        );
        $dessinateur = self::cellIfImported(
            $data,
            $importSet,
            'bd_dessinateur',
            (string) ($data['bd_dessinateur'] ?? '')
        );
        $editeur = self::cellIfImported(
            $data,
            $importSet,
            'bd_editeur',
            (string) ($data['bd_editeur'] ?? '')
        );
        $genre = self::cellIfImported(
            $data,
            $importSet,
            'bd_genre',
            (string) ($data['bd_genre'] ?? '')
        );

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT 1 FROM oeuvre_bd WHERE oeuvre_id = ? LIMIT 1');
        $stmt->execute([$oeuvreId]);
        if ($stmt->fetchColumn()) {
            $db->prepare(
                'UPDATE oeuvre_bd SET series_id = ?, kind = ?, tome_numero = ?, tome_ordre = ?,
                 tome_label = ?, est_hors_serie = ?, scenariste = ?, dessinateur = ?, editeur = ?, genre = ?
                 WHERE oeuvre_id = ?'
            )->execute([
                $seriesId > 0 ? $seriesId : null,
                $kind,
                $tomeNumero,
                $tomeOrdre,
                $tomeLabel,
                $horsSerie,
                $scenariste,
                $dessinateur,
                $editeur,
                $genre,
                $oeuvreId,
            ]);
        } else {
            $db->prepare(
                'INSERT INTO oeuvre_bd (
                    oeuvre_id, series_id, kind, tome_numero, tome_ordre, tome_label,
                    est_hors_serie, scenariste, dessinateur, editeur, genre
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $oeuvreId,
                $seriesId > 0 ? $seriesId : null,
                $kind,
                $tomeNumero,
                $tomeOrdre,
                $tomeLabel,
                $horsSerie,
                $scenariste,
                $dessinateur,
                $editeur,
                $genre,
            ]);
        }
    }

    /**
     * Crée ou retrouve la série BD pour conserver les liens catalogue.
     *
     * @param array<string, mixed> $data
     * @param array<string, int>|null $importSet
     */
    private static function ensureBdSeriesId(array $data, ?array $importSet): int
    {
        $seriesId = max(0, (int) self::cellIfImported(
            $data,
            $importSet,
            'bd_series_id',
            (string) ($data['bd_series_id'] ?? '0')
        ));
        $seriesTitre = self::cellIfImported(
            $data,
            $importSet,
            'bd_series_titre',
            (string) ($data['bd_series_titre'] ?? '')
        );
        $editeur = self::cellIfImported(
            $data,
            $importSet,
            'bd_editeur',
            (string) ($data['bd_editeur'] ?? '')
        );

        $seriesRepo = new SeriesRepository();

        if ($seriesId > 0) {
            $existing = $seriesRepo->findById($seriesId, MediaDomain::BD);
            if ($existing !== null) {
                return $seriesId;
            }
            // ID connu de l’ancienne instance : recréer avec le même numéro.
            if ($seriesTitre === '') {
                $seriesTitre = 'Série BD #' . $seriesId;
            }
            $created = $seriesRepo->createWithId($seriesId, [
                'titre' => $seriesTitre,
                'editeur' => $editeur,
            ], MediaDomain::BD);
            if (!is_int($created)) {
                throw new \RuntimeException((string) $created);
            }

            return $created;
        }

        if ($seriesTitre === '') {
            return 0;
        }

        $byTitle = $seriesRepo->findByTitre($seriesTitre, MediaDomain::BD);
        if ($byTitle !== null) {
            return (int) $byTitle['id'];
        }

        $created = $seriesRepo->create([
            'titre' => $seriesTitre,
            'editeur' => $editeur,
        ], MediaDomain::BD);
        if (!is_int($created)) {
            throw new \RuntimeException((string) $created);
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int>|null $importSet
     */
    private static function cellIfImported(
        array $data,
        ?array $importSet,
        string $key,
        string $fallback
    ): string {
        if ($importSet !== null && !isset($importSet[$key])) {
            return $fallback;
        }

        return trim((string) ($data[$key] ?? $fallback));
    }

    private static function formatBoolForExport(bool $value): string
    {
        return $value ? '1' : '0';
    }

    private static function parseBool(string $raw): bool
    {
        $raw = mb_strtolower(trim($raw));

        return in_array($raw, ['1', 'true', 'oui', 'yes', 'vrai'], true);
    }
}
