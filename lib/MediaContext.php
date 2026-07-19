<?php
/**
 * Domaine média actif (session utilisateur).
 */

declare(strict_types=1);

namespace Moncine;

final class MediaContext
{
    private const SESSION_KEY = 'moncine_media_domain';

    public static function bootstrap(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (isset($_GET['media_domain']) && is_string($_GET['media_domain'])) {
            self::set((string) $_GET['media_domain']);
        }
    }

    public static function current(): string
    {
        // En CLI (PHPUnit), respecter la session si elle est active — sinon défaut film.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return MediaDomain::FILM;
        }

        return MediaDomain::normalize((string) ($_SESSION[self::SESSION_KEY] ?? MediaDomain::FILM));
    }

    public static function set(string $domain): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[self::SESSION_KEY] = MediaDomain::normalize($domain);
    }

    /** @return array{collection: string, wishlist: string, stats: string, footer: string} */
    public static function navLabels(): array
    {
        return MediaDomain::navLabels(self::current());
    }
}
