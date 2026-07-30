<?php
/**
 * Catégories de livre (Jeux vidéo, Cinéma…).
 *
 * Définies sur la fiche livre. La catégorie « Jeux vidéo » autorise
 * les liens vers les jeux dont parle le livre.
 */

declare(strict_types=1);

namespace Moncine;

final class LivreCategory
{
    public const JEUX_VIDEO = 'Jeux vidéo';
    public const CINEMA = 'Cinéma';
    public const FIGURINES = 'Figurines';
    public const DIVERS = 'Divers';

    /** @return list<string> */
    public static function defaultLabels(): array
    {
        return [
            self::JEUX_VIDEO,
            self::CINEMA,
            self::FIGURINES,
            self::DIVERS,
        ];
    }

    /** @return array<string, string> clé normalisée => libellé affiché */
    private static function canonicalLabels(): array
    {
        $labels = [];
        foreach (self::defaultLabels() as $label) {
            $labels[self::labelKey($label)] = $label;
        }

        $aliases = [
            'jeux video' => self::JEUX_VIDEO,
            'jeux videos' => self::JEUX_VIDEO,
            'jeu video' => self::JEUX_VIDEO,
            'jeu vidéo' => self::JEUX_VIDEO,
            'jeux vidéos' => self::JEUX_VIDEO,
            'video game' => self::JEUX_VIDEO,
            'video games' => self::JEUX_VIDEO,
            'game' => self::JEUX_VIDEO,
            'games' => self::JEUX_VIDEO,
            'cinema' => self::CINEMA,
            'ciné' => self::CINEMA,
            'cine' => self::CINEMA,
            'film' => self::CINEMA,
            'films' => self::CINEMA,
            'figurine' => self::FIGURINES,
            'figure' => self::FIGURINES,
            'figures' => self::FIGURINES,
            'divers' => self::DIVERS,
            'misc' => self::DIVERS,
            'autre' => self::DIVERS,
            'autres' => self::DIVERS,
        ];

        foreach ($aliases as $aliasKey => $label) {
            $labels[$aliasKey] = $label;
        }

        return $labels;
    }

    public static function normalizeLabel(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $key = self::labelKey($raw);
        $canonical = self::canonicalLabels();

        return $canonical[$key] ?? $raw;
    }

    /** @return list<string> */
    public static function parseList(string $raw): array
    {
        $categories = [];
        foreach (preg_split('/[,;]+/', trim($raw)) ?: [] as $part) {
            $label = self::normalizeLabel((string) $part);
            if ($label === '') {
                continue;
            }
            $key = self::labelKey($label);
            if (!isset($categories[$key])) {
                $categories[$key] = $label;
            }
        }

        return array_values($categories);
    }

    /** @param array<int, string> $categories */
    public static function serializeList(array $categories): string
    {
        $out = [];
        foreach ($categories as $category) {
            $label = self::normalizeLabel((string) $category);
            if ($label === '') {
                continue;
            }
            $key = self::labelKey($label);
            if (!isset($out[$key])) {
                $out[$key] = $label;
            }
        }

        return implode(', ', array_values($out));
    }

    public static function normalizeInput(string $raw): string
    {
        return self::serializeList(self::parseList($raw));
    }

    /** @param array<int, string>|string $raw */
    public static function normalizeFromPost(array|string $raw): string
    {
        if (is_array($raw)) {
            return self::serializeList($raw);
        }

        return self::normalizeInput($raw);
    }

    /** @param array<int|string, mixed> $book */
    public static function listForBook(array $book): array
    {
        return self::parseList((string) ($book['categories'] ?? ''));
    }

    /**
     * @param list<string>|string|array<string, mixed> $categoriesOrBook
     */
    public static function includesJeuxVideo(array|string $categoriesOrBook): bool
    {
        if (is_array($categoriesOrBook) && array_key_exists('categories', $categoriesOrBook)) {
            $list = self::listForBook($categoriesOrBook);
        } elseif (is_string($categoriesOrBook)) {
            $list = self::parseList($categoriesOrBook);
        } else {
            $list = [];
            foreach ($categoriesOrBook as $item) {
                $label = self::normalizeLabel((string) $item);
                if ($label !== '') {
                    $list[] = $label;
                }
            }
        }

        foreach ($list as $label) {
            if (self::normalizeLabel((string) $label) === self::JEUX_VIDEO) {
                return true;
            }
        }

        return false;
    }

    public static function filterKey(string $label): string
    {
        return self::labelKey(self::normalizeLabel($label));
    }

    /**
     * @param array<string, mixed> $book
     * @return list<string>
     */
    public static function filterKeysForBook(array $book): array
    {
        $keys = [];
        foreach (self::listForBook($book) as $label) {
            $key = self::filterKey($label);
            if ($key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param list<array<string, mixed>> $bookList
     * @return list<array{key: string, label: string, count: int}>
     */
    public static function filterChoicesForBookList(array $bookList): array
    {
        /** @var array<string, array{label: string, count: int}> $choices */
        $choices = [];
        foreach (self::defaultLabels() as $label) {
            $key = self::filterKey($label);
            $choices[$key] = ['label' => $label, 'count' => 0];
        }

        foreach ($bookList as $book) {
            $seen = [];
            foreach (self::listForBook($book) as $label) {
                $key = self::filterKey($label);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if (!isset($choices[$key])) {
                    $choices[$key] = ['label' => self::normalizeLabel($label), 'count' => 0];
                }
                $choices[$key]['count']++;
            }
        }

        $out = [];
        foreach ($choices as $key => $row) {
            if ($row['count'] <= 0) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $row['label'],
                'count' => $row['count'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    /** @return list<string> */
    public static function suggestionLabels(): array
    {
        $merged = [];
        foreach (self::defaultLabels() as $label) {
            $merged[self::labelKey($label)] = $label;
        }

        if (LivreRepository::isAvailable()) {
            foreach ((new LivreRepository())->listKnownCategoryLabels() as $label) {
                $normalized = self::normalizeLabel($label);
                if ($normalized === '') {
                    continue;
                }
                $merged[self::labelKey($normalized)] = $normalized;
            }
        }

        $labels = array_values($merged);
        usort($labels, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return $labels;
    }

    private static function labelKey(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = preg_replace('/\s+/u', ' ', $label) ?? '';

        return $label;
    }
}
