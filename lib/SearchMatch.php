<?php
/**
 * Recherche tolérante : casse, accents (via FrenchSort) et une faute de frappe par mot.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class SearchMatch
{
    private const DEFAULT_MAX_EDIT_DISTANCE = 1;

    /** @var bool */
    private static bool $sqlRegistered = false;

    /** Enregistre fold_search() pour les requêtes SQL (insensible accents / casse). */
    public static function registerSqlFunction(PDO $pdo): void
    {
        if (self::$sqlRegistered || !method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction(
            'fold_search',
            static fn (?string $value): string => self::fold((string) $value),
            1
        );

        self::$sqlRegistered = true;
    }

    /** @internal Tests PHPUnit */
    public static function resetSqlRegistrationForTests(): void
    {
        self::$sqlRegistered = false;
    }

    /** Minuscules sans accents (réutilise le tri français). */
    public static function fold(string $value): string
    {
        return FrenchSort::fold($value);
    }

    /** Motif LIKE « %query% » sur texte plié (accents / casse). */
    public static function foldedContainsPattern(string $query): string
    {
        return LikePattern::containsFragment(self::fold($query));
    }

    /** Préfixe LIKE sur les N premiers caractères pliés (élargit l’autocomplétion). */
    public static function foldedPrefixPattern(string $query, int $length = 2): string
    {
        $folded = self::fold($query);
        if (mb_strlen($folded) < $length) {
            return '';
        }

        return LikePattern::escapeLiteral(mb_substr($folded, 0, $length)) . '%';
    }

    /**
     * Le texte correspond-il à la requête (mots séparés, distance d’édition limitée) ?
     */
    public static function matches(string $haystack, string $query, int $maxEditDistance = self::DEFAULT_MAX_EDIT_DISTANCE): bool
    {
        $query = trim($query);
        if ($query === '') {
            return true;
        }

        $haystackF = self::fold($haystack);
        $tokens = preg_split('/\s+/u', self::fold($query), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || $tokens === []) {
            return true;
        }

        foreach ($tokens as $token) {
            if (!self::tokenMatches($haystackF, $token, $maxEditDistance)) {
                return false;
            }
        }

        return true;
    }

    /** Score de pertinence (plus bas = meilleur). */
    public static function score(string $haystack, string $query): int
    {
        $haystackF = self::fold($haystack);
        $queryF = self::fold($query);
        if ($queryF === '') {
            return 0;
        }

        if (str_starts_with($haystackF, $queryF)) {
            return 0;
        }

        if (str_contains($haystackF, $queryF)) {
            return 10;
        }

        $tokens = preg_split('/\s+/u', $queryF, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || $tokens === []) {
            return 0;
        }

        $total = 100;
        foreach ($tokens as $token) {
            $total += self::tokenBestDistance($haystackF, $token);
        }

        return $total;
    }

    /**
     * Filtre, trie par pertinence et limite les lignes candidates.
     *
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $textFromRow
     * @return list<array<string, mixed>>
     */
    public static function filterRankLimit(
        array $rows,
        string $query,
        callable $textFromRow,
        int $limit,
        int $maxEditDistance = self::DEFAULT_MAX_EDIT_DISTANCE
    ): array {
        $query = trim($query);
        if ($query === '') {
            return array_slice($rows, 0, max(1, $limit));
        }

        $scored = [];
        foreach ($rows as $row) {
            $text = $textFromRow($row);
            if (!self::matches($text, $query, $maxEditDistance)) {
                continue;
            }

            $scored[] = [
                'row' => $row,
                'score' => self::score($text, $query),
                'label' => self::fold($text),
            ];
        }

        usort($scored, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $left['score'] <=> $right['score'];
            }

            return FrenchSort::compare($left['label'], $right['label']);
        });

        $out = [];
        foreach (array_slice($scored, 0, max(1, $limit)) as $entry) {
            $out[] = $entry['row'];
        }

        return $out;
    }

    private static function tokenMatches(string $haystackF, string $token, int $maxEditDistance): bool
    {
        if ($token === '') {
            return true;
        }

        if (str_contains($haystackF, $token)) {
            return true;
        }

        if ($maxEditDistance <= 0 || mb_strlen($token) < 3) {
            return false;
        }

        return self::tokenBestDistance($haystackF, $token) <= $maxEditDistance;
    }

    private static function tokenBestDistance(string $haystackF, string $token): int
    {
        if (str_contains($haystackF, $token)) {
            return 0;
        }

        $tokenLen = mb_strlen($token);
        if ($tokenLen < 3) {
            return 99;
        }

        $hayLen = mb_strlen($haystackF);
        $best = 99;

        for ($windowLen = max(1, $tokenLen - 1); $windowLen <= min($hayLen, $tokenLen + 1); $windowLen++) {
            for ($offset = 0; $offset <= $hayLen - $windowLen; $offset++) {
                $chunk = mb_substr($haystackF, $offset, $windowLen);
                $best = min($best, levenshtein($chunk, $token));
                if ($best === 0) {
                    return 0;
                }
            }
        }

        return $best;
    }
}
