<?php
/**
 * Lecture des lignes export bibliothèque (CSV / ODS léger).
 */

declare(strict_types=1);

namespace Moncine;

final class ImportLibraryRows
{
    /**
     * @param list<string|null> $row
     * @param array<string, int> $map
     * @return array<string, mixed>
     */
    public static function rowToLibrary(array $row, array $map): array
    {
        $data = [
            'oeuvre_id' => self::intCell($row, $map, 'oeuvre_id'),
            'bibliotheque_id' => self::intCell($row, $map, 'bibliotheque_id'),
            'titre' => ImportFilmRows::getCell($row, $map, 'titre'),
            'realisateur' => ImportFilmRows::getCell($row, $map, 'realisateur'),
            '_import_columns' => array_keys($map),
        ];

        if (isset($map['media_domain'])) {
            $domainRaw = ImportFilmRows::getCell($row, $map, 'media_domain');
            if ($domainRaw !== '') {
                $data['media_domain'] = MediaDomain::normalize($domainRaw);
            }
        }

        if (isset($map['statut'])) {
            $raw = ImportFilmRows::getCell($row, $map, 'statut');
            $data['statut'] = LibraryStatut::normalize($raw);
        }

        foreach (LibraryExportSchema::libraryDatabaseFields() as $field) {
            if ($field === 'statut') {
                continue;
            }
            if (isset($map[$field])) {
                $value = ImportFilmRows::getCell($row, $map, $field);
                $data[$field] = match ($field) {
                    'support_physique' => self::normalizeSupportForImport(
                        $value,
                        (string) ($data['media_domain'] ?? '')
                    ),
                    'saga_ordre', 'saison_numero' => max(0, (int) $value),
                    'ean' => preg_replace('/\D+/', '', $value) ?? '',
                    default => $value,
                };
            }
        }

        if (isset($map['vu'])) {
            $data['_vu'] = ImportFilmRows::getCell($row, $map, 'vu');
        }
        if (isset($map['note'])) {
            $data['_note'] = ImportFilmRows::getCell($row, $map, 'note');
        }

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

    /** Normalise le support selon le domaine (films vs BD, etc.). */
    private static function normalizeSupportForImport(string $value, string $mediaDomain): string
    {
        $domain = $mediaDomain !== '' ? MediaDomain::normalize($mediaDomain) : '';
        if ($domain === MediaDomain::BD) {
            return BdPhysicalSupport::normalize($value);
        }

        $film = SupportPhysique::normalize($value);
        if ($film !== '') {
            return $film;
        }

        // Ancien export sans colonne Domaine : accepter aussi un support BD.
        return BdPhysicalSupport::normalize($value);
    }
}
