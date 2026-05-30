<?php
/**
 * Portée d’affichage de la page Mes envies (personnelle ou groupe famille).
 */

declare(strict_types=1);

namespace Moncine;

final class WishlistScope
{
    public const MINE = 'mine';
    public const GROUP = 'group';

    public static function normalize(string $scope): string
    {
        return $scope === self::GROUP ? self::GROUP : self::MINE;
    }

    public static function label(string $scope): string
    {
        return $scope === self::GROUP ? 'Envies du groupe' : 'Mes envies';
    }
}
