<?php
/**
 * Catégorie principale d’une entrée : film, série (avec saisons), spectacle.
 * Complète les profils TMDB (documentaire, émission…) via TmdbContentProfile.
 */

declare(strict_types=1);

namespace Moncine;

final class MoncineContentKind
{
    public const FILM = 'film';
    public const SERIE = 'serie';
    public const SPECTACLE = 'spectacle';

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::FILM => 'Film',
            self::SERIE => 'Série',
            self::SPECTACLE => 'Spectacle (concert, one-man show…)',
        ];
    }

    /** Choix du formulaire (alignés sur la colonne Type de Mes films). */
    public const FORM_DOCUMENTAIRE = 'documentaire';

    /** @return array<string, string> */
    public static function formChoices(): array
    {
        return [
            self::FILM => 'Film',
            self::SERIE => 'Série',
            self::FORM_DOCUMENTAIRE => 'Documentaire',
            self::SPECTACLE => 'Spectacle',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return self::FILM;
        }
        if (isset(self::choices()[$raw])) {
            return $raw;
        }
        $aliases = [
            'série' => self::SERIE,
            'serie' => self::SERIE,
            'series' => self::SERIE,
            'concert' => self::SPECTACLE,
            'humour' => self::SPECTACLE,
            'spectacle' => self::SPECTACLE,
            'one-man-show' => self::SPECTACLE,
            'one man show' => self::SPECTACLE,
        ];

        return $aliases[$raw] ?? self::FILM;
    }

    public static function label(string $kind): string
    {
        $kind = self::normalize($kind);

        return self::choices()[$kind] ?? self::choices()[self::FILM];
    }

    public static function isSerie(string $kind): bool
    {
        return self::normalize($kind) === self::SERIE;
    }

    public static function isSpectacle(string $kind): bool
    {
        return self::normalize($kind) === self::SPECTACLE;
    }

    /**
     * Valeur du champ formulaire « content_kind » → moncine_kind + profil TMDB optionnel.
     *
     * @return array{moncine_kind: string, tmdb_profile: string}
     */
    public static function parseFormValue(string $formValue): array
    {
        $formValue = trim($formValue);
        if ($formValue === self::FORM_DOCUMENTAIRE) {
            return [
                'moncine_kind' => self::FILM,
                'tmdb_profile' => TmdbContentProfile::DOCUMENTARY_TV,
            ];
        }
        if (str_starts_with($formValue, 'profile:')) {
            $profile = substr($formValue, 8);

            return [
                'moncine_kind' => self::FILM,
                'tmdb_profile' => TmdbContentProfile::normalize($profile) !== ''
                    ? TmdbContentProfile::normalize($profile)
                    : TmdbContentProfile::FILM,
            ];
        }

        $kind = self::normalize($formValue);
        $profile = match ($kind) {
            self::SERIE => TmdbContentProfile::SERIES,
            self::SPECTACLE => TmdbContentProfile::SPECTACLE,
            default => TmdbContentProfile::FILM,
        };

        return ['moncine_kind' => $kind, 'tmdb_profile' => $profile];
    }

    /**
     * Valeur à présélectionner dans le formulaire.
     */
    public static function toFormValue(string $moncineKind, string $mediaType, string $tvKind): string
    {
        $moncineKind = self::normalize($moncineKind);
        if ($moncineKind === self::SERIE) {
            return self::SERIE;
        }
        if ($moncineKind === self::SPECTACLE) {
            return self::SPECTACLE;
        }

        $profile = TmdbContentProfile::fromTmdbFields($mediaType, $tvKind);
        if (in_array($profile, [TmdbContentProfile::DOCUMENTARY, TmdbContentProfile::DOCUMENTARY_TV], true)) {
            return self::FORM_DOCUMENTAIRE;
        }
        if (in_array($profile, [
            TmdbContentProfile::SERIES,
            TmdbContentProfile::MINISERIES,
            TmdbContentProfile::EMISSION,
        ], true)) {
            return self::SERIE;
        }
        if ($profile === TmdbContentProfile::SPECTACLE) {
            return self::SPECTACLE;
        }

        return self::FILM;
    }

    /** Déduit moncine_kind à partir des champs TMDB enregistrés. */
    public static function fromTmdbFields(string $mediaType, string $tvKind): string
    {
        $tvKind = TmdbTvKind::normalize($tvKind);
        if ($tvKind === TmdbTvKind::SPECTACLE) {
            return self::SPECTACLE;
        }
        if (TmdbMediaType::normalize($mediaType) === TmdbMediaType::TV) {
            if (!in_array($tvKind, [TmdbTvKind::DOCUMENTARY, TmdbTvKind::SPECTACLE, ''], true)) {
                return self::SERIE;
            }
        }

        return self::FILM;
    }

    /**
     * Déduit saison / catégorie depuis le titre (ex. « Saison 2 », « Intégrale »).
     *
     * @return array{saison_numero: int, saison_label: string, moncine_kind: string}
     */
    public static function inferFromTitle(string $titre, string $edition = ''): array
    {
        $haystack = $titre . ' ' . $edition;
        $saisonNumero = 0;
        $saisonLabel = '';
        if (preg_match('/\b(?:saison|season)\s*(\d+)\b/iu', $haystack, $m)) {
            $saisonNumero = max(1, (int) $m[1]);
            $saisonLabel = 'Saison ' . $saisonNumero;
        } elseif (preg_match('/\b(?:saison|season)\s*([IVXLC]+)\b/iu', $haystack, $m)) {
            $saisonLabel = 'Saison ' . strtoupper($m[1]);
        } elseif (preg_match('/\bintégrale\b/iu', $haystack)) {
            $saisonLabel = 'Intégrale';
        }

        $moncineKind = self::FILM;
        if ($saisonNumero > 0 || $saisonLabel !== '' || preg_match('/\bsérie\b/iu', $haystack)) {
            $moncineKind = self::SERIE;
        }
        if (preg_match('/\b(concert|one[- ]?man|humour|spectacle|stand[- ]?up|comédie\s+musicale)\b/iu', $haystack)) {
            $moncineKind = self::SPECTACLE;
        }

        return [
            'saison_numero' => $saisonNumero,
            'saison_label' => $saisonLabel,
            'moncine_kind' => $moncineKind,
        ];
    }
}
