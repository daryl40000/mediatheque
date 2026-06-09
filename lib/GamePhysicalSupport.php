<?php
/**
 * Supports physiques des jeux (CD/DVD, disquette…).
 */

declare(strict_types=1);

namespace Moncine;

final class GamePhysicalSupport
{
    public const CD_DVD = 'cd_dvd';
    public const DISKETTE = 'disquette';

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::CD_DVD => 'CD / DVD',
            self::DISKETTE => 'Disquette',
        ];
    }

    /** @return list<string> */
    public static function parseList(string $raw): array
    {
        $items = [];
        foreach (preg_split('/[,;]+/', trim($raw)) ?: [] as $part) {
            $part = self::normalizeKey(trim($part));
            if ($part === '' || !isset(self::choices()[$part])) {
                continue;
            }
            $items[$part] = $part;
        }

        return array_values($items);
    }

    public static function serializeList(array $keys): string
    {
        $out = [];
        foreach ($keys as $key) {
            $key = self::normalizeKey((string) $key);
            if ($key !== '' && isset(self::choices()[$key])) {
                $out[$key] = $key;
            }
        }

        return implode(',', array_values($out));
    }

    /** @param array<int, string>|string $raw */
    public static function normalizeFromPost(array|string $raw): string
    {
        if (is_array($raw)) {
            return self::serializeList($raw);
        }

        return self::serializeList(self::parseList($raw));
    }

    public static function label(string $key): string
    {
        $key = self::normalizeKey($key);

        return self::choices()[$key] ?? $key;
    }

    /** @return list<string> libellés affichables */
    public static function displayLabels(string $raw): array
    {
        $labels = [];
        foreach (self::parseList($raw) as $key) {
            $labels[] = self::label($key);
        }

        return $labels;
    }

    private static function normalizeKey(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return match ($raw) {
            'cd', 'dvd', 'cd/dvd', 'cd_dvd', 'cd-dvd' => self::CD_DVD,
            'disquette', 'diskette', 'floppy' => self::DISKETTE,
            default => $raw,
        };
    }
}
