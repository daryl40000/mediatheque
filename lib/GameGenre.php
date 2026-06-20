<?php
/**
 * Genres de jeux vidéo : tags réutilisables (comme les tags de série magazine).
 *
 * Stockés dans oeuvre_jeu.genre, séparés par des virgules.
 */

declare(strict_types=1);

namespace Moncine;

final class GameGenre
{
    /** @return list<string> */
    public static function parseList(string $raw): array
    {
        $tags = [];
        foreach (preg_split('/[,;]+/', trim($raw)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $key = mb_strtolower($part);
            if (!isset($tags[$key])) {
                $tags[$key] = $part;
            }
        }

        return array_values($tags);
    }

    public static function serializeList(array $tags): string
    {
        $out = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $key = mb_strtolower($tag);
            if (!isset($out[$key])) {
                $out[$key] = $tag;
            }
        }

        return implode(', ', array_values($out));
    }

    public static function normalizeInput(string $raw): string
    {
        return self::serializeList(self::parseList($raw));
    }

    /** @param array<int, string>|string $raw */
    public static function normalizeFromPost(array|string $raw): string
    {
        if (is_array($raw)) {
            return self::serializeList($raw);
        }

        return self::normalizeInput($raw);
    }

    public static function displayLabel(string $storedGenres): string
    {
        return self::serializeList(self::parseList($storedGenres));
    }

    /**
     * Expression SQL : liste de tags en minuscules, délimitée par des virgules (comme parseList).
     * Permet un test fiable via LIKE '%,tag,%'.
     */
    public static function sqlTaggedCsvLower(string $columnSql): string
    {
        return 'LOWER(\',\' || REPLACE(REPLACE(REPLACE(TRIM(' . $columnSql . '), \';\', \',\'), \', \', \',\'), \',,\', \',\') || \',\')';
    }

    /** Vérifie qu’un tag est présent dans une chaîne stockée (tests / logique PHP). */
    public static function listContainsTag(string $storedGenres, string $tagKey): bool
    {
        $tagKey = mb_strtolower(trim($tagKey));
        if ($tagKey === '') {
            return false;
        }

        foreach (self::parseList($storedGenres) as $tag) {
            if (mb_strtolower($tag) === $tagKey) {
                return true;
            }
        }

        return false;
    }
}
