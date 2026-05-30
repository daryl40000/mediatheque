<?php
/**
 * Protection CSRF pour tous les formulaires POST de l’application.
 *
 * Chaque formulaire inclut templates/_csrf_field.php ; les pages POST appellent
 * Csrf::rejectUnlessValid() pour refuser les requêtes forgées depuis un autre site.
 */

declare(strict_types=1);

namespace Moncine;

final class Csrf
{
    public const FIELD_NAME = 'csrf_token';

    public const REJECT_MESSAGE = 'Action refusée : rechargez la page puis réessayez.';

    private const SESSION_KEY = 'moncine_csrf_token';

    public static function getToken(): string
    {
        self::ensureSession();

        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function validate(?string $submitted): bool
    {
        self::ensureSession();

        if ($submitted === null || $submitted === '') {
            return false;
        }

        $expected = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /** @param array<string, mixed> $post */
    public static function validateFromPost(array $post): bool
    {
        $submitted = isset($post[self::FIELD_NAME]) ? (string) $post[self::FIELD_NAME] : null;

        return self::validate($submitted);
    }

    /**
     * Redirige vers $url si le jeton POST est absent ou invalide.
     *
     * @param array<string, mixed> $post
     */
    public static function rejectUnlessValid(array $post, string $redirectUrl): void
    {
        if (self::validateFromPost($post)) {
            return;
        }

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';
        header('Location: ' . $redirectUrl . $separator . 'csrf_error=1');
        exit;
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            QuizSession::start();
        }
    }
}
