<?php
/**
 * Limitation d’abus sur les fonctionnalités sociales (recherche, demandes d’ami).
 */

declare(strict_types=1);

namespace Moncine;

final class SocialRateLimit
{
    private const SESSION_KEY = 'moncine_social_rate';

    /** Demandes d’ami max par utilisateur sur 24 h. */
    private const FRIEND_REQUEST_MAX = 20;

    private const FRIEND_REQUEST_WINDOW = 86400;

    /** Recherches utilisateurs max par minute. */
    private const SEARCH_MAX = 30;

    private const SEARCH_WINDOW = 60;

    public static function allowFriendRequest(int $userId): bool
    {
        return self::countInWindow(self::friendRequestKey($userId), self::FRIEND_REQUEST_WINDOW)
            < self::FRIEND_REQUEST_MAX;
    }

    public static function recordFriendRequest(int $userId): void
    {
        self::record(self::friendRequestKey($userId));
    }

    /** @return positive-int secondes avant nouvelle tentative autorisée, ou 0 */
    public static function secondsUntilFriendRequestAllowed(int $userId): int
    {
        return self::secondsUntilAllowed(
            self::friendRequestKey($userId),
            self::FRIEND_REQUEST_WINDOW,
            self::FRIEND_REQUEST_MAX
        );
    }

    public static function friendRequestLimitMessage(int $userId): string
    {
        $seconds = self::secondsUntilFriendRequestAllowed($userId);
        if ($seconds <= 0) {
            return 'Trop de demandes d’ami envoyées. Réessayez plus tard.';
        }
        $hours = (int) ceil($seconds / 3600);

        return 'Trop de demandes d’ami envoyées. Réessayez dans environ '
            . max(1, $hours) . ' heure(s).';
    }

    public static function allowUserSearch(int $userId): bool
    {
        return self::countInWindow(self::searchKey($userId), self::SEARCH_WINDOW)
            < self::SEARCH_MAX;
    }

    public static function recordUserSearch(int $userId): void
    {
        self::record(self::searchKey($userId));
    }

    public static function userSearchLimitMessage(): string
    {
        return 'Trop de recherches en peu de temps. Patientez une minute puis réessayez.';
    }

    /** Utilisé par les tests PHPUnit. */
    public static function resetForTests(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function friendRequestKey(int $userId): string
    {
        return 'friend_req:' . $userId;
    }

    private static function searchKey(int $userId): string
    {
        return 'user_search:' . $userId;
    }

    private static function record(string $key): void
    {
        if ($key === '') {
            return;
        }

        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }

        $attempts = $bucket[$key] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $now = time();
        $attempts[] = $now;
        $bucket[$key] = $attempts;
        $_SESSION[self::SESSION_KEY] = $bucket;
    }

    private static function countInWindow(string $key, int $windowSeconds): int
    {
        if ($key === '') {
            return 0;
        }

        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bucket)) {
            return 0;
        }

        $attempts = $bucket[$key] ?? null;
        if (!is_array($attempts)) {
            return 0;
        }

        $now = time();
        $recent = array_values(array_filter(
            $attempts,
            static fn ($ts): bool => is_int($ts) && $ts >= $now - $windowSeconds
        ));

        if ($recent !== $attempts) {
            $bucket[$key] = $recent;
            $_SESSION[self::SESSION_KEY] = $bucket;
        }

        return count($recent);
    }

    private static function secondsUntilAllowed(string $key, int $windowSeconds, int $max): int
    {
        if ($key === '' || self::countInWindow($key, $windowSeconds) < $max) {
            return 0;
        }

        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? [];
        $attempts = is_array($bucket[$key] ?? null) ? $bucket[$key] : [];
        if ($attempts === []) {
            return 0;
        }

        $oldest = min($attempts);
        $unlockAt = $oldest + $windowSeconds;
        $remaining = $unlockAt - time();

        return $remaining > 0 ? $remaining : 0;
    }
}
