<?php
/**
 * Tags libres associés à une série magazine (PC, Diesel, 16 Go…).
 *
 * Saisis sur la fiche série, séparés par des virgules.
 * Un seul tag → appliqué automatiquement à tous les sujets du numéro.
 * Plusieurs tags → choix obligatoire à l’ajout d’un sujet.
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineSeriesTag
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

    /** Normalise une saisie utilisateur (champ texte ou POST). */
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

    /** @param array<string, mixed> $series */
    public static function listForSeries(array $series): array
    {
        return self::parseList((string) ($series['tags'] ?? ''));
    }

    /** @param array<string, mixed> $series */
    public static function singleTag(array $series): ?string
    {
        $list = self::listForSeries($series);

        return count($list) === 1 ? $list[0] : null;
    }

    /**
     * Retrouve un tag série à partir d’une saisie (comparaison insensible à la casse).
     *
     * @param list<string> $seriesTags
     */
    public static function matchTag(string $userValue, array $seriesTags): ?string
    {
        $needle = mb_strtolower(trim($userValue));
        if ($needle === '') {
            return null;
        }

        foreach ($seriesTags as $tag) {
            if (mb_strtolower($tag) === $needle) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Détermine la précision stockée dans magazine_subject.detail.
     *
     * @param array<string, mixed> $series
     */
    public static function resolveDetailForSubject(array $series, string $userDetail): string
    {
        $seriesTags = self::listForSeries($series);
        $single = count($seriesTags) === 1 ? $seriesTags[0] : null;

        if ($single !== null) {
            return $single;
        }

        if (count($seriesTags) > 1) {
            $picked = self::matchTag($userDetail, $seriesTags);

            return $picked ?? '';
        }

        return trim($userDetail);
    }

    /** @param array<string, mixed> $series */
    public static function requiresTagChoice(array $series): bool
    {
        return count(self::listForSeries($series)) > 1;
    }

    public static function detailLabel(string $storedDetail): string
    {
        return trim($storedDetail);
    }
}
