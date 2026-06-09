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
    public const INTERVIEW = 'interview';

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
            self::INTERVIEW => 'Interview',
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
            'entretien' => self::INTERVIEW,
            'entretiens' => self::INTERVIEW,
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

    /** Sujets pouvant être reliés à une fiche jeu du catalogue (pont M4/M5). */
    public static function supportsCatalogGameLink(string $category): bool
    {
        $category = self::normalizeCategory($category);

        return in_array($category, [self::TEST, self::PREVIEW, self::INTERVIEW], true);
    }

    /**
     * Clé de comparaison pour regrouper des libellés proches
     * (ex. « After Life » et « Afterlife » → « afterlife »).
     */
    public static function normalizeLabelKey(string $label): string
    {
        $label = mb_strtolower(trim($label));
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s+/u', '', $label) ?? '';
        $label = preg_replace('/[^\p{L}\p{N}]/u', '', $label) ?? '';

        return $label;
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

        return self::normalizeParutionYear($year);
    }

    /** Année valide pour un sujet (1900–2100), sinon 0. */
    public static function normalizeParutionYear(int $year): int
    {
        return $year >= 1900 && $year <= 2100 ? $year : 0;
    }

    /**
     * Année proposée par défaut à l’ajout d’un sujet (date du numéro, sinon année courante).
     *
     * @param array<string, mixed> $issue
     */
    public static function defaultSubjectYearFromIssue(array $issue): int
    {
        $fromIssue = self::parutionYearFromIssue($issue);

        return $fromIssue > 0 ? $fromIssue : (int) date('Y');
    }

    /**
     * Années proposées dans le menu déroulant (centrées sur l’année par défaut).
     *
     * @return list<int>
     */
    public static function subjectYearChoices(int $defaultYear = 0): array
    {
        $defaultYear = self::normalizeParutionYear($defaultYear);
        if ($defaultYear <= 0) {
            $defaultYear = (int) date('Y');
        }

        $currentYear = (int) date('Y');
        $min = max(1900, $defaultYear - 15);
        $max = min(2100, max($defaultYear + 2, $currentYear + 1));

        $years = [];
        for ($year = $max; $year >= $min; $year--) {
            $years[] = $year;
        }

        return $years;
    }
}
