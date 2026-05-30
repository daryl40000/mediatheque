<?php
/**
 * Profils affichés dans les formulaires (film, documentaire, série…)
 * → paires tmdb_media_type + tmdb_tv_kind en base.
 */

declare(strict_types=1);

namespace Moncine;

final class TmdbContentProfile
{
    public const FILM = 'film';
    public const DOCUMENTARY = 'documentary';
    public const DOCUMENTARY_TV = 'documentary_tv';
    public const SERIES = 'series';
    public const MINISERIES = 'miniseries';
    public const EMISSION = 'emission';
    public const SPECTACLE = 'spectacle';

    /** @return array<string, string> clé → libellé */
    public static function choices(): array
    {
        return [
            self::FILM => 'Film',
            self::DOCUMENTARY => 'Documentaire (cinéma)',
            self::DOCUMENTARY_TV => 'Documentaire TV',
            self::SERIES => 'Série TV',
            self::MINISERIES => 'Mini-série',
            self::EMISSION => 'Émission TV',
            self::SPECTACLE => 'Spectacle',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        $aliases = [
            'movie' => self::FILM,
            'film' => self::FILM,
            'doc' => self::DOCUMENTARY,
            'documentaire' => self::DOCUMENTARY,
            'documentaire tv' => self::DOCUMENTARY_TV,
            'doc tv' => self::DOCUMENTARY_TV,
            'serie' => self::SERIES,
            'série' => self::SERIES,
            'serie tv' => self::SERIES,
            'série tv' => self::SERIES,
            'tv' => self::SERIES,
            'mini-serie' => self::MINISERIES,
            'mini-série' => self::MINISERIES,
            'miniserie' => self::MINISERIES,
            'émission' => self::EMISSION,
            'emission' => self::EMISSION,
            'spectacle' => self::SPECTACLE,
            'concert' => self::SPECTACLE,
            'humour' => self::SPECTACLE,
        ];

        return $aliases[$raw] ?? '';
    }

    /**
     * @return array{media_type: string, tv_kind: string}
     */
    public static function toTmdbFields(string $profile): array
    {
        return match (self::normalize($profile)) {
            self::DOCUMENTARY => [
                'media_type' => TmdbMediaType::MOVIE,
                'tv_kind' => TmdbTvKind::DOCUMENTARY,
            ],
            self::DOCUMENTARY_TV => [
                'media_type' => TmdbMediaType::TV,
                'tv_kind' => TmdbTvKind::DOCUMENTARY,
            ],
            self::SERIES => [
                'media_type' => TmdbMediaType::TV,
                'tv_kind' => TmdbTvKind::SERIES,
            ],
            self::MINISERIES => [
                'media_type' => TmdbMediaType::TV,
                'tv_kind' => TmdbTvKind::MINISERIES,
            ],
            self::EMISSION => [
                'media_type' => TmdbMediaType::TV,
                'tv_kind' => TmdbTvKind::EMISSION,
            ],
            self::SPECTACLE => [
                'media_type' => TmdbMediaType::MOVIE,
                'tv_kind' => TmdbTvKind::SPECTACLE,
            ],
            default => [
                'media_type' => TmdbMediaType::MOVIE,
                'tv_kind' => '',
            ],
        };
    }

    /** Déduit le profil à partir des champs en base. */
    public static function fromTmdbFields(string $mediaType, string $tvKind): string
    {
        $mediaType = TmdbMediaType::normalize($mediaType);
        $tvKind = TmdbTvKind::normalize($tvKind);

        if ($tvKind === TmdbTvKind::SPECTACLE) {
            return self::SPECTACLE;
        }

        if ($mediaType === TmdbMediaType::TV) {
            return match ($tvKind) {
                TmdbTvKind::DOCUMENTARY => self::DOCUMENTARY_TV,
                TmdbTvKind::EMISSION => self::EMISSION,
                TmdbTvKind::MINISERIES => self::MINISERIES,
                default => self::SERIES,
            };
        }

        return $tvKind === TmdbTvKind::DOCUMENTARY ? self::DOCUMENTARY : self::FILM;
    }

    public static function label(string $profile): string
    {
        $profile = self::normalize($profile);

        return self::choices()[$profile] ?? self::choices()[self::FILM];
    }
}
