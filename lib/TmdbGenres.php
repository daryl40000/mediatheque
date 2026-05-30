<?php
/**
 * Genres TMDB → chaîne « Style » Moncine (ex. Action, Aventure).
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbGenres
{
    /**
     * Extrait les noms de genres d’une fiche détaillée movie/{id} ou tv/{id} (langue fr-FR).
     *
     * @param array<string, mixed> $detail
     */
    public static function stylesFromDetail(array $detail): string
    {
        $names = [];
        foreach ($detail['genres'] ?? [] as $genre) {
            if (!is_array($genre)) {
                continue;
            }
            $name = trim((string) ($genre['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($names[$key])) {
                $names[$key] = $name;
            }
        }

        return implode(', ', array_values($names));
    }

    /**
     * Style à enregistrer : TMDB seulement si la fiche n’en a pas encore.
     *
     * @param array<string, mixed> $film
     * @param array<string, mixed> $meta
     */
    public static function mergeStylesForEnrichment(array $film, array $meta): string
    {
        $current = trim((string) ($film['styles'] ?? ''));
        if ($current !== '') {
            return $current;
        }

        return trim((string) ($meta['styles'] ?? ''));
    }
}
