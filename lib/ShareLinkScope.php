<?php
/**
 * Portée d’un lien de partage visiteur.
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkScope
{
    public const COLLECTION = 'collection';
    public const WISHLIST = 'wishlist';

    public static function normalize(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return $scope === self::WISHLIST ? self::WISHLIST : self::COLLECTION;
    }

    public static function label(string $scope, string $mediaDomain = MediaDomain::FILM): string
    {
        $domain = MediaDomain::normalize($mediaDomain);

        return match (self::normalize($scope)) {
            self::WISHLIST => $domain === MediaDomain::JEU ? 'Mes envies jeux' : 'Mes envies',
            default => $domain === MediaDomain::JEU ? 'Mes jeux' : 'Mes films',
        };
    }
}
