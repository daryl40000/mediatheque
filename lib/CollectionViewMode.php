<?php
/**
 * Mode d’affichage de la page Mes films : liste (tableau) ou vignettes (grille).
 */

declare(strict_types=1);

namespace Moncine;

final class CollectionViewMode
{
    public const LIST = 'list';
    public const GRID = 'grid';

    public static function normalize(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));

        return $raw === self::GRID ? self::GRID : self::LIST;
    }

    public static function isGrid(string $mode): bool
    {
        return self::normalize($mode) === self::GRID;
    }

    /** @return array<string, string> */
    public static function choices(): array
    {
        return [
            self::LIST => 'Liste',
            self::GRID => 'Vignettes',
        ];
    }
}
