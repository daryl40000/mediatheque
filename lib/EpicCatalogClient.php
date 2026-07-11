<?php
/**
 * Utilitaires URL Epic Games Store (saisie manuelle admin).
 */

declare(strict_types=1);

namespace Moncine;

final class EpicCatalogClient
{
    public static function storeUrl(string $slug): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return '';
        }

        return 'https://store.epicgames.com/p/' . $slug;
    }

    public static function slugFromStoreUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~store\.epicgames\.com/p/([^/?#]+)~i', $url, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }
}
