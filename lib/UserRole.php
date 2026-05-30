<?php
/**
 * Rôles des comptes Moncine.
 *
 * admin : gère le catalogue partagé et les comptes (/catalogue.php, /utilisateurs.php).
 * user  : sa bibliothèque et ses envies uniquement.
 */

declare(strict_types=1);

namespace Moncine;

final class UserRole
{
    public const ADMIN = 'admin';
    public const USER = 'user';

    /** Valeur sûre pour la base : seul « admin » est conservé tel quel. */
    public static function normalize(string $raw): string
    {
        return match (mb_strtolower(trim($raw), 'UTF-8')) {
            self::ADMIN => self::ADMIN,
            default => self::USER,
        };
    }

    public static function isAdmin(string $role): bool
    {
        return self::normalize($role) === self::ADMIN;
    }

    public static function label(string $role): string
    {
        return match (self::normalize($role)) {
            self::ADMIN => 'Administrateur',
            default => 'Utilisateur',
        };
    }
}
