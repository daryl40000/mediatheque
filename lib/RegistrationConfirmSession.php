<?php
/**
 * Jeton de confirmation d’inscription en session (évite de le laisser dans l’URL après le premier chargement).
 */

declare(strict_types=1);

namespace Moncine;

final class RegistrationConfirmSession
{
    private const SESSION_KEY = 'moncine_registration_confirm_token';

    private const SESSION_AT = 'moncine_registration_confirm_token_at';

    /** Durée de validité du jeton en session (secondes). */
    private const SESSION_TTL = 1800;

    public static function storeFromQueryToken(string $plainToken): bool
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return false;
        }

        $service = new RegistrationService();
        if (!$service->isConfirmTokenValid($plainToken)) {
            return false;
        }

        QuizSession::start();
        $_SESSION[self::SESSION_KEY] = $plainToken;
        $_SESSION[self::SESSION_AT] = time();

        return true;
    }

    public static function getPlainToken(): string
    {
        QuizSession::start();
        $token = $_SESSION[self::SESSION_KEY] ?? '';
        $at = (int) ($_SESSION[self::SESSION_AT] ?? 0);
        if (!is_string($token) || $token === '' || $at <= 0 || time() - $at > self::SESSION_TTL) {
            self::clear();

            return '';
        }

        return $token;
    }

    public static function clear(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_AT]);
    }
}
