<?php
/**
 * Connexion, session utilisateur et contrôle d’accès web.
 *
 * Chaque page dans www/ charge bootstrap.php, qui appelle enforceWebAccess().
 * Flux typique : visiteur → page publique OU login OU premier compte OU page protégée.
 */

declare(strict_types=1);

namespace Moncine;

final class Auth
{
    private const SESSION_USER_ID = 'moncine_auth_user_id';

    /** Chemins accessibles sans être connecté (hors configuration initiale). */
    private const PUBLIC_PATHS = [
        '/connexion.php',
        '/premier-compte.php',
        '/inscription.php',
        '/confirmer-inscription.php',
        '/confirmer-email.php',
        '/deconnexion.php',
        '/mot-de-passe-oublie.php',
        '/reinitialiser-mot-de-passe.php',
        '/partage.php',
        '/partage-film.php',
    ];

    /**
     * Barrière d’entrée pour toutes les pages web (sauf CLI).
     * Ordre volontaire : assets/login → installation → compte requis.
     */
    public static function enforceWebAccess(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $path = self::requestPath();
        if (self::isPublicPath($path)) {
            return;
        }

        // Aucun compte avec mot de passe : redirection vers la création du premier admin.
        if (self::needsSetup()) {
            header('Location: /premier-compte.php');
            exit;
        }

        if (!self::isLoggedIn()) {
            $target = '/connexion.php';
            // Après connexion, renvoyer l’utilisateur vers la page qu’il voulait voir.
            if ($path !== '/' && $path !== '') {
                $target .= '?redirect=' . rawurlencode($path);
            }
            header('Location: ' . $target);
            exit;
        }
    }

    public static function needsSetup(): bool
    {
        return (new UtilisateurRepository())->countWithPassword() === 0;
    }

    public static function isLoggedIn(): bool
    {
        return self::currentUserId() > 0;
    }

    /**
     * Identifiant de l’utilisateur connecté, ou 0.
     * On relit la base à chaque fois : un compte désactivé entre-temps ne doit plus rester connecté.
     */
    public static function currentUserId(): int
    {
        self::ensureSession();
        $id = (int) ($_SESSION[self::SESSION_USER_ID] ?? 0);
        if ($id <= 0) {
            return 0;
        }

        $user = (new UtilisateurRepository())->findById($id);
        if ($user === null || (int) ($user['actif'] ?? 0) !== 1) {
            self::logout();

            return 0;
        }

        return $id;
    }

    /** @return array<string, mixed>|null */
    public static function currentUser(): ?array
    {
        $id = self::currentUserId();

        return $id > 0 ? (new UtilisateurRepository())->findById($id) : null;
    }

    public static function isAdmin(): bool
    {
        $user = self::currentUser();
        if ($user === null) {
            return false;
        }

        return UserRole::isAdmin((string) ($user['role'] ?? ''));
    }

    public static function login(string $email, string $password): bool|string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if (LoginThrottle::isBlocked($email)) {
            $minutes = (int) ceil(LoginThrottle::secondsUntilUnblock($email) / 60);

            return 'Trop de tentatives. Réessayez dans environ ' . max(1, $minutes) . ' minute(s).';
        }

        $repo = new UtilisateurRepository();
        $user = $repo->findByEmailForAuthentication($email);
        if ($user === null || (int) ($user['actif'] ?? 0) !== 1 || !UtilisateurRepository::verifyPassword($user, $password)) {
            LoginThrottle::recordFailure($email);

            return 'Identifiants incorrects.';
        }

        self::ensureSession();
        // Nouvel identifiant de session : limite le vol de cookie (fixation de session).
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = (int) $user['id'];
        $repo->updateLastLogin((int) $user['id']);
        $repo->upgradePasswordHashIfNeeded((int) $user['id'], (string) ($user['password_hash'] ?? ''), $password);
        LoginThrottle::clearOnSuccess($email);

        return true;
    }

    /** Vide la session et invalide le cookie côté navigateur. */
    public static function logout(): void
    {
        self::ensureSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    public static function denyUnlessAdmin(string $redirectUrl = '/'): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    private static function requestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private static function isPublicPath(string $path): bool
    {
        if (str_starts_with($path, '/assets/')) {
            return true;
        }

        return in_array($path, self::PUBLIC_PATHS, true);
    }

    /** Une seule session PHP pour le questionnaire « Ce soir » et la connexion. */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            QuizSession::start();
        }
    }
}
