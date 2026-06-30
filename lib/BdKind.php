<?php
/**
 * Type d’album : bande dessinée, manga, comic.
 */

declare(strict_types=1);

namespace Moncine;

final class BdKind
{
    public const BD = 'bd';
    public const MANGA = 'manga';
    public const COMIC = 'comic';

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::BD => 'BD',
            self::MANGA => 'Manga',
            self::COMIC => 'Comic',
        ];
    }

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return isset(self::choices()[$raw]) ? $raw : self::BD;
    }

    public static function label(string $key): string
    {
        $key = self::normalize($key);

        return self::choices()[$key] ?? $key;
    }
}
