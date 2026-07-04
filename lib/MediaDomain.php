<?php
/**
 * Type de média géré par l’application (onglet actif).
 */

declare(strict_types=1);

namespace Moncine;

final class MediaDomain
{
    public const FILM = 'film';
    public const BD = 'bd';
    public const LIVRE = 'livre';
    public const MUSIQUE = 'musique';
    public const JEU = 'jeu';
    public const MAGAZINE = 'magazine';

    /** @return array<string, string> clé => libellé court (onglet) */
    public static function choices(): array
    {
        return [
            self::FILM => 'Films',
            self::BD => 'BD / Manga',
            self::LIVRE => 'Livres',
            self::MUSIQUE => 'Musique',
            self::JEU => 'Jeux',
            self::MAGAZINE => 'Magazines',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return self::FILM;
        }

        $aliases = [
            'films' => self::FILM,
            'cinema' => self::FILM,
            'dvd' => self::FILM,
            'manga' => self::BD,
            'bd' => self::BD,
            'comics' => self::BD,
            'livre' => self::LIVRE,
            'livres' => self::LIVRE,
            'book' => self::LIVRE,
            'books' => self::LIVRE,
            'musique' => self::MUSIQUE,
            'music' => self::MUSIQUE,
            'vinyle' => self::MUSIQUE,
            'vinyl' => self::MUSIQUE,
            'cd' => self::MUSIQUE,
            'jeu' => self::JEU,
            'jeux' => self::JEU,
            'game' => self::JEU,
            'games' => self::JEU,
            'magazine' => self::MAGAZINE,
            'magazines' => self::MAGAZINE,
        ];

        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        return $aliases[$raw] ?? self::FILM;
    }

    public static function label(string $domain): string
    {
        $domain = self::normalize($domain);

        return self::choices()[$domain] ?? self::choices()[self::FILM];
    }

    public static function isFilm(string $domain): bool
    {
        return self::normalize($domain) === self::FILM;
    }

    public static function isMagazine(string $domain): bool
    {
        return self::normalize($domain) === self::MAGAZINE;
    }

    public static function isGame(string $domain): bool
    {
        return self::normalize($domain) === self::JEU;
    }

    public static function isBd(string $domain): bool
    {
        return self::normalize($domain) === self::BD;
    }

    public static function isMusique(string $domain): bool
    {
        return self::normalize($domain) === self::MUSIQUE;
    }

    /** Domaines dont la collection est utilisable. */
    public static function isCollectionImplemented(string $domain): bool
    {
        $domain = self::normalize($domain);

        return $domain === self::FILM
            || $domain === self::MAGAZINE
            || $domain === self::JEU
            || $domain === self::BD;
    }

    /** Page principale « ma collection » selon l’onglet actif. */
    public static function collectionPath(string $domain): string
    {
        return match (self::normalize($domain)) {
            self::MAGAZINE => '/magazines.php',
            self::JEU => '/jeux.php',
            self::BD => '/bd.php',
            self::LIVRE => '/livres.php',
            self::MUSIQUE => '/musique.php',
            default => '/films.php',
        };
    }

    /** Page « mes envies » selon l’onglet actif. */
    public static function wishlistPath(string $domain): string
    {
        return match (self::normalize($domain)) {
            self::MAGAZINE => '/magazines-envies.php',
            self::JEU => '/jeux-envies.php',
            self::BD => '/bd-envies.php',
            self::LIVRE => '/livres-envies.php',
            self::MUSIQUE => '/musique-envies.php',
            default => '/souhaits.php',
        };
    }

    /** Couleur d’accent (thème CSS). */
    public static function accentColor(string $domain): string
    {
        return self::theme($domain)['accent'];
    }

    /** Couleur d’accent atténuée (fond onglet actif). */
    public static function accentMutedColor(string $domain): string
    {
        return self::theme($domain)['accent_muted'];
    }

