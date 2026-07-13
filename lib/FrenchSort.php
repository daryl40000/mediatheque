<?php
/**
 * Tri alphabétique français : les accents ne décalent pas les titres (Démineur près de De, pas après Dz).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FrenchSort
{
    public const COLLATE = 'FRENCH_NOCASE';

    /** @var bool */
    private static bool $registered = false;

    /** @internal Tests PHPUnit */
    public static function resetRegistrationForTests(): void
    {
        self::$registered = false;
    }

    public static function registerCollation(PDO $pdo): void
    {
        if (self::$registered || !method_exists($pdo, 'sqliteCreateCollation')) {
            return;
        }

        $pdo->sqliteCreateCollation(self::COLLATE, static function (mixed $left, mixed $right): int {
            return self::compare((string) $left, (string) $right);
        });

        self::$registered = true;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    /** Collation SQL à utiliser dans ORDER BY (repli sur NOCASE si indisponible). */
    public static function sqlCollate(): string
    {
        return self::$registered ? self::COLLATE : 'NOCASE';
    }

    public static function compare(string $left, string $right): int
    {
        $foldedLeft = self::fold($left);
        $foldedRight = self::fold($right);

        if ($foldedLeft === $foldedRight) {
            return 0;
        }

        return $foldedLeft < $foldedRight ? -1 : 1;
    }

    /**
     * Minuscules sans accents pour le tri (Démineur → demineur).
     */
    public static function fold(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $stripped = preg_replace('/\p{M}/u', '', $normalized);
                if (is_string($stripped) && $stripped !== '') {
                    return $stripped;
                }
            }
        }

        return strtr($value, self::accentMap());
    }

    /** @return array<string, string> */
    private static function accentMap(): array
    {
        return [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'œ' => 'oe', 'æ' => 'ae',
        ];
    }
}
