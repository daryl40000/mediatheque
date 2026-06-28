<?php
/**
 * Mode d’affichage des collections : liste, vignettes ; jeux : vue bibliothèque (tranches).
 */

declare(strict_types=1);

namespace Moncine;

final class CollectionViewMode
{
    public const LIST = 'list';
    public const GRID = 'grid';
    public const SHELF = 'shelf';

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return match ($raw) {
            self::GRID => self::GRID,
            self::SHELF => self::SHELF,
            default => self::LIST,
        };
    }

    public static function isGrid(string $mode): bool
    {
        return self::normalize($mode) === self::GRID;
    }

    public static function isShelf(string $mode): bool
    {
        return self::normalize($mode) === self::SHELF;
    }

    /** Valeur du paramètre URL `view`, ou null pour le mode liste (défaut). */
    public static function queryValue(string $mode): ?string
    {
        $mode = self::normalize($mode);

        return $mode === self::LIST ? null : $mode;
    }

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::LIST => 'Liste',
            self::GRID => 'Vignettes',
            self::SHELF => 'Bibliothèque',
        ];
    }

    /** @return array<string, string> */
    public static function gameChoices(): array
    {
        return self::choices();
    }

    /** Liste et vignettes uniquement (pages sans vue bibliothèque). */
    public static function listGridChoices(): array
    {
        return [
            self::LIST => 'Liste',
            self::GRID => 'Vignettes',
        ];
    }
}
