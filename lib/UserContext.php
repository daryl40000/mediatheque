<?php
/**
 * Utilisateur courant (session de connexion).
 *
 * Point d’accès pour le code métier : quel user_id / foyer_id utiliser.
 * Délègue à Auth ; redirige vers la connexion si personne n’est connecté.
 */

declare(strict_types=1);

namespace Moncine;

final class UserContext
{
    /** @deprecated Ancien mono-utilisateur (id 1) ; ne plus utiliser. */
    public const DEFAULT_USER_ID = 1;

    /** ID SQLite de l’utilisateur connecté ; arrête la page si session absente. */
    public static function currentUserId(): int
    {
        $id = Auth::currentUserId();
        if ($id > 0) {
            return $id;
        }

        if (Auth::needsSetup()) {
            return 0;
        }

        Auth::enforceWebAccess();
        exit;
    }

    /** ID du foyer de l’utilisateur connecté (collection partagée). */
    public static function currentFoyerId(): int
    {
        $userId = Auth::currentUserId();
        if ($userId <= 0) {
            return 0;
        }

        static $cache = [];
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
        if ($foyerId <= 0 && FoyerRepository::tableExists(Database::getInstance())) {
            $foyerId = (new FoyerRepository())->ensurePersonalFoyerForUser($userId);
        }
        $cache[$userId] = $foyerId;

        return $foyerId;
    }

    public static function canManageCatalog(): bool
    {
        return Auth::isAdmin();
    }

    public static function canManageFoyers(): bool
    {
        return Auth::isAdmin();
    }
}
