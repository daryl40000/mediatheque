<?php
/**
 * Lecture des lignes export Moncine (CSV ou ODS) — schéma : CollectionExportSchema.
 */

declare(strict_types=1);

namespace Moncine;

final class ImportFilmRows
{
    /** @var array<string, list<string>> */
    public const COLUMN_MAP = CollectionExportSchema::FILM_COLUMN_ALIASES;

    /** @var array<string, list<string>> */
    public const HISTORIQUE_MAP = CollectionExportSchema::HISTORIQUE_COLUMN_ALIASES;

    /**
     * @param list<string|null> $header
     * @param array<string, list<string>> $columnMap
     * @return array<string, int>
     */
    public static function mapHeaders(array $header, array $columnMap = self::COLUMN_MAP): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $norm = self::normalizeHeader((string) $label);
            foreach ($columnMap as $field => $aliases) {
                if (in_array($norm, $aliases, true)) {
                    $map[$field] = (int) $index;
                }
            }
        }

        return $map;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     * @return array<string, mixed>
     */
    public static function rowToFilm(array $row, array $map): array
    {
        $tmdbRaw = self::getCell($row, $map, 'tmdb_id');
        $tmdbId = 0;
        $tmdbMediaType = '';
        $tmdbTvKind = '';

        if (isset($map['tmdb_id']) || isset($map['tmdb_media_type'])) {
            $typeLabel = self::getCell($row, $map, 'tmdb_media_type');
            $parsedType = TmdbTvKind::parseImportTypeLabel($typeLabel);
            $tmdbMediaType = $parsedType['media_type'] !== ''
                ? $parsedType['media_type']
                : TmdbMediaType::normalize($typeLabel);
            $tmdbTvKind = $parsedType['tv_kind'];
        }

        if ($tmdbRaw !== '') {
            $ref = TmdbClient::normalizeTmdbReference($tmdbRaw);
            if ($ref !== null) {
                $tmdbId = $ref['id'];
                if ($ref['type'] !== '') {
                    $tmdbMediaType = TmdbMediaType::normalize($ref['type']);
                }
            } elseif (preg_match('/^\d+$/', $tmdbRaw)) {
                $tmdbId = max(0, (int) $tmdbRaw);
            }
        }

        $annee = 0;
        if (isset($map['annee'])) {
            $anneeRaw = self::getCell($row, $map, 'annee');
            if ($anneeRaw !== '' && preg_match('/^\d{4}$/', $anneeRaw)) {
                $annee = (int) $anneeRaw;
            }
        }

        $data = [
            'oeuvre_id' => self::intCell($row, $map, 'oeuvre_id'),
            'titre' => self::getCell($row, $map, 'titre'),
            'realisateur' => isset($map['realisateur']) ? self::getCell($row, $map, 'realisateur') : '',
            '_import_columns' => array_keys($map),
        ];

        if (isset($map['titre_original'])) {
            $data['titre_original'] = self::getCell($row, $map, 'titre_original');
        }
        if (isset($map['duree_min'])) {
            $data['duree_min'] = ImportCsv::parseDurationMinutes(self::getCell($row, $map, 'duree_min'));
        }
        if (isset($map['format_image'])) {
            $data['format_image'] = self::getCell($row, $map, 'format_image');
        }
        if (isset($map['format_son'])) {
            $data['format_son'] = self::getCell($row, $map, 'format_son');
        }
        if (isset($map['support_physique'])) {
            $data['support_physique'] = SupportPhysique::normalize(self::getCell($row, $map, 'support_physique'));
        }
        if (isset($map['styles'])) {
            $data['styles'] = self::getCell($row, $map, 'styles');
        }
        if (isset($map['saga'])) {
            $data['saga'] = trim(self::getCell($row, $map, 'saga'));
            if ($data['saga'] === '') {
                $data['saga_ordre'] = 0;
            } elseif (isset($map['saga_ordre'])) {
                $ordreRaw = self::getCell($row, $map, 'saga_ordre');
                $data['saga_ordre'] = $ordreRaw !== '' && preg_match('/^\d+$/', $ordreRaw)
                    ? max(1, (int) $ordreRaw)
                    : 0;
            }
        } elseif (isset($map['saga_ordre'])) {
            $ordreRaw = self::getCell($row, $map, 'saga_ordre');
            $data['saga_ordre'] = $ordreRaw !== '' && preg_match('/^\d+$/', $ordreRaw)
                ? max(1, (int) $ordreRaw)
                : 0;
        }
        if (isset($map['annee'])) {
            $data['annee'] = $annee;
        }
        if (isset($map['nationalite'])) {
            $data['nationalite'] = TmdbCountries::formatNationaliteList(
                self::getCell($row, $map, 'nationalite')
            );
        }
        if (isset($map['acteur_1'])) {
            $data['acteur_1'] = self::getCell($row, $map, 'acteur_1');
        }
        if (isset($map['acteur_2'])) {
            $data['acteur_2'] = self::getCell($row, $map, 'acteur_2');
        }
        if (isset($map['acteur_3'])) {
            $data['acteur_3'] = self::getCell($row, $map, 'acteur_3');
        }
        if (isset($map['synopsis'])) {
            $data['synopsis'] = self::getCell($row, $map, 'synopsis');
        }
        if (isset($map['poster_url'])) {
            $data['poster_url'] = SecureUrl::sanitizePosterUrl(self::getCell($row, $map, 'poster_url'));
        }
        if (isset($map['tmdb_id'])) {
            $data['tmdb_id'] = $tmdbId;
        }
        if (isset($map['tmdb_media_type'])) {
            $data['tmdb_media_type'] = $tmdbMediaType;
            $data['tmdb_tv_kind'] = $tmdbTvKind;
        } elseif ($tmdbId > 0 && $tmdbMediaType !== '') {
            $data['tmdb_media_type'] = $tmdbMediaType;
            $data['tmdb_tv_kind'] = $tmdbTvKind;
        }

        if (isset($map['vu'])) {
            $data['_vu'] = self::getCell($row, $map, 'vu');
        }
        if (isset($map['note'])) {
            $data['_note'] = self::getCell($row, $map, 'note');
        }

        return $data;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    public static function previewTitre(array $row, array $map): string
    {
        $t = self::getCell($row, $map, 'titre');

        return $t !== '' ? $t : '?';
    }

    /** @param list<string|null> $row */
    public static function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    public static function getCell(array $row, array $map, string $key): string
    {
        if (!isset($map[$key])) {
            return '';
        }

        return trim((string) ($row[$map[$key]] ?? ''));
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    public static function intCell(array $row, array $map, string $key): int
    {
        if (!isset($map[$key])) {
            return 0;
        }

        $raw = trim((string) ($row[$map[$key]] ?? ''));
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return 0;
        }

        return max(0, (int) $raw);
    }

    public static function normalizeHeader(string $label): string
    {
        $label = self::stripHeaderWrapping($label);
        $label = mb_strtolower(trim($label), 'UTF-8');
        $label = str_replace(
            ['é', 'è', 'ê', 'ë', 'à', 'â', 'ù', 'û', 'ô', 'î', 'ï', 'ç', '—', '–', '-', '_', '°', 'º'],
            ['e', 'e', 'e', 'e', 'a', 'a', 'u', 'u', 'o', 'i', 'i', 'c', ' ', ' ', ' ', ' ', '', ''],
            $label
        );

        return preg_replace('/\s+/', ' ', $label) ?? $label;
    }

    /** Enlève BOM UTF-8 et guillemets Excel autour d’un libellé de colonne. */
    public static function stripHeaderWrapping(string $label): string
    {
        $label = (string) preg_replace('/^\x{FEFF}/u', '', $label);
        $label = (string) preg_replace('/^\xEF\xBB\xBF/', '', $label);
        $label = trim($label);
        while (
            strlen($label) >= 2
            && (($label[0] === '"' && $label[strlen($label) - 1] === '"')
                || ($label[0] === "'" && $label[strlen($label) - 1] === "'"))
        ) {
            $label = trim(substr($label, 1, -1));
        }

        return trim($label);
    }
}
