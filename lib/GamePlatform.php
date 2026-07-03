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
    public const SNES = 'snes';
    public const SWITCH = 'switch';
    public const SWITCH2 = 'switch2';
    public const MOBILE = 'mobile';
    public const MULTI = 'multi';
    public const OTHER = 'other';

    /** @return array<string, string> clé interne => libellé affiché */
    public static function choices(): array
    {
        return GamePlatformRegistry::choices(true);
    }

    /** @return array<string, string> y compris plateformes désactivées (affichage données existantes). */
    public static function allChoices(): array
    {
        return GamePlatformRegistry::choices(false);
    }

    public static function isValid(?string $key): bool
    {
        return GamePlatformRegistry::isValid($key, false);
    }

    public static function label(?string $key): string
    {
        return GamePlatformRegistry::label($key);
    }

    public static function shortLabel(?string $key): string
    {
        return GamePlatformRegistry::shortLabel($key);
    }

    /** Normalise une saisie formulaire ou import vers une clé interne (ou vide). */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        if (GamePlatformRegistry::isValid($raw, false)) {
            return self::normalizeKey($raw);
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
            'snes' => self::SNES,
            'super nintendo' => self::SNES,
            'super nintendo entertainment system' => self::SNES,
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

        $mapped = $aliases[$norm] ?? '';
        if ($mapped !== '' && GamePlatformRegistry::isValid($mapped, false)) {
            return $mapped;
        }

        $slug = preg_replace('/[^a-z0-9_]+/', '_', $norm) ?? '';
        $slug = trim((string) $slug, '_');
        if ($slug !== '' && GamePlatformRegistry::isValid($slug, false)) {
            return $slug;
        }

        return GamePlatformRegistry::isValid(self::OTHER, false) ? self::OTHER : '';
    }

    public static function normalizeKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');
        $raw = preg_replace('/[^a-z0-9_]+/', '_', $raw) ?? '';
        $raw = trim($raw, '_');

        return $raw;
    }

    /** Plateformes consoles (store démat unique, sans lien). */
    public static function isConsole(string $platform): bool
    {
        return GamePlatformRegistry::isConsole($platform);
    }

    /** PC : choix Steam / GOG / Epic / Battle.net avec liens magasin. */
    public static function usesPcDigitalStores(string $platform): bool
    {
        return GamePlatformRegistry::usesPcDigitalStores($platform);
    }

    /** @return list<string> */
    public static function selectedKeysFromPost(array $post, string $arrayField, string $scalarField = ''): array
    {
        if (isset($post[$arrayField]) && is_array($post[$arrayField])) {
            return GamePlatformList::parseList(GamePlatformList::serializeList($post[$arrayField]));
        }

        if ($scalarField !== '' && isset($post[$scalarField])) {
            $single = self::normalize((string) $post[$scalarField]);

            return $single !== '' ? [$single] : [];
        }

        return [];
    }
}
