<?php
/**
 * Limite les tentatives de validation d’un jeton de partage (session + IP, anti brute-force).
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkRateLimit
{
    private const SESSION_KEY = 'moncine_share_rate';

    private const MAX_ATTEMPTS = 40;

    private const WINDOW_SECONDS = 300;

    private const IP_STORE_DIR = 'share_rate_limit';

    public static function allowAttempt(): bool
    {
        return self::countRecentSession() < self::MAX_ATTEMPTS
            && self::countRecentIp() < self::MAX_ATTEMPTS;
    }

    public static function recordFailure(): void
    {
        self::recordSession();
        self::recordIp();
    }

    public static function resetForTests(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY]);
        self::clearIpStoreForTests();
    }

    private static function recordSession(): void
    {
        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        $attempts = $bucket['failures'] ?? [];
        if (!is_array($attempts)) {
            $attempts = [];
        }
        $attempts[] = time();
        $bucket['failures'] = $attempts;
        $_SESSION[self::SESSION_KEY] = $bucket;
    }

    private static function countRecentSession(): int
    {
        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bucket)) {
            return 0;
        }
        $attempts = $bucket['failures'] ?? null;
        if (!is_array($attempts)) {
            return 0;
        }

        $now = time();
        $recent = array_values(array_filter(
            $attempts,
            static fn ($ts): bool => is_int($ts) && $ts >= $now - self::WINDOW_SECONDS
        ));
        if ($recent !== $attempts) {
            $bucket['failures'] = $recent;
            $_SESSION[self::SESSION_KEY] = $bucket;
        }

        return count($recent);
    }

    private static function recordIp(): void
    {
        $path = self::ipStorePath();
        if ($path === '') {
            return;
        }

        $attempts = self::readIpAttempts($path);
        $attempts[] = time();
        self::writeIpAttempts($path, $attempts);
    }

    private static function countRecentIp(): int
    {
        $path = self::ipStorePath();
        if ($path === '') {
            return 0;
        }

        $attempts = self::readIpAttempts($path);

        return self::pruneAndCount($attempts, static function (array $recent) use ($path): void {
            self::writeIpAttempts($path, $recent);
        });
    }

    /**
     * @param list<int> $attempts
     * @param callable(list<int>): void $persist
     */
    private static function pruneAndCount(array $attempts, callable $persist): int
    {
        $now = time();
        $recent = array_values(array_filter(
            $attempts,
            static fn ($ts): bool => is_int($ts) && $ts >= $now - self::WINDOW_SECONDS
        ));
        if ($recent !== $attempts) {
            $persist($recent);
        }

        return count($recent);
    }

    /** @return list<int> */
    private static function readIpAttempts(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($ts): bool => is_int($ts)));
    }

    /** @param list<int> $attempts */
    private static function writeIpAttempts(string $path, array $attempts): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($path, json_encode($attempts, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private static function ipStorePath(): string
    {
        $ip = RequestClientIp::resolve();
        if ($ip === '0.0.0.0') {
            return '';
        }

        $dir = MONCINE_DATA . '/' . self::IP_STORE_DIR;
        $hash = hash('sha256', $ip);

        return $dir . '/' . $hash . '.json';
    }

    private static function clearIpStoreForTests(): void
    {
        $dir = MONCINE_DATA . '/' . self::IP_STORE_DIR;
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
