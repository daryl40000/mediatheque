<?php
/**
 * Statistiques d’évolution d’une série magazine (pages, sujets par catégorie).
 *
 * Les chiffres portent sur tout le catalogue de la série (pas seulement
 * les numéros possédés) : on analyse le magazine lui-même, au fur et à mesure
 * que pages et sujets sont renseignés.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineSeriesStats
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return MagazineRepository::isAvailable();
    }

    /**
     * Tableau de bord complet pour une série.
     *
     * @return array{
     *   summary: array<string, mixed>,
     *   pages_by_year: list<array<string, mixed>>,
     *   subjects_avg_by_year: list<array<string, mixed>>,
     *   subjects_by_issue: list<array<string, mixed>>,
     *   subject_category_keys: list<string>
     * }
     */
    public function getDashboard(int $seriesId): array
    {
        if (!self::isAvailable() || $seriesId <= 0) {
            return $this->emptyDashboard();
        }

        $issues = $this->fetchIssues($seriesId);
        $subjectRows = MagazineSubjectRepository::isAvailable()
            ? $this->fetchSubjectLinks($seriesId)
            : [];

        return [
            'summary' => $this->buildSummary($issues, $subjectRows),
            'pages_by_year' => $this->buildPagesByYear($issues),
            'subjects_avg_by_year' => $this->buildSubjectsAvgByYear($issues, $subjectRows),
            'subjects_by_issue' => $this->buildSubjectsByIssue($issues, $subjectRows),
            'subject_category_keys' => array_keys(MagazineSubject::choices()),
        ];
    }

    /**
     * @return array{
     *   summary: array<string, mixed>,
     *   pages_by_year: list<array<string, mixed>>,
     *   subjects_avg_by_year: list<array<string, mixed>>,
     *   subjects_by_issue: list<array<string, mixed>>,
     *   subject_category_keys: list<string>
     * }
     */
    private function emptyDashboard(): array
    {
        return [
            'summary' => [
                'issue_count' => 0,
                'issues_with_pages' => 0,
                'issues_without_pages' => 0,
                'pages_total' => 0,
                'pages_avg' => null,
                'pages_min' => null,
                'pages_max' => null,
                'subject_link_count' => 0,
                'issues_with_subjects' => 0,
                'issues_without_subjects' => 0,
            ],
            'pages_by_year' => [],
            'subjects_avg_by_year' => [],
            'subjects_by_issue' => [],
            'subject_category_keys' => array_keys(MagazineSubject::choices()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchIssues(int $seriesId): array
    {
        $stmt = $this->db->prepare(
            'SELECT om.oeuvre_id, om.numero, om.numero_ordre, om.date_parution, om.pages, om.est_hors_serie
             FROM oeuvre_magazine om
             WHERE om.series_id = :series_id
             ORDER BY
                om.numero_ordre ASC,
                CASE WHEN om.date_parution IS NULL OR TRIM(om.date_parution) = \'\' THEN 1 ELSE 0 END,
                om.date_parution ASC'
        );
        $stmt->execute(['series_id' => $seriesId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pages = isset($row['pages']) && $row['pages'] !== null && $row['pages'] !== ''
                ? (int) $row['pages']
                : 0;
            $rows[] = [
                'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
                'numero' => (string) ($row['numero'] ?? ''),
                'numero_ordre' => (float) ($row['numero_ordre'] ?? 0),
                'date_parution' => (string) ($row['date_parution'] ?? ''),
                'pages' => $pages > 0 ? $pages : null,
                'est_hors_serie' => !empty($row['est_hors_serie']),
                'year' => self::extractYear((string) ($row['date_parution'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{oeuvre_id: int, category: string, year: ?int}>
     */
    private function fetchSubjectLinks(int $seriesId): array
    {
        $stmt = $this->db->prepare(
            'SELECT om.oeuvre_id, om.date_parution, ms.category
             FROM oeuvre_magazine om
             INNER JOIN oeuvre_magazine_subject oms ON oms.oeuvre_id = om.oeuvre_id
             INNER JOIN magazine_subject ms ON ms.id = oms.subject_id
             WHERE om.series_id = :series_id'
        );
        $stmt->execute(['series_id' => $seriesId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'oeuvre_id' => (int) ($row['oeuvre_id'] ?? 0),
                'category' => MagazineSubject::normalizeCategory((string) ($row['category'] ?? '')),
                'year' => self::extractYear((string) ($row['date_parution'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $issues
     * @param list<array{oeuvre_id: int, category: string, year: ?int}> $subjectRows
     * @return array<string, mixed>
     */
    private function buildSummary(array $issues, array $subjectRows): array
    {
        $issueCount = count($issues);
        $pagesValues = [];
        foreach ($issues as $issue) {
            if (isset($issue['pages']) && is_int($issue['pages']) && $issue['pages'] > 0) {
                $pagesValues[] = $issue['pages'];
            }
        }

        $issuesWithPages = count($pagesValues);
        $pagesTotal = array_sum($pagesValues);
        $issuesWithSubjects = count(array_unique(array_map(
            static fn (array $row): int => $row['oeuvre_id'],
            $subjectRows
        )));

        return [
            'issue_count' => $issueCount,
            'issues_with_pages' => $issuesWithPages,
            'issues_without_pages' => max(0, $issueCount - $issuesWithPages),
            'pages_total' => $pagesTotal,
            'pages_avg' => $issuesWithPages > 0
                ? round($pagesTotal / $issuesWithPages, 1)
                : null,
            'pages_min' => $pagesValues !== [] ? min($pagesValues) : null,
            'pages_max' => $pagesValues !== [] ? max($pagesValues) : null,
            'subject_link_count' => count($subjectRows),
            'issues_with_subjects' => $issuesWithSubjects,
            'issues_without_subjects' => max(0, $issueCount - $issuesWithSubjects),
        ];
    }

    /**
     * Pages moyennes par année de parution.
     *
     * @param list<array<string, mixed>> $issues
     * @return list<array{year: int, avg_pages: float, issue_count: int, pages_sum: int}>
     */
    private function buildPagesByYear(array $issues): array
    {
        /** @var array<int, array{sum: int, count: int}> $byYear */
        $byYear = [];
        foreach ($issues as $issue) {
            $year = $issue['year'] ?? null;
            $pages = $issue['pages'] ?? null;
            if (!is_int($year) || !is_int($pages) || $pages <= 0) {
                continue;
            }
            if (!isset($byYear[$year])) {
                $byYear[$year] = ['sum' => 0, 'count' => 0];
            }
            $byYear[$year]['sum'] += $pages;
            $byYear[$year]['count']++;
        }

        ksort($byYear, SORT_NUMERIC);

        $rows = [];
        foreach ($byYear as $year => $data) {
            $rows[] = [
                'year' => $year,
                'avg_pages' => round($data['sum'] / $data['count'], 1),
                'issue_count' => $data['count'],
                'pages_sum' => $data['sum'],
            ];
        }

        return $rows;
    }

    /**
     * Moyenne de sujets par numéro, pour chaque année et chaque catégorie.
     *
     * Formule : (sujets de la catégorie dans l’année)
     *           ÷ (nombre de numéros de l’année qui ont au moins un sujet).
     * Les numéros sans aucun sujet sont ignorés.
     *
     * @param list<array<string, mixed>> $issues
     * @param list<array{oeuvre_id: int, category: string, year: ?int}> $subjectRows
     * @return list<array{
     *   year: int,
     *   issue_count: int,
     *   total_avg: float,
     *   categories: array<string, float>,
     *   counts: array<string, int>
     * }>
     */
    private function buildSubjectsAvgByYear(array $issues, array $subjectRows): array
    {
        $categoryKeys = array_keys(MagazineSubject::choices());

        // Année de chaque numéro (pour rattacher les sujets).
        /** @var array<int, int> $yearByOeuvre */
        $yearByOeuvre = [];
        foreach ($issues as $issue) {
            $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
            $year = $issue['year'] ?? null;
            if ($oeuvreId > 0 && is_int($year)) {
                $yearByOeuvre[$oeuvreId] = $year;
            }
        }

        /** @var array<int, array<string, int>> $countsByYear */
        $countsByYear = [];
        /** @var array<int, array<int, true>> $issuesWithSubjectsByYear */
        $issuesWithSubjectsByYear = [];

        foreach ($subjectRows as $row) {
            $oeuvreId = $row['oeuvre_id'];
            $year = $yearByOeuvre[$oeuvreId] ?? $row['year'];
            if (!is_int($year) || $oeuvreId <= 0) {
                continue;
            }
            if (!isset($countsByYear[$year])) {
                $countsByYear[$year] = array_fill_keys($categoryKeys, 0);
                $issuesWithSubjectsByYear[$year] = [];
            }
            $category = $row['category'];
            if (!isset($countsByYear[$year][$category])) {
                $countsByYear[$year][$category] = 0;
            }
            $countsByYear[$year][$category]++;
            $issuesWithSubjectsByYear[$year][$oeuvreId] = true;
        }

        $years = array_keys($countsByYear);
        sort($years, SORT_NUMERIC);

        $rows = [];
        foreach ($years as $year) {
            $issueCount = count($issuesWithSubjectsByYear[$year] ?? []);
            if ($issueCount <= 0) {
                continue;
            }
            $counts = $countsByYear[$year];
            $averages = [];
            foreach ($categoryKeys as $key) {
                $averages[$key] = round(($counts[$key] ?? 0) / $issueCount, 2);
            }
            $rows[] = [
                'year' => $year,
                'issue_count' => $issueCount,
                'total_avg' => round(array_sum($averages), 2),
                'categories' => $averages,
                'counts' => $counts,
            ];
        }

        return $rows;
    }

    /**
     * Nombre de sujets par numéro (évolution numéro par numéro).
     *
     * @param list<array<string, mixed>> $issues
     * @param list<array{oeuvre_id: int, category: string, year: ?int}> $subjectRows
     * @return list<array{
     *   oeuvre_id: int,
     *   numero: string,
     *   numero_label: string,
     *   numero_ordre: float,
     *   year: ?int,
     *   total: int,
     *   categories: array<string, int>
     * }>
     */
    private function buildSubjectsByIssue(array $issues, array $subjectRows): array
    {
        $categoryKeys = array_keys(MagazineSubject::choices());

        /** @var array<int, array<string, int>> $countsByIssue */
        $countsByIssue = [];
        foreach ($subjectRows as $row) {
            $oeuvreId = $row['oeuvre_id'];
            if ($oeuvreId <= 0) {
                continue;
            }
            if (!isset($countsByIssue[$oeuvreId])) {
                $countsByIssue[$oeuvreId] = array_fill_keys($categoryKeys, 0);
            }
            $category = $row['category'];
            if (!isset($countsByIssue[$oeuvreId][$category])) {
                $countsByIssue[$oeuvreId][$category] = 0;
            }
            $countsByIssue[$oeuvreId][$category]++;
        }

        // Uniquement les numéros qui ont au moins un sujet (évite une longue file de zéros
        // tant que le remplissage est progressif).
        $rows = [];
        foreach ($issues as $issue) {
            $oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0 || !isset($countsByIssue[$oeuvreId])) {
                continue;
            }
            $categories = $countsByIssue[$oeuvreId];
            $numero = (string) ($issue['numero'] ?? '');
            $isHs = !empty($issue['est_hors_serie']);
            $numeroLabel = $numero !== ''
                ? ($isHs ? 'HS ' . $numero : 'n°' . $numero)
                : '—';

            $rows[] = [
                'oeuvre_id' => $oeuvreId,
                'numero' => $numero,
                'numero_label' => $numeroLabel,
                'numero_ordre' => (float) ($issue['numero_ordre'] ?? 0),
                'year' => is_int($issue['year'] ?? null) ? $issue['year'] : null,
                'total' => array_sum($categories),
                'categories' => $categories,
            ];
        }

        return $rows;
    }

    /** Extrait l’année d’une date ISO ou d’un libellé français (« mars 2018 »). */
    public static function extractYear(?string $dateParution): ?int
    {
        $dateParution = trim((string) $dateParution);
        if ($dateParution === '') {
            return null;
        }

        if (preg_match('/^(\d{4})\b/', $dateParution, $matches) === 1) {
            $year = (int) $matches[1];

            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        $normalized = PublicationType::parseParutionDateLabel($dateParution);
        if ($normalized !== null && preg_match('/^(\d{4})\b/', $normalized, $matches) === 1) {
            $year = (int) $matches[1];

            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        return null;
    }
}
