<?php
/**
 * Règles partagées pour e-mail / mot de passe à la création de compte (Phase F).
 *
 * Centralise les messages affichés à l’utilisateur pour inscription et admin.
 */

declare(strict_types=1);

namespace Moncine\Validator;

use Moncine\Exception\ValidationException;
use Moncine\UtilisateurRepository;

final class UserAccountValidator
{
    /**
     * Normalise et valide une adresse e-mail (trim + minuscules).
     *
     * @throws ValidationException
     */
    public static function requireEmail(string $email): string
    {
        return Validator::of($email)
            ->trim()
            ->lower()
            ->email()
            ->orThrow();
    }

    /**
     * Comme requireEmail, mais retourne le message au lieu de lancer une exception.
     *
     * @return true|string true si OK (et $normalized rempli), sinon message d’erreur
     */
    public static function checkEmail(string $email, ?string &$normalized = null): bool|string
    {
        $v = Validator::of($email)->trim()->lower()->email();
        if ($v->failed()) {
            return (string) $v->errorMessage();
        }
        $normalized = $v->value();

        return true;
    }

    /**
     * Vérifie la longueur du mot de passe (mêmes bornes / message que hashPassword).
     *
     * @throws ValidationException
     */
    public static function requirePasswordLength(string $plainPassword): string
    {
        return Validator::of($plainPassword)
            ->byteLengthBetween(
                UtilisateurRepository::MIN_PASSWORD_LENGTH,
                UtilisateurRepository::MAX_PASSWORD_LENGTH,
                UtilisateurRepository::passwordValidationMessage()
            )
            ->orThrow();
    }

    /**
     * @return true|string
     */
    public static function checkPasswordLength(string $plainPassword): bool|string
    {
        return Validator::of($plainPassword)
            ->byteLengthBetween(
                UtilisateurRepository::MIN_PASSWORD_LENGTH,
                UtilisateurRepository::MAX_PASSWORD_LENGTH,
                UtilisateurRepository::passwordValidationMessage()
            )
            ->result();
    }
}
