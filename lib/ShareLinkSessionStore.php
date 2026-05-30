<?php
/**
 * Mémorise temporairement l’URL complète d’un lien de partage (jeton visible une seule fois à la création).
 */

declare(strict_types=1);

namespace Moncine;

final class ShareLinkSessionStore
{
    private const SESSION_KEY = 'moncine_share_link_urls';

    /** Durée de mémorisation en session (24 h). */
    private const TTL_SECONDS = 86400;

    public static function remember(int $linkId, string $absoluteUrl): void
    {
        if ($linkId <= 0 || trim($absoluteUrl) === '') {
            return;
        }

        QuizSession::start();
        $bucket = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($bucket)) {
            $bucket = [];
        }

        $bucket[(string) $linkId] = [
            'url' => $absoluteUrl,
            'until' => time() + self::TTL_SECONDS,
        ];
        $_SESSION[self::SESSION_KEY] = $bucket;
    }

    public static function get(int $linkId): ?string
    {
        if ($linkId <= 0) {
            return null;
        }

        QuizSession::start();
        self::pruneExpired();

        $bucket = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bucket)) {
            return null;
        }

        $entry = $bucket[(string) $linkId] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        $url = trim((string) ($entry['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        $until = (int) ($entry['until'] ?? 0);
        if ($until > 0 && $until < time()) {
            unset($bucket[(string) $linkId]);
            $_SESSION[self::SESSION_KEY] = $bucket;

            return null;
        }

        return $url;
    }

    /**
     * @return array<int, string> link_id => URL absolue
     */
    public static function allForUserLinks(array $links): array
    {
        $map = [];
        foreach ($links as $link) {
            $id = (int) ($link['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $url = self::get($id);
            if ($url !== null) {
                $map[$id] = $url;
            }
        }

        return $map;
    }

    public static function resetForTests(): void
    {
        QuizSession::start();
        unset($_SESSION[self::SESSION_KEY]);
    }

    private static function pruneExpired(): void
    {
        $bucket = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($bucket)) {
            return;
        }

        $now = time();
        $changed = false;
        foreach ($bucket as $key => $entry) {
            if (!is_array($entry)) {
                unset($bucket[$key]);
                $changed = true;
                continue;
            }
            $until = (int) ($entry['until'] ?? 0);
            if ($until > 0 && $until < $now) {
                unset($bucket[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            $_SESSION[self::SESSION_KEY] = $bucket;
        }
    }
}
