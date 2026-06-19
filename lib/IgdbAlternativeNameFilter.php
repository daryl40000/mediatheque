<?php
/**
 * Filtre les noms alternatifs IGDB pour ne conserver que les acronymes (GTA, FF, TLoZ…).
 */

declare(strict_types=1);

namespace Moncine;

final class IgdbAlternativeNameFilter
{
    /**
     * @param list<string> $names
     */
    public static function serializeAcronyms(array $names, string $mainTitle = ''): string
    {
        $acronyms = [];
        $mainKey = mb_strtolower(trim($mainTitle));

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            if ($mainKey !== '' && mb_strtolower($name) === $mainKey) {
                continue;
            }
            if (!self::isAcronym($name)) {
                continue;
            }
            $acronyms[] = $name;
        }

        return GameGenre::serializeList($acronyms);
    }

    public static function isAcronym(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || preg_match('/\s/u', $name) !== 0) {
            return false;
        }

        $length = mb_strlen($name);
        if ($length < 2 || $length > 14) {
            return false;
        }

        if (!preg_match('/^[\p{L}\p{N}.\-&\'\/\+]+$/u', $name)) {
            return false;
        }

        if (preg_match_all('/\p{Ll}/u', $name, $lowerMatches) && count($lowerMatches[0]) > 3) {
            return false;
        }

        if (!preg_match('/\p{Lu}/u', $name) && !preg_match('/^[A-Z0-9.\-&\'\/\+]+$/', $name)) {
            return false;
        }

        if ($length > 4 && !preg_match('/\p{Lu}/u', $name) && !preg_match('/\d/', $name)) {
            return false;
        }

        return true;
    }
}
