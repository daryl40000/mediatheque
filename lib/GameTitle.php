<?php
/**
 * Titres jeux : français (oeuvres.titre) et anglais IGDB (oeuvres.titre_original).
 */

declare(strict_types=1);

namespace Moncine;

final class GameTitle
{
    /**
     * Titre affiché : français si présent, sinon anglais IGDB.
     *
     * @param array<string, mixed> $row
     */
    public static function displayTitle(array $row): string
    {
        $french = trim((string) ($row['titre'] ?? ''));
        if ($french !== '') {
            return $french;
        }

        return trim((string) ($row['titre_original'] ?? ''));
    }

    /**
     * Texte utilisé pour la recherche (français + anglais).
     *
     * @param array<string, mixed> $row
     */
    public static function searchText(array $row): string
    {
        $parts = [];
        foreach (['titre', 'titre_original', 'alternative_names'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Titre envoyé à IGDB quand le français seul ne suffit pas.
     *
     * @param array<string, mixed> $row
     */
    public static function lookupTitle(array $row): string
    {
        $french = trim((string) ($row['titre'] ?? ''));
        if ($french !== '') {
            return $french;
        }

        return trim((string) ($row['titre_original'] ?? ''));
    }
}
