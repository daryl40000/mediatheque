<?php
/**
 * Type de contenu TMDB : film ou série TV.
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbMediaType
{
    public const MOVIE = 'movie';
    public const TV = 'tv';

    public static function normalize(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === self::TV || $raw === 'tv' || $raw === 'serie' || $raw === 'série' || $raw === 'series') {
            return self::TV;
        }
        if ($raw === self::MOVIE || $raw === 'film' || $raw === 'movies') {
            return self::MOVIE;
        }
        return '';
    }

    public static function isTv(string $type): bool
    {
        return self::normalize($type) === self::TV;
    }

    /** Lien public themoviedb.org */
    public static function publicUrl(int $tmdbId, string $mediaType): string
    {
        if ($tmdbId <= 0) {
            return '';
        }
        $segment = self::isTv($mediaType) ? 'tv' : 'movie';
        return 'https://www.themoviedb.org/' . $segment . '/' . $tmdbId;
    }

    public static function label(string $mediaType, string $tvKind = ''): string
    {
        $kind = TmdbTvKind::normalize($tvKind);

        if (!self::isTv($mediaType)) {
            return match ($kind) {
                TmdbTvKind::DOCUMENTARY => 'Documentaire',
                TmdbTvKind::SPECTACLE => 'Spectacle',
                default => 'Film',
            };
        }

        return match ($kind) {
            TmdbTvKind::DOCUMENTARY => 'Documentaire TV',
            TmdbTvKind::EMISSION => 'Émission TV',
            TmdbTvKind::MINISERIES => 'Mini-série',
            TmdbTvKind::SPECTACLE => 'Spectacle',
            default => 'Série TV',
        };
    }
}
