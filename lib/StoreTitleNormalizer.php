<?php
/**
 * Variantes de titre pour la recherche magasins (GOG, Epic).
 */

declare(strict_types=1);

namespace Moncine;

final class StoreTitleNormalizer
{
    /** @var list<string> */
    private const EDITION_WORDS = [
        'goty',
        'game of the year',
        'deluxe',
        'complete',
        'definitive',
        'ultimate',
        'remastered',
        'remaster',
        'edition',
        'enhanced',
        'directors cut',
        "director's cut",
    ];

    private const STRIP_SUFFIX_SEPARATORS = [' - ', ': ', ' — ', ' – '];

    /**
     * @param array<string, mixed> $catalogRow
     * @return list<string>
     */
    public static function searchQueriesFromRow(array $catalogRow): array
    {
        $title = GameTitle::displayTitle($catalogRow);
        if ($title === '') {
            $title = trim((string) ($catalogRow['titre_original'] ?? ''));
        }

        return self::searchQueries($title);
    }

    /**
     * @return list<string>
     */
    public static function searchQueries(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return [];
        }

        $queries = [$title];

        foreach (self::STRIP_SUFFIX_SEPARATORS as $separator) {
            $pos = mb_strpos($title, $separator);
            if ($pos !== false && $pos > 0) {
                $queries[] = trim(mb_substr($title, 0, $pos));
            }
        }

        $withoutEdition = self::stripEditionWords($title);
        if ($withoutEdition !== '' && $withoutEdition !== $title) {
            $queries[] = $withoutEdition;
        }

        $unique = [];
        foreach ($queries as $query) {
            $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
            if ($query === '') {
                continue;
            }
            $key = SearchMatch::fold($query);
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $query;
            }
        }

        return array_values($unique);
    }

    public static function stripEditionWords(string $title): string
    {
        $folded = SearchMatch::fold($title);
        $result = $title;

        foreach (self::EDITION_WORDS as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/ui';
            $result = trim(preg_replace($pattern, '', $result) ?? $result);
            $result = trim(preg_replace('/\s+/u', ' ', $result) ?? $result);
            $result = trim($result, " -:—–");
            if (SearchMatch::fold($result) === $folded && $word !== '') {
                continue;
            }
        }

        return trim($result);
    }
}
