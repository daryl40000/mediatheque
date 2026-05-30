<?php
/**
 * Sous-type des contenus TV TMDB : série, documentaire, émission, mini-série.
 * Les identifiants de genre TMDB sont stables (même en français).
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbTvKind
{
    public const SERIES = 'series';
    public const DOCUMENTARY = 'documentary';
    public const EMISSION = 'emission';
    public const MINISERIES = 'miniseries';
    public const SPECTACLE = 'spectacle';

    /** Genre « Documentaire » */
    private const GENRE_DOCUMENTARY = 99;

    /** Genres « émission » (infos, télé-réalité, talk-show) */
    private const GENRE_NEWS = 10763;
    private const GENRE_REALITY = 10764;
    private const GENRE_TALK = 10767;

    /** Mini-série */
    private const GENRE_MINISERIES = 10770;

    /**
     * Déduit le sous-type à partir de la fiche TV complète TMDB.
     *
     * @param array<string, mixed> $tvData
     */
    public static function classifyTvDetail(array $tvData): string
    {
        $ids = self::genreIds($tvData);
        if (in_array(self::GENRE_DOCUMENTARY, $ids, true)) {
            return self::DOCUMENTARY;
        }
        if (in_array(self::GENRE_MINISERIES, $ids, true)) {
            return self::MINISERIES;
        }
        foreach ([self::GENRE_NEWS, self::GENRE_REALITY, self::GENRE_TALK] as $emissionGenre) {
            if (in_array($emissionGenre, $ids, true)) {
                return self::EMISSION;
            }
        }

        return self::SERIES;
    }

    /**
     * Documentaire cinéma / téléfilm (fiche « film » TMDB).
     *
     * @param array<string, mixed> $movieData
     */
    public static function classifyMovieDetail(array $movieData): string
    {
        if (in_array(self::GENRE_DOCUMENTARY, self::genreIds($movieData), true)) {
            return self::DOCUMENTARY;
        }

        return '';
    }

    /** Sous-types stockés sur une fiche « film » (media_type = movie). */
    public static function isMovieMetadata(string $kind): bool
    {
        $kind = self::normalize($kind);

        return $kind === self::DOCUMENTARY || $kind === self::SPECTACLE;
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }

        $map = [
            'series' => self::SERIES,
            'serie' => self::SERIES,
            'série' => self::SERIES,
            'série tv' => self::SERIES,
            'serie tv' => self::SERIES,
            'documentary' => self::DOCUMENTARY,
            'documentaire' => self::DOCUMENTARY,
            'documentaire tv' => self::DOCUMENTARY,
            'docu' => self::DOCUMENTARY,
            'emission' => self::EMISSION,
            'émission' => self::EMISSION,
            'emission tv' => self::EMISSION,
            'émission tv' => self::EMISSION,
            'miniserie' => self::MINISERIES,
            'mini-serie' => self::MINISERIES,
            'mini-série' => self::MINISERIES,
            'miniseries' => self::MINISERIES,
            'spectacle' => self::SPECTACLE,
            'concert' => self::SPECTACLE,
            'humour' => self::SPECTACLE,
            'one-man-show' => self::SPECTACLE,
        ];

        if (isset($map[$raw])) {
            return $map[$raw];
        }

        return in_array($raw, [self::SERIES, self::DOCUMENTARY, self::EMISSION, self::MINISERIES, self::SPECTACLE], true)
            ? $raw
            : '';
    }

    /**
     * Interprète la colonne « Type TMDB » à l’import (export / Excel).
     *
     * @return array{media_type: string, tv_kind: string}
     */
    public static function parseImportTypeLabel(string $label): array
    {
        $label = mb_strtolower(trim($label));
        if ($label === '') {
            return ['media_type' => '', 'tv_kind' => ''];
        }

        $tvKind = self::normalize($label);
        if ($tvKind !== '') {
            return ['media_type' => TmdbMediaType::TV, 'tv_kind' => $tvKind];
        }

        if (str_contains($label, 'documentaire')) {
            if (str_contains($label, 'tv') || str_contains($label, 'télé')) {
                return ['media_type' => TmdbMediaType::TV, 'tv_kind' => self::DOCUMENTARY];
            }

            return ['media_type' => TmdbMediaType::MOVIE, 'tv_kind' => self::DOCUMENTARY];
        }

        if (str_contains($label, 'émission') || str_contains($label, 'emission')) {
            return ['media_type' => TmdbMediaType::TV, 'tv_kind' => self::EMISSION];
        }

        if (str_contains($label, 'mini')) {
            return ['media_type' => TmdbMediaType::TV, 'tv_kind' => self::MINISERIES];
        }

        if (str_contains($label, 'série') || str_contains($label, 'serie')) {
            return ['media_type' => TmdbMediaType::TV, 'tv_kind' => self::SERIES];
        }

        if ($label === 'film' || $label === 'movie') {
            return ['media_type' => TmdbMediaType::MOVIE, 'tv_kind' => ''];
        }

        return ['media_type' => '', 'tv_kind' => ''];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<int>
     */
    private static function genreIds(array $data): array
    {
        $ids = [];
        foreach ($data['genres'] ?? [] as $genre) {
            if (!is_array($genre)) {
                continue;
            }
            $id = (int) ($genre['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
