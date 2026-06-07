<?php
/**
 * Catégories de sujets magazines (tests, previews…).
 */

declare(strict_types=1);

namespace Moncine;

final class MagazineSubject
{
    public const TEST = 'test';
    public const PREVIEW = 'preview';
    public const COMPARATIF = 'comparatif';
    public const DOSSIER = 'dossier';

    /** Anciennes catégories fusionnées dans {@see TEST}. */
    private const LEGACY_TEST = ['test_jeu', 'test_voiture', 'test_materiel'];

    /** @return list<string> */
    public static function legacyTestCategories(): array
    {
        return self::LEGACY_TEST;
    }

    /**
     * Valeurs SQL à filtrer pour une catégorie (Test inclut les anciennes catégories).
     *
     * @return list<string>
     */
    public static function categoryFilterValues(string $category): array
    {
        $category = self::normalizeCategory($category);
        if ($category === self::TEST) {
            return array_values(array_unique([self::TEST, ...self::LEGACY_TEST]));
        }

        return [$category];
    }

    /** @return array<string, string> clé => libellé */
    public static function choices(): array
    {
        return [
            self::TEST => 'Test',
            self::PREVIEW => 'Preview / avant-première',
            self::COMPARATIF => 'Comparatif',
            self::DOSSIER => 'Dossier',
        ];
    }

    public static function normalizeCategory(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return self::TEST;
        }

        $legacyTest = self::LEGACY_TEST;
        if (in_array($raw, $legacyTest, true)) {
            return self::TEST;
        }

        $aliases = [
            'jeu' => self::TEST,
            'jeux' => self::TEST,
            'game' => self::TEST,
            'voiture' => self::TEST,
            'auto' => self::TEST,
            'materiel' => self::TEST,
            'matériel' => self::TEST,
            'it' => self::TEST,
            'hardware' => self::TEST,
            'apercu' => self::PREVIEW,
            'aperçu' => self::PREVIEW,
        ];

        if (isset(self::choices()[$raw])) {
            return $raw;
        }

        return $aliases[$raw] ?? self::TEST;
    }

    public static function label(string $category): string
    {
        $category = self::normalizeCategory($category);

        return self::choices()[$category] ?? self::choices()[self::TEST];
    }

    /** Libellé complet affiché (ex. « Gran Turismo 7 (PS5 · 2024) »). */
    public static function displayLabel(string $label, string $detail = '', int $parutionYear = 0): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $parts = [];
        $detailLabel = trim($detail);
        if ($detailLabel !== '') {
            $parts[] = $detailLabel;
        }
        if ($parutionYear > 0) {
            $parts[] = (string) $parutionYear;
        }
        if ($parts === []) {
            return $label;
        }

        return $label . ' (' . implode(' · ', $parts) . ')';
    }

    /** Année de parution extraite du numéro magazine. */
    public static function parutionYearFromIssue(array $issue): int
    {
        $date = trim((string) ($issue['date_parution'] ?? ''));
        if ($date === '') {
            return 0;
        }

        $year = (int) substr($date, 0, 4);

        return $year >= 1900 && $year <= 2100 ? $year : 0;
    }
}
