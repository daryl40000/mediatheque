<?php
/**
 * Liste multi-plateformes (catalogue et bibliothèque) — sérialisation CSV.
 */

declare(strict_types=1);

namespace Moncine;

final class GamePlatformList
{
    /**
     * @param array<int, string>|string $raw
     */
    public static function normalizeFromPost(array|string $raw): string
    {
        if (is_string($raw)) {
            return self::serializeList(self::parseList($raw));
        }

        return self::serializeList($raw);
    }

    /**
     * Plateformes possédées : sous-ensemble du catalogue du jeu.
     *
     * @param array<int, string>|string $raw
     * @param list<string> $catalogKeys
     */
    public static function normalizeOwnedFromPost(array|string $raw, array $catalogKeys): string
    {
        $catalogSet = [];
        foreach ($catalogKeys as $key) {
            $key = GamePlatform::normalize((string) $key);
            if ($key !== '') {
                $catalogSet[$key] = true;
            }
        }

        $owned = [];
        $items = is_array($raw) ? $raw : self::parseList($raw);
        foreach ($items as $key) {
            $key = GamePlatform::normalize((string) $key);
            if ($key !== '' && isset($catalogSet[$key])) {
                $owned[$key] = $key;
            }
        }

        return self::serializeList(array_values($owned));
    }

    /** @return list<string> */
    public static function parseList(string $raw): array
    {
        $items = [];
        foreach (preg_split('/[,;]+/', trim($raw)) ?: [] as $part) {
            $key = GamePlatform::normalize(trim($part));
            if ($key !== '') {
                $items[$key] = $key;
            }
        }

        return self::orderedKeys(array_values($items));
    }

    /** @param list<string> $keys */
    public static function serializeList(array $keys): string
    {
        return implode(',', self::orderedKeys($keys));
    }

    /**
     * Plateformes catalogue d'une ligne jeu (colonne platforms, repli sur platform).
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function catalogKeysFromRow(array $row): array
    {
        if (GameSchema::hasPlatformsColumn()) {
            $fromList = self::parseList((string) ($row['platforms'] ?? ''));
            if ($fromList !== []) {
                return $fromList;
            }
        }

        $legacy = GamePlatform::normalize((string) ($row['platform'] ?? ''));

        return $legacy !== '' ? [$legacy] : [];
    }

    /**
     * Plateformes possédées sur l'exemplaire (repli catalogue si vide).
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function ownedKeysFromRow(array $row): array
    {
        if (GameSchema::hasOwnedPlatformsColumn()) {
            $owned = self::parseList((string) ($row['owned_platforms'] ?? ''));
            if ($owned !== []) {
                return $owned;
            }
        }

        return self::catalogKeysFromRow($row);
    }

    /**
     * Clé principale (tri, compatibilité colonne platform).
     *
     * @param list<string> $keys
     */
    public static function primaryKey(array $keys): string
    {
        $ordered = self::orderedKeys($keys);

        return $ordered[0] ?? '';
    }

    /** @param list<string> $keys */
    public static function shortLabelsDisplay(array $keys): string
    {
        $labels = [];
        foreach (self::orderedKeys($keys) as $key) {
            $short = GamePlatform::shortLabel($key);
            if ($short !== '') {
                $labels[] = $short;
            }
        }

        return implode(' · ', $labels);
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    public static function orderedKeys(array $keys): array
    {
        $order = array_flip(array_keys(GamePlatform::choices()));
        $unique = [];
        foreach ($keys as $key) {
            $key = GamePlatform::normalize((string) $key);
            if ($key !== '') {
                $unique[$key] = $key;
            }
        }

        $list = array_values($unique);
        usort($list, static function (string $a, string $b) use ($order): int {
            $oa = $order[$a] ?? 9999;
            $ob = $order[$b] ?? 9999;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcmp($a, $b);
        });

        return $list;
    }

    /** Condition SQL : la clé apparaît dans une liste CSV (colonnes platforms ou owned_platforms). */
    public static function sqlCsvContains(string $columnExpr, string $paramName): string
    {
        return "((',' || REPLACE(COALESCE({$columnExpr}, ''), ' ', '') || ',') LIKE ('%,' || {$paramName} || ',%'))";
    }
}
