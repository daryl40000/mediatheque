<?php
/**
 * Limite les exports / restaurations de base (session + IP, anti abus).
 */

declare(strict_types=1);

namespace Moncine;

final class DatabaseBackupRateLimit
{
    private const SESSION_KEY = 'moncine_db_backup_rate';

    /** Opérations réussies (export ou restauration) par fenêtre. */
    private const MAX_OPERATIONS = 6;

    private const OPERATION_WINDOW_SECONDS = 3600;

    /** Échecs de mot de passe par fenêtre. */
    private const MAX_PASSWORD_FAILURES = 5;

    private const PASSWORD_WINDOW_SECONDS = 900;

    private const IP_STORE_DIR = 'db_backup_rate_limit';

    public static function allowOperation(): bool
    {
        return self::countRecentOperations() < self::MAX_OPERATIONS
            && self::countRecentIpOperations() < self::MAX_OPERATIONS;
    }

    public static function allowPasswordAttempt(): bool
    {
        return self::countRecentPasswordFailures() < self::MAX_PASSWORD_FAILURES
            && self::countRecentIpPasswordFailures() < self::MAX_PASSWORD_FAILURES;
    }

    public static function recordOperation(): void
    {
        self::recordTimestamp('operations');
        self::recordIpTimestamp('operations');
    }

    public static function recordPasswordFailure(): void
    {
        self::recordTimestamp('password_failures');
        self::recordIpTimestamp('password_failures');
    }

    public static function resetForTests(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY]);
        self::clearIpStoreForTests();
    }

    private static function recordTimestamp(string $bucketKey): void
    {
        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }
        $list = $bucket[$bucketKey] ?? [];
        if (!is_array($list)) {
            $list = [];
        }
        $list[] = time();
        $bucket[$bucketKey] = $list;
        $_SESSION[self::SESSION_KEY] = $bucket;
    }

    private static function countRecentOperations(): int
    {
        return self::countRecentInBucket('operations', self::OPERATION_WINDOW_SECONDS);
    }

    private static function countRecentPasswordFailures(): int
    {
        return self::countRecentInBucket('password_failures', self::PASSWORD_WINDOW_SECONDS);
    }

    private static function countRecentInBucket(string $bucketKey, int $windowSeconds): int
    {
        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bucket)) {
            return 0;
        }
        $attempts = $bucket[$bucketKey] ?? null;
        if (!is_array($attempts)) {
            return 0;
        }

        return self::pruneAndCount($attempts, $windowSeconds, static function (array $recent) use ($bucketKey): void {
            QuizSession::start();
            $bucket = $_SESSION[self::SESSION_KEY] ?? [];
            if (!is_array($bucket)) {
                $bucket = [];
            }
            $bucket[$bucketKey] = $recent;
            $_SESSION[self::SESSION_KEY] = $bucket;
        });
    }

    private static function recordIpTimestamp(string $bucketKey): void
    {
        $path = self::ipStorePath($bucketKey);
        if ($path === '') {
            return;
        }

        $attempts = self::readIpAttempts($path);
        $attempts[] = time();
        self::writeIpAttempts($path, $attempts);
    }

    private static function countRecentIpOperations(): int
    {
        return self::countRecentIpBucket('operations', self::OPERATION_WINDOW_SECONDS);
    }

    private static function countRecentIpPasswordFailures(): int
    {
        return self::countRecentIpBucket('password_failures', self::PASSWORD_WINDOW_SECONDS);
    }

    private static function countRecentIpBucket(string $bucketKey, int $windowSeconds): int
    {
        $path = self::ipStorePath($bucketKey);
        if ($path === '') {
            return 0;
        }

        $attempts = self::readIpAttempts($path);

        return self::pruneAndCount($attempts, $windowSeconds, static function (array $recent) use ($path): void {
            self::writeIpAttempts($path, $recent);
        });
    }

    /**
     * @param list<int> $attempts
     * @param callable(list<int>): void $persist
     */
    private static function pruneAndCount(array $attempts, int $windowSeconds, callable $persist): int
    {
        $now = time();
        $recent = array_values(array_filter(
            $attempts,
            static fn ($ts): bool => is_int($ts) && $ts >= $now - $windowSeconds
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

    private static function ipStorePath(string $bucketKey): string
    {
        $ip = RequestClientIp::resolve();
        if ($ip === '0.0.0.0') {
            return '';
        }

        $dir = MONCINE_DATA . '/' . self::IP_STORE_DIR;
        $hash = hash('sha256', $ip . '|' . $bucketKey);

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
