<?php
/**
 * Plateformes de jeux vidéo (catalogue et filtres).
 */

declare(strict_types=1);

namespace Moncine;

final class GamePlatform
{
    public const PC = 'pc';
    public const PS5 = 'ps5';
    public const PS4 = 'ps4';
    public const XBOX_SERIES = 'xbox_series';
    public const XBOX_ONE = 'xbox_one';
    public const SWITCH = 'switch';
    public const SWITCH2 = 'switch2';
    public const MOBILE = 'mobile';
    public const MULTI = 'multi';
    public const OTHER = 'other';

    /** @return array<string, string> clé interne => libellé affiché */
    public static function choices(): array
    {
        return [
            self::PC => 'PC',
            self::PS5 => 'PlayStation 5',
            self::PS4 => 'PlayStation 4',
            self::XBOX_SERIES => 'Xbox Series',
            self::XBOX_ONE => 'Xbox One',
            self::SWITCH => 'Nintendo Switch',
            self::SWITCH2 => 'Nintendo Switch 2',
            self::MOBILE => 'Mobile',
            self::MULTI => 'Multi-plateformes',
            self::OTHER => 'Autre',
        ];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && $key !== '' && isset(self::choices()[$key]);
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return self::choices()[$key] ?? (string) $key;
    }

    /** Normalise une saisie formulaire ou import vers une clé interne (ou vide). */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        $norm = mb_strtolower($raw, 'UTF-8');
        $norm = str_replace(['é', 'è', 'ê'], 'e', $norm);
        $norm = preg_replace('/\s+/', ' ', $norm) ?? $norm;

        $aliases = [
            'pc' => self::PC,
            'steam' => self::PC,
            'windows' => self::PC,
            'ps5' => self::PS5,
            'playstation 5' => self::PS5,
            'playstation5' => self::PS5,
            'ps4' => self::PS4,
            'playstation 4' => self::PS4,
            'playstation4' => self::PS4,
            'xbox series' => self::XBOX_SERIES,
            'xbox series x' => self::XBOX_SERIES,
            'xbox series s' => self::XBOX_SERIES,
            'xbox one' => self::XBOX_ONE,
            'switch' => self::SWITCH,
            'nintendo switch' => self::SWITCH,
            'switch 2' => self::SWITCH2,
            'nintendo switch 2' => self::SWITCH2,
            'mobile' => self::MOBILE,
            'android' => self::MOBILE,
            'ios' => self::MOBILE,
            'multi' => self::MULTI,
            'multi-plateformes' => self::MULTI,
            'multiplateformes' => self::MULTI,
        ];

        return $aliases[$norm] ?? self::OTHER;
    }

    /** Libellé court pour affichage catalogue (ex. « PS5 »). */
    public static function shortLabel(?string $key): string
    {
        return match (self::normalize($key)) {
            self::PC => 'PC',
            self::PS5 => 'PS5',
            self::PS4 => 'PS4',
            self::XBOX_SERIES => 'Xbox Series',
            self::XBOX_ONE => 'Xbox One',
            self::SWITCH => 'Switch',
            self::SWITCH2 => 'Switch 2',
            self::MOBILE => 'Mobile',
            self::MULTI => 'Multi',
            self::OTHER => 'Autre',
            default => '',
        };
    }

    /** Plateformes consoles (store démat unique, sans lien). */
    public static function isConsole(string $platform): bool
    {
        return in_array(self::normalize($platform), [
            self::PS5,
            self::PS4,
            self::XBOX_SERIES,
            self::XBOX_ONE,
            self::SWITCH,
            self::SWITCH2,
        ], true);
    }

    /** PC : choix Steam / GOG / Epic avec liens magasin. */
    public static function usesPcDigitalStores(string $platform): bool
    {
        return self::normalize($platform) === self::PC;
    }
}
