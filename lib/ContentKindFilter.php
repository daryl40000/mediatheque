<?php
/**
 * Catégories affichées dans la liste collection : Film, Série, Documentaire, Spectacle.
 */

declare(strict_types=1);

namespace Moncine;

final class ContentKindFilter
{
    public const ALL = '';
    public const FILM = 'film';
    public const SERIE = 'serie';
    public const DOCUMENTARY = 'documentaire';
    public const SPECTACLE = 'spectacle';

    /** @return array<string, string> valeur URL → libellé */
    public static function choices(): array
    {
        return [
            self::ALL => 'Tout',
            self::FILM => 'Films',
            self::SERIE => 'Séries',
            self::DOCUMENTARY => 'Documentaires',
            self::SPECTACLE => 'Spectacles',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '' || $raw === 'all' || $raw === 'tout') {
            return self::ALL;
        }

        $aliases = [
            'films' => self::FILM,
            'movie' => self::FILM,
            'series' => self::SERIE,
            'série' => self::SERIE,
            'séries' => self::SERIE,
            'doc' => self::DOCUMENTARY,
            'documentaire' => self::DOCUMENTARY,
            'documentaires' => self::DOCUMENTARY,
            'spectacles' => self::SPECTACLE,
            'concert' => self::SPECTACLE,
        ];

        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        return $aliases[$raw] ?? self::ALL;
    }

    public static function label(string $filter): string
    {
        $filter = self::normalize($filter);

        return self::choices()[$filter] ?? self::choices()[self::ALL];
    }

    /** @return array<string, string> Questionnaire « Ce soir » */
    public static function quizChoices(): array
    {
        return [
            self::ALL => 'Peu importe',
            self::FILM => 'Film',
            self::SERIE => 'Série',
            self::DOCUMENTARY => 'Documentaire',
            self::SPECTACLE => 'Spectacle',
        ];
    }

    public static function quizLabel(string $filter): string
    {
        $filter = self::normalize($filter);

        return self::quizChoices()[$filter] ?? self::quizChoices()[self::ALL];
    }

    /**
     * @param array<string, mixed> $film
     */
    public static function matchesFilter(array $film, string $filter): bool
    {
        $filter = self::normalize($filter);
        if ($filter === self::ALL) {
            return true;
        }

        return self::categoryKey($film) === $filter;
    }

    /**
     * Libellé court pour une ligne du tableau (Film, Série, Documentaire, Spectacle).
     *
     * @param array<string, mixed> $film
     */
    public static function listLabel(array $film): string
    {
        return match (self::categoryKey($film)) {
            self::SERIE => 'Série',
            self::DOCUMENTARY => 'Documentaire',
            self::SPECTACLE => 'Spectacle',
            default => 'Film',
        };
    }

    /**
     * @param array<string, mixed> $film
     */
    public static function categoryKey(array $film): string
    {
        if (self::isSpectacleRow($film)) {
            return self::SPECTACLE;
        }
        if (self::isSerieRow($film)) {
            return self::SERIE;
        }
        if (self::isDocumentaryRow($film)) {
            return self::DOCUMENTARY;
        }

        return self::FILM;
    }

    /**
     * @param array<string, mixed> $film
     */
    private static function isSpectacleRow(array $film): bool
    {
        if (MoncineContentKind::isSpectacle((string) ($film['moncine_kind'] ?? ''))) {
            return true;
        }

        return TmdbTvKind::normalize((string) ($film['tmdb_tv_kind'] ?? '')) === TmdbTvKind::SPECTACLE;
    }

    /**
     * @param array<string, mixed> $film
     */
    private static function isSerieRow(array $film): bool
    {
        if (MoncineContentKind::isSerie((string) ($film['moncine_kind'] ?? ''))) {
            return true;
        }

        if (TmdbMediaType::normalize((string) ($film['tmdb_media_type'] ?? '')) !== TmdbMediaType::TV) {
            return false;
        }

        $kind = TmdbTvKind::normalize((string) ($film['tmdb_tv_kind'] ?? ''));

        return !in_array($kind, [TmdbTvKind::DOCUMENTARY, TmdbTvKind::SPECTACLE], true);
    }

    /**
     * @param array<string, mixed> $film
     */
    private static function isDocumentaryRow(array $film): bool
    {
        return TmdbTvKind::normalize((string) ($film['tmdb_tv_kind'] ?? '')) === TmdbTvKind::DOCUMENTARY;
    }

    /**
     * Clause SQL AND pour filtrer (préfixe table œuvre : o. ou f.).
     *
     * @param array<string, string|int> $params
     */
    public static function sqlWhere(string $filter, string $oeuvrePrefix, array &$params): string
    {
        $filter = self::normalize($filter);
        if ($filter === self::ALL) {
            return '';
        }

        $k = $oeuvrePrefix . 'moncine_kind';
        $mt = $oeuvrePrefix . 'tmdb_media_type';
        $tk = $oeuvrePrefix . 'tmdb_tv_kind';

        return match ($filter) {
            self::SPECTACLE => '(' . $k . " = 'spectacle' OR " . $tk . " = 'spectacle')",
            self::SERIE => '(' . $k . " = 'serie' OR (" . $mt . " = 'tv' AND COALESCE(" . $tk . ", '') NOT IN ('documentary', 'spectacle')))",
            self::DOCUMENTARY => '(' . $tk . " = 'documentary')",
            self::FILM => '(' . $k . " = 'film' OR " . $k . " = '' OR " . $k . " IS NULL)
                AND COALESCE(" . $tk . ", '') NOT IN ('documentary', 'spectacle')
                AND NOT (" . $mt . " = 'tv' AND COALESCE(" . $tk . ", '') NOT IN ('documentary', 'spectacle', ''))",
            default => '',
        };
    }
}
