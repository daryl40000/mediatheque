<?php
/**
 * Normalisation des titres Steam pour rapprochement catalogue / bibliothèque.
 */

declare(strict_types=1);

namespace Moncine;

final class SteamTitleMatch
{
    private const STRIP_SUFFIX_SEPARATORS = [' - ', ': ', ' — ', ' – '];

    /**
     * Clés pliées à tester pour un titre (nom, racine, variante slug Steam avec _).
     *
     * @return list<string>
     */
    public static function foldedKeys(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        $keys = [self::foldKey($title)];
        foreach (self::STRIP_SUFFIX_SEPARATORS as $separator) {
            $pos = mb_strpos($title, $separator);
            if ($pos !== false && $pos > 0) {
                $keys[] = self::foldKey(mb_substr($title, 0, $pos));
            }
        }

        $slug = self::steamStoreSlug($title);
        if ($slug !== '') {
            $keys[] = self::foldKey(str_replace('_', ' ', $slug));
            $keys[] = SearchMatch::fold($slug);
        }

        $fromSlug = str_replace('_', ' ', $title);
        if ($fromSlug !== $title) {
            $keys[] = self::foldKey($fromSlug);
        }

        $unique = [];
        foreach ($keys as $key) {
            if ($key !== '') {
                $unique[$key] = true;
            }
        }

        return array_keys($unique);
    }

    /** Slug type URL Steam : espaces → underscores. */
    public static function steamStoreSlug(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $title = preg_replace('/[™®©]/u', '', $title) ?? $title;
        $title = preg_replace('/[:\'?&,!]/u', '', $title) ?? $title;
        $title = preg_replace('/\s+/u', '_', $title) ?? $title;
        $title = preg_replace('/_+/', '_', $title) ?? $title;
        $title = trim($title, '_');

        return $title;
    }

    public static function foldKey(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        $title = preg_replace('/[™®©]/u', '', $title) ?? $title;
        $title = str_replace('_', ' ', $title);
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return SearchMatch::fold($title);
    }

    /**
     * Titre dérivé du segment d’URL Steam (/app/123/Nom_Avec_Underscores/).
     */
    public static function titleFromStoreUrlSlug(string $url): string
    {
        if (preg_match('~/app/\d+/([^/?#]+)~', $url, $matches) !== 1) {
            return '';
        }

        $slug = rawurldecode((string) ($matches[1] ?? ''));

        return trim(str_replace('_', ' ', $slug));
    }

    /**
     * Indexe un titre catalogue sous plusieurs clés pliées.
     *
     * @param array<string, int> $index
     */
    public static function indexCatalogRow(array $index, array $row, int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        foreach (['titre', 'titre_original'] as $field) {
            foreach (self::foldedKeys((string) ($row[$field] ?? '')) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $oeuvreId;
                }
            }
        }

        foreach (GameGenre::parseList((string) ($row['alternative_names'] ?? '')) as $altName) {
            foreach (self::foldedKeys($altName) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $oeuvreId;
                }
            }
        }
    }

    /**
     * @param array<string, int> $index
     */
    public static function indexTitle(array $index, string $title, int $oeuvreId): void
    {
        if ($oeuvreId <= 0) {
            return;
        }

        foreach (self::foldedKeys($title) as $key) {
            if (!isset($index[$key])) {
                $index[$key] = $oeuvreId;
            }
        }
    }
}
