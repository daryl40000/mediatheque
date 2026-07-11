<?php
/**
 * Utilitaires URL catalogue GOG (saisie manuelle admin).
 */

declare(strict_types=1);

namespace Moncine;

final class GogCatalogClient
{
    public static function storeUrl(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        return 'https://www.gog.com/game/' . $slug;
    }

    public static function slugFromStoreUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~gog\.com/(?:[a-z]{2}/)?game/([^/?#]+)~i', $url, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    public static function normalizeImageUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }
}
