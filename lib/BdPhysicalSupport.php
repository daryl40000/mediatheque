<?php
/**
 * Supports physiques des albums BD / manga.
 */

declare(strict_types=1);

namespace Moncine;

final class BdPhysicalSupport
{
    public const ALBUM = 'album';
    public const RELIE = 'relie';
    public const POCHE = 'poche';
    public const COFFRET = 'coffret';
    public const MAGAZINE = 'magazine';

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::ALBUM => 'Album',
            self::RELIE => 'Relié',
            self::POCHE => 'Poche',
            self::COFFRET => 'Coffret',
            self::MAGAZINE => 'Magazine',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return isset(self::choices()[$raw]) ? $raw : '';
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

        return self::choices()[$key] ?? $key;
    }
}
