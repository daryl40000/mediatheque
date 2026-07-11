<?php
/**
 * Limite les tentatives de connexion (session + IP serveur).
 */

declare(strict_types=1);

namespace Moncine;

final class LoginThrottle
{
    private const SCOPE = 'login';

    private const SESSION_KEY = 'moncine_login_throttle';

    private const MAX_ATTEMPTS = 8;

    private const MAX_ATTEMPTS_PER_IP = 24;

    private const WINDOW_SECONDS = 900;

    private const LOCKOUT_SECONDS = 900;

    private static ?LockoutThrottleStore $store = null;

    public static function isBlocked(string $loginIdentifier): bool
    {
        $loginIdentifier = self::normalizeLoginIdentifier($loginIdentifier);

        return $loginIdentifier !== '' && self::store()->isBlocked(self::bucketKey($loginIdentifier));
    }

    public static function secondsUntilUnblock(string $loginIdentifier): int
    {
        $loginIdentifier = self::normalizeLoginIdentifier($loginIdentifier);
        if ($loginIdentifier === '') {
            return 0;
        }

        return self::store()->secondsUntilUnblock(self::bucketKey($loginIdentifier));
    }

    public static function recordFailure(string $loginIdentifier): void
    {
        $loginIdentifier = self::normalizeLoginIdentifier($loginIdentifier);
        if ($loginIdentifier === '') {
            return;
        }

        self::store()->recordAttempt(self::bucketKey($loginIdentifier));
    }

    public static function clearOnSuccess(string $loginIdentifier): void
    {
        $loginIdentifier = self::normalizeLoginIdentifier($loginIdentifier);
        if ($loginIdentifier === '') {
            return;
        }

        self::store()->clear(self::bucketKey($loginIdentifier));
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

    private static function normalizeLoginIdentifier(string $loginIdentifier): string
    {
        return LoginIdentifier::normalizeForThrottle($loginIdentifier);
    }

    private static function bucketKey(string $loginIdentifier): string
    {
        return hash('sha256', $loginIdentifier . '|' . RequestClientIp::resolve());
    }
}
