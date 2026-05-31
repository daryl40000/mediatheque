<?php
/**
 * Statut d’une entrée dans la bibliothèque personnelle.
 */

declare(strict_types=1);

namespace Moncine;

final class LibraryStatut
{
    public const COLLECTION = 'collection';
    public const WISHLIST = 'wishlist';

    public static function normalize(string $raw): string
    {
        $key = mb_strtolower(trim($raw), 'UTF-8');
        return match ($key) {
            'wishlist', 'souhait', 'souhaits', 'mes envies', 'a acheter', 'à acheter', 'chercher', 'recherche' => self::WISHLIST,
            default => self::COLLECTION,
        };
    }

    public static function isValid(string $statut): bool
    {
        return $statut === self::COLLECTION || $statut === self::WISHLIST;
    }

    public static function label(string $statut): string
    {
        $nav = MediaContext::navLabels();

        return match ($statut) {
            self::WISHLIST => $nav['wishlist'],
            default => $nav['collection'],
        };
    }

    /** Libellé pour la recherche par personne (présence dans la bibliothèque). */
    public static function presenceLabel(string $presence): string
    {
        return match ($presence) {
            self::COLLECTION => 'Dans ma collection',
            self::WISHLIST => 'Dans mes envies',
            default => 'Pas dans ma liste',
        };
    }
}
