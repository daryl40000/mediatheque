<?php
/**
 * Limite de débit avec verrouillage temporaire : session PHP + fichiers par IP (hors session).
 *
 * Empêche de contourner les throttles en supprimant le cookie de session.
 */

declare(strict_types=1);

namespace Moncine;

final class LockoutThrottleStore
{
    private const DATA_SUBDIR = 'auth_rate_limit';

    /** @var array{attempts: list<int>, locked_until: int} */
    private const EMPTY_ENTRY = ['attempts' => [], 'locked_until' => 0];

    public function __construct(
        private readonly string $scope,
        private readonly string $sessionKey,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly int $lockoutSeconds,
        private readonly int $maxAttemptsPerIp
    ) {
    }

    public function isBlocked(string $bucketKey): bool
    {
        return $this->secondsUntilUnblock($bucketKey) > 0;
    }

    public function secondsUntilUnblock(string $bucketKey): int
    {
        return max(
            $this->remainingLockSeconds($this->readSessionEntry($bucketKey)),
            $this->remainingLockSeconds($this->readFileEntry($this->combinedFilePath($bucketKey))),
            $this->remainingLockSeconds($this->readFileEntry($this->ipFilePath()))
        );
    }

    public function recordAttempt(string $bucketKey): void
    {
        $now = time();
        $this->writeSessionEntry($bucketKey, $this->advanceEntry($this->readSessionEntry($bucketKey), $now, $this->maxAttempts));
        $this->writeFileEntry(
            $this->combinedFilePath($bucketKey),
            $this->advanceEntry($this->readFileEntry($this->combinedFilePath($bucketKey)), $now, $this->maxAttempts)
        );
        $this->writeFileEntry(
            $this->ipFilePath(),
            $this->advanceEntry($this->readFileEntry($this->ipFilePath()), $now, $this->maxAttemptsPerIp)
        );
    }

    /** Réinitialise le compteur pour une clé (ex. connexion réussie). Ne touche pas au plafond IP global. */
    public function clear(string $bucketKey): void
    {
        QuizSession::start();
        unset($_SESSION[$this->sessionKey][$bucketKey]);

        $path = $this->combinedFilePath($bucketKey);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    public static function resetScopeForTests(string $scope): void
    {
        self::removeJsonFilesUnder(MONCINE_DATA . '/' . self::DATA_SUBDIR . '/' . $scope);
    }

    private static function removeJsonFilesUnder(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                self::removeJsonFilesUnder($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array{attempts: list<int>, locked_until: int}
     */
    private function advanceEntry(array $entry, int $now, int $maxAttempts): array
    {
        $entry = $this->normalizeEntry($entry, $now);
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        if ($lockedUntil > $now) {
            return $entry;
        }

        $attempts = is_array($entry['attempts'] ?? null) ? $entry['attempts'] : [];
        $attempts[] = $now;
        $windowSeconds = $this->windowSeconds;
        $attempts = array_values(array_filter(
            $attempts,
            static fn (int $ts): bool => $ts >= $now - $windowSeconds
        ));

        if (count($attempts) >= $maxAttempts) {
            return ['attempts' => [], 'locked_until' => $now + $this->lockoutSeconds];
        }

        return ['attempts' => $attempts, 'locked_until' => 0];
    }

    /**
     * @param array{attempts?: list<int>, locked_until?: int} $entry
     * @return array{attempts: list<int>, locked_until: int}
     */
    private function normalizeEntry(array $entry, int $now): array
    {
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        if ($lockedUntil > 0 && $lockedUntil <= $now) {
            return self::EMPTY_ENTRY;
        }

        $attempts = is_array($entry['attempts'] ?? null) ? $entry['attempts'] : [];
        $windowSeconds = $this->windowSeconds;
        $attempts = array_values(array_filter(
            $attempts,
            static fn (int $ts): bool => $ts >= $now - $windowSeconds
        ));

        return [
            'attempts' => $attempts,
            'locked_until' => $lockedUntil > $now ? $lockedUntil : 0,
        ];
    }

    /**
     * @param array{attempts?: list<int>, locked_until?: int} $entry
     */
    private function remainingLockSeconds(array $entry): int
    {
        $now = time();
        $entry = $this->normalizeEntry($entry, $now);
        $lockedUntil = (int) ($entry['locked_until'] ?? 0);
        if ($lockedUntil <= $now) {
            return 0;
        }

        return $lockedUntil - $now;
    }

    /** @return array{attempts: list<int>, locked_until: int} */
    private function readSessionEntry(string $bucketKey): array
    {
        QuizSession::start();
        $bucket = $_SESSION[$this->sessionKey] ?? null;
        if (!is_array($bucket)) {
            return self::EMPTY_ENTRY;
        }

        $entry = $bucket[$bucketKey] ?? null;

        return is_array($entry) ? $this->normalizeEntry($entry, time()) : self::EMPTY_ENTRY;
    }

    /** @param array{attempts: list<int>, locked_until: int} $entry */
    private function writeSessionEntry(string $bucketKey, array $entry): void
    {
        QuizSession::start();
        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [];
        }
        $_SESSION[$this->sessionKey][$bucketKey] = $entry;
    }

    /** @return array{attempts: list<int>, locked_until: int} */
    private function readFileEntry(string $path): array
    {
        if ($path === '' || !is_file($path)) {
            return self::EMPTY_ENTRY;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return self::EMPTY_ENTRY;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::EMPTY_ENTRY;
        }

        return is_array($decoded) ? $this->normalizeEntry($decoded, time()) : self::EMPTY_ENTRY;
    }

    /** @param array{attempts: list<int>, locked_until: int} $entry */
    private function writeFileEntry(string $path, array $entry): void
    {
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        try {
            @file_put_contents($path, json_encode($entry, JSON_THROW_ON_ERROR), LOCK_EX);
        } catch (\JsonException) {
            // ignore
        }
    }

    private function combinedFilePath(string $bucketKey): string
    {
        $hash = hash('sha256', $this->scope . '|' . $bucketKey);

        return $this->scopeDir() . '/combined/' . $hash . '.json';
    }

    private function ipFilePath(): string
    {
        $ip = RequestClientIp::resolve();
        if ($ip === '0.0.0.0') {
            return '';
        }

        $hash = hash('sha256', $this->scope . '|ip|' . $ip);

        return $this->scopeDir() . '/ip/' . $hash . '.json';
    }

    private function scopeDir(): string
    {
        return MONCINE_DATA . '/' . self::DATA_SUBDIR . '/' . $this->scope;
    }
}
