<?php
/**
 * Identifiant de connexion : adresse e-mail ou pseudo (option A).
 *
 * Si la saisie contient « @ », elle est traitée comme un e-mail ; sinon comme un pseudo.
 */

declare(strict_types=1);

namespace Moncine;

final class LoginIdentifier
{
    /** La saisie ressemble à une adresse e-mail (contient @). */
    public static function isEmailLogin(string $login): bool
    {
        return str_contains(trim($login), '@');
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /** Clé de recherche pseudo (insensible à la casse). */
    public static function normalizePseudoLookup(string $pseudo): string
    {
        return mb_strtolower(UserProfile::sanitizePseudo($pseudo), 'UTF-8');
    }

    /** Normalisation pour le limiteur de tentatives (e-mail ou pseudo). */
    public static function normalizeForThrottle(string $login): string
    {
        $login = trim($login);
        if ($login === '') {
            return '';
        }

        return self::isEmailLogin($login)
            ? self::normalizeEmail($login)
            : self::normalizePseudoLookup($login);
    }
}
