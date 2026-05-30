<?php
/**
 * Lecture des lignes export catalogue (admin).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportCatalogRows
{
    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     * @return array<string, mixed>
     */
    public static function rowToOeuvre(array $row, array $map): array
    {
        $tmdbRaw = ImportFilmRows::getCell($row, $map, 'tmdb_id');
        $tmdbId = 0;
        $tmdbMediaType = '';
        $tmdbTvKind = '';

        if (isset($map['tmdb_id']) || isset($map['tmdb_media_type'])) {
            $typeLabel = ImportFilmRows::getCell($row, $map, 'tmdb_media_type');
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

        $kindRaw = ImportFilmRows::getCell($row, $map, 'moncine_kind');
        $moncineKind = $kindRaw !== ''
            ? MoncineContentKind::normalize(mb_strtolower($kindRaw, 'UTF-8'))
            : MoncineContentKind::FILM;

        $data = [
            'oeuvre_id' => self::intCell($row, $map, 'oeuvre_id'),
            'titre' => ImportFilmRows::getCell($row, $map, 'titre'),
            'realisateur' => ImportFilmRows::getCell($row, $map, 'realisateur'),
            '_import_columns' => array_keys($map),
        ];

        if (isset($map['titre_original'])) {
            $data['titre_original'] = ImportFilmRows::getCell($row, $map, 'titre_original');
        }
        if (isset($map['duree_min'])) {
            $data['duree_min'] = ImportCsv::parseDurationMinutes(
                ImportFilmRows::getCell($row, $map, 'duree_min')
            );
        }
        if (isset($map['styles'])) {
            $data['styles'] = ImportFilmRows::getCell($row, $map, 'styles');
        }
        if (isset($map['annee'])) {
            $anneeRaw = ImportFilmRows::getCell($row, $map, 'annee');
            $data['annee'] = $anneeRaw !== '' && preg_match('/^\d{4}$/', $anneeRaw) ? (int) $anneeRaw : 0;
        }
        if (isset($map['nationalite'])) {
            $data['nationalite'] = TmdbCountries::formatNationaliteList(
                ImportFilmRows::getCell($row, $map, 'nationalite')
            );
        }
        foreach (['acteur_1', 'acteur_2', 'acteur_3'] as $acteur) {
            if (isset($map[$acteur])) {
                $data[$acteur] = ImportFilmRows::getCell($row, $map, $acteur);
            }
        }
        if (isset($map['synopsis'])) {
            $data['synopsis'] = ImportFilmRows::getCell($row, $map, 'synopsis');
        }
        if (isset($map['poster_url'])) {
            $data['poster_url'] = SecureUrl::sanitizePosterUrl(
                ImportFilmRows::getCell($row, $map, 'poster_url')
            );
        }
        if (isset($map['tmdb_id'])) {
            $data['tmdb_id'] = $tmdbId;
            $data['tmdb_media_type'] = $tmdbMediaType;
            $data['tmdb_tv_kind'] = $tmdbTvKind;
        }

        $data['moncine_kind'] = $moncineKind;

        return $data;
    }

    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     */
    private static function intCell(array $row, array $map, string $key): int
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
}
