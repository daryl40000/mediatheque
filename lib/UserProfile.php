<?php
/**
 * Affichage et validation du profil utilisateur (nom, prénom, pseudo, ville).
 */

declare(strict_types=1);

namespace Moncine;

final class UserProfile
{
    public const MAX_PSEUDO_LENGTH = 40;

    public const MAX_VILLE_LENGTH = 80;

    /** Nom affiché dans l’interface : pseudo, sinon « Prénom Nom ». */
    public static function displayName(array $user): string
    {
        $pseudo = trim((string) ($user['pseudo'] ?? ''));
        if ($pseudo !== '') {
            return $pseudo;
        }

        $prenom = trim((string) ($user['prenom'] ?? ''));
        $nom = trim((string) ($user['nom'] ?? ''));
        if ($prenom !== '' && $nom !== '') {
            return $prenom . ' ' . $nom;
        }
        if ($prenom !== '') {
            return $prenom;
        }
        if ($nom !== '') {
            return $nom;
        }

        return 'Utilisateur';
    }

    public static function sanitizePseudo(string $pseudo): string
    {
        $pseudo = trim($pseudo);
        if ($pseudo === '') {
            return '';
        }

        if (mb_strlen($pseudo, 'UTF-8') > self::MAX_PSEUDO_LENGTH) {
            $pseudo = mb_substr($pseudo, 0, self::MAX_PSEUDO_LENGTH, 'UTF-8');
        }

        return $pseudo;
    }

    public static function sanitizeVille(string $ville): string
    {
        $ville = trim($ville);
        if ($ville === '') {
            return '';
        }

        if (mb_strlen($ville, 'UTF-8') > self::MAX_VILLE_LENGTH) {
            $ville = mb_substr($ville, 0, self::MAX_VILLE_LENGTH, 'UTF-8');
        }

        return $ville;
    }

    /** Compte visible dans la recherche par pseudo / ville. */
    public static function isSearchable(array $user): bool
    {
        return (int) ($user['searchable'] ?? 1) === 1;
    }

    /**
     * @return true|string
     */
    public static function validateIdentityFields(string $nom, string $prenom, string $pseudo): bool|string
    {
        $nom = trim($nom);
        $prenom = trim($prenom);
        $pseudo = self::sanitizePseudo($pseudo);

        if ($nom === '' && $prenom === '' && $pseudo === '') {
            return 'Indiquez au moins un nom, un prénom ou un pseudo.';
        }

        return true;
    }
}
