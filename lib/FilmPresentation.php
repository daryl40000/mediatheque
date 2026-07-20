<?php
/**
 * Helpers d’affichage / parsing pour les films (hors accès base).
 *
 * Extrait de FilmRepositoryLegacy (dette qualité) pour que le schéma catalogue
 * n’ait plus besoin de charger la logique « ancien moteur films » pour formater.
 */

declare(strict_types=1);

namespace Moncine;

final class FilmPresentation
{
    /**
     * Identifiants de films cochés dans un formulaire de masse.
     *
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public static function parseBulkFilmIds(array $post): array
    {
        $ids = [];
        foreach ((array) ($post['film_ids'] ?? []) as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function formatSagaOrdre(int $ordre): string
    {
        return $ordre > 0 ? (string) $ordre : '—';
    }

    public static function formatSupport(?string $key): string
    {
        $label = SupportPhysique::label($key);

        return $label !== '' ? $label : '—';
    }

    public static function formatNationalite(?string $nationalite): string
    {
        $formatted = TmdbCountries::formatNationaliteList((string) $nationalite);

        return $formatted !== '' ? $formatted : '—';
    }

    /**
     * Rôles de la personne recherchée sur ce film (réalisateur, acteur…).
     *
     * @param array<string, mixed> $film
     * @return list<string>
     */
    public static function rolesForPerson(array $film, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $personId = preg_match('/^\d+$/', $query) ? (int) $query : 0;
        $q = mb_strtolower($query, 'UTF-8');
        $roles = [];

        if (self::personFieldMatches(
            (string) ($film['realisateur'] ?? ''),
            (int) ($film['realisateur_tmdb_id'] ?? 0),
            $q,
            $personId
        )) {
            $roles[] = 'Réalisateur';
        }
        foreach (['acteur_1' => 'Acteur', 'acteur_2' => 'Acteur', 'acteur_3' => 'Acteur'] as $key => $label) {
            $idKey = $key . '_tmdb_id';
            if (self::personFieldMatches(
                (string) ($film[$key] ?? ''),
                (int) ($film[$idKey] ?? 0),
                $q,
                $personId
            )) {
                if (!in_array($label, $roles, true)) {
                    $roles[] = $label;
                }
            }
        }

        return $roles;
    }

    private static function personFieldMatches(string $name, int $tmdbPersonId, string $queryLower, int $queryPersonId): bool
    {
        if ($queryPersonId > 0 && $tmdbPersonId > 0 && $tmdbPersonId === $queryPersonId) {
            return true;
        }
        if ($name === '') {
            return false;
        }

        return mb_strpos(mb_strtolower($name, 'UTF-8'), $queryLower, 0, 'UTF-8') !== false;
    }

    public static function formatAnnee(int $annee): string
    {
        return $annee > 0 ? (string) $annee : '—';
    }

    /** Affiche la durée en « 1 h 56 » ou « 90 min ». */
    public static function formatDuree(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return $h . ' h ' . $m . ' min';
        }
        if ($h > 0) {
            return $h . ' h';
        }

        return $minutes . ' min';
    }

    /**
     * Découpe "Action, Comédie" en liste normalisée.
     *
     * @return list<string>
     */
    public static function splitStyles(string $styles): array
    {
        $parts = preg_split('/[,;|\/]+/', $styles) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $s = trim($part);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }
}
