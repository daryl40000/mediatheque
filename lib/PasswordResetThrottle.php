<?php
/**
 * Limite les demandes « mot de passe oublié » (session + IP serveur).
 */

declare(strict_types=1);

namespace Moncine;

final class PasswordResetThrottle
{
    private const SCOPE = 'password_reset';

    private const SESSION_KEY = 'moncine_password_reset_throttle';

    private const MAX_ATTEMPTS = 5;

    private const MAX_ATTEMPTS_PER_IP = 15;

    private const WINDOW_SECONDS = 900;

    private const LOCKOUT_SECONDS = 900;

    private static ?LockoutThrottleStore $store = null;

    public static function isBlocked(string $email): bool
    {
        $email = self::normalizeEmail($email);

        return $email !== '' && self::store()->isBlocked(self::bucketKey($email));
    }

    public static function recordAttempt(string $email): void
    {
        $email = self::normalizeEmail($email);
        if ($email === '') {
            return;
        }

        self::store()->recordAttempt(self::bucketKey($email));
    }

    public static function resetForTests(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY]);
        LockoutThrottleStore::resetScopeForTests(self::SCOPE);
        self::$store = null;
    }

    private static function store(): LockoutThrottleStore
    {
        return self::$store ??= new LockoutThrottleStore(
            self::SCOPE,
            self::SESSION_KEY,
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
            self::LOCKOUT_SECONDS,
            self::MAX_ATTEMPTS_PER_IP
        );
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private static function bucketKey(string $email): string
    {
        return hash('sha256', 'reset|' . $email . '|' . RequestClientIp::resolve());
    }
}