    /**
     * Palette complète du domaine (variables CSS).
     *
     * @return array{
     *   accent: string,
     *   accent_hover: string,
     *   accent_muted: string,
     *   bar_bg: string,
     *   header_tint: string,
     *   body_tint: string
     * }
     */
    public static function theme(string $domain): array
    {
        return match (self::normalize($domain)) {
            // Gris argenté — dvdthèque films (neutre, sobre)
            self::FILM => [
                'accent' => '#adb5bd',
                'accent_hover' => '#ced4da',
                'accent_muted' => '#2d3038',
                'bar_bg' => '#16161a',
                'header_tint' => '#1a1a1f',
                'body_tint' => '#0f0f12',
            ],
            // Rose corail — BD / manga
            self::BD => [
                'accent' => '#f06292',
                'accent_hover' => '#f48fb1',
                'accent_muted' => '#3d2435',
                'bar_bg' => '#1a1218',
                'header_tint' => '#221019',
                'body_tint' => '#120a0e',
            ],
            // Bleu ciel — livres
            self::LIVRE => [
                'accent' => '#64b5f6',
                'accent_hover' => '#90caf9',
                'accent_muted' => '#1a2838',
                'bar_bg' => '#0f141c',
                'header_tint' => '#121a24',
                'body_tint' => '#0a0e14',
            ],
            // Ambre — musique (vinyles, CD)
            self::MUSIQUE => [
                'accent' => '#ffca28',
                'accent_hover' => '#ffd54f',
                'accent_muted' => '#3d3520',
                'bar_bg' => '#1a1810',
                'header_tint' => '#221f14',
                'body_tint' => '#12100a',
            ],
            // Violet — jeux vidéo
            self::JEU => [
                'accent' => '#9575cd',
                'accent_hover' => '#b39ddb',
                'accent_muted' => '#2a2438',
                'bar_bg' => '#14101c',
                'header_tint' => '#181222',
                'body_tint' => '#0c0a12',
            ],
            // Vert d’eau — magazines
            self::MAGAZINE => [
                'accent' => '#4db6ac',
                'accent_hover' => '#80cbc4',
                'accent_muted' => '#1a2e2c',
                'bar_bg' => '#0e1413',
                'header_tint' => '#101a18',
                'body_tint' => '#080e0d',
            ],
            default => [
                'accent' => '#adb5bd',
                'accent_hover' => '#ced4da',
                'accent_muted' => '#2d3038',
                'bar_bg' => '#16161a',
                'header_tint' => '#1a1a1f',
                'body_tint' => '#0f0f12',
            ],
        };
    }

    /**
     * Libellés de navigation selon le domaine.
     *
     * @return array{collection: string, wishlist: string, stats: string, footer: string}
     */
    public static function navLabels(string $domain): array
    {
        return match (self::normalize($domain)) {
            self::BD => [
                'collection' => 'Mes BD',
                'wishlist' => 'Mes envies BD',
                'stats' => 'Statistiques BD',
                'footer' => 'Collection BD / manga',
            ],
            self::LIVRE => [
                'collection' => 'Mes livres',
                'wishlist' => 'Mes envies livres',
                'stats' => 'Statistiques livres',
                'footer' => 'Bibliothèque livres',
            ],
            self::MUSIQUE => [
                'collection' => 'Ma musique',
                'wishlist' => 'Mes envies musique',
                'stats' => 'Statistiques musique',
                'footer' => 'Bibliothèque musique',
            ],
            self::JEU => [
                'collection' => 'Mes jeux',
                'wishlist' => 'Mes envies jeux',
                'stats' => 'Statistiques jeux',
                'footer' => 'Collection jeux vidéo',
            ],
            self::MAGAZINE => [
                'collection' => 'Mes magazines',
                'wishlist' => 'Mes envies magazines',
                'stats' => 'Statistiques magazines',
                'footer' => 'Collection magazines',
            ],
            default => [
                'collection' => 'Mes films',
                'wishlist' => 'Mes envies',
                'stats' => 'Statistiques',
                'footer' => 'Dvdthèque personnelle',
            ],
        };
    }

    /** Fonctionnalités réservées aux films (quiz, sagas, personnes…). */
    public static function hasFilmOnlyFeatures(string $domain): bool
    {
        return self::isFilm($domain);
    }

}
