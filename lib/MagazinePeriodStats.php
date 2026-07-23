<?php
/**
 * Statistiques magazines sur une période (années de parution des numéros).
 *
 * Classements : jeux les plus / moins évoqués, séries avec le plus de tests / previews.
 * Une mention = un lien sujet ↔ numéro (pas une fiche sujet unique).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazinePeriodStats
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return MagazineRepository::isAvailable()
            && MagazineSubjectRepository::isAvailable()
            && MagazineGameLink::catalogColumnExists();
    }

    /**
     * @return array{
     *   active: bool,
     *   from_year: int,
     *   to_year: int,
     *   year_choices: list<int>,
     *   games_most: list<array<string, mixed>>,
     *   games_least: list<array<string, mixed>>,
     *   series_most_tests: list<array<string, mixed>>,
     *   series_most_previews: list<array<string, mixed>>
     * }
     */
    public function getPeriodDashboard(?int $fromYear, ?int $toYear): array
    {
        $yearChoices = $this->availableYears();
        $period = $this->normalizePeriod($fromYear, $toYear);

        if ($period === null || !self::isAvailable()) {
            return [
                'active' => false,
                'from_year' => $period['from'] ?? (int) ($fromYear ?? 0),
                'to_year' => $period['to'] ?? (int) ($toYear ?? 0),
                'year_choices' => $yearChoices,
                'games_most' => [],
                'games_least' => [],
                'series_most_tests' => [],
                'series_most_previews' => [],
            ];
        }

        $issueOeuvreIds = $this->issueOeuvreIdsInPeriod($period['from'], $period['to']);

        return [
            'active' => true,
            'from_year' => $period['from'],
            'to_year' => $period['to'],
            'year_choices' => $yearChoices,
            'games_most' => $this->rankGamesByMentionCount($issueOeuvreIds, 10, true),
            'games_least' => $this->rankGamesByMentionCount($issueOeuvreIds, 10, false),
            'series_most_tests' => $this->rankSeriesByCategory(
                $issueOeuvreIds,
                MagazineSubject::categoryFilterValues(MagazineSubject::TEST),
                5
            ),
            'series_most_previews' => $this->rankSeriesByCategory(
                $issueOeuvreIds,
                MagazineSubject::categoryFilterValues(MagazineSubject::PREVIEW),
                5
            ),
        ];
    }

    /**
     * @return array{from: int, to: int}|null
     */
    public function normalizePeriod(?int $fromYear, ?int $toYear): ?array
    {
        $fromYear = $fromYear !== null && $fromYear > 0 ? MagazineSubject::normalizeParutionYear($fromYear) : 0;
        $toYear = $toYear !== null && $toYear > 0 ? MagazineSubject::normalizeParutionYear($toYear) : 0;

        if ($fromYear <= 0 && $toYear <= 0) {
            return null;
        }

        if ($fromYear <= 0) {
            $fromYear = $toYear;
        }
        if ($toYear <= 0) {
            $toYear = $fromYear;
        }
        if ($fromYear > $toYear) {
            [$fromYear, $toYear] = [$toYear, $fromYear];
        }

        return ['from' => $fromYear, 'to' => $toYear];
    }

    /**
     * Années proposées dans le sélecteur (d’après les dates de parution renseignées).
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        if (!MagazineRepository::isAvailable()) {
            return $this->fallbackYearChoices();
        }

        $stmt = $this->db->query(
            "SELECT DISTINCT TRIM(date_parution) AS date_parution
             FROM oeuvre_magazine
             WHERE TRIM(COALESCE(date_parution, '')) != ''"
        );
        $years = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $year = MagazineSeriesStats::extractYear((string) ($row['date_parution'] ?? ''));
            if ($year !== null) {
                $years[$year] = $year;
            }
        }

        if ($years === []) {
            return $this->fallbackYearChoices();
        }

        rsort($years, SORT_NUMERIC);

        return array_values($years);
    }

    /**
     * Numéros dont l’année de parution est dans la période
     * (ISO « 1996-03-01 » ou libellé FR « mars 1996 »).
     *
     * @return list<int>
     */
    public function issueOeuvreIdsInPeriod(int $fromYear, int $toYear): array
    {
        if (!MagazineRepository::isAvailable() || $fromYear <= 0 || $toYear <= 0) {
            return [];
        }

        $stmt = $this->db->query(
            'SELECT oeuvre_id, date_parution
             FROM oeuvre_magazine
             WHERE TRIM(COALESCE(date_parution, \'\')) != \'\''
        );

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $year = MagazineSeriesStats::extractYear((string) ($row['date_parution'] ?? ''));
            if ($year === null || $year < $fromYear || $year > $toYear) {
                continue;
            }
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId > 0) {
                $ids[$oeuvreId] = $oeuvreId;
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<int>
     */
    private function fallbackYearChoices(): array
    {
        $current = (int) date('Y');
        $years = [];
        for ($year = $current; $year >= $current - 40; $year--) {
            $years[] = $year;
        }

        return $years;
    }

    /**
     * Jeux liés par des sujets (hors « jeux offerts ») : 1 lien sujet↔numéro = 1 mention.
     *
     * @param list<int> $issueOeuvreIds
     * @return list<array{oeuvre_id: int, titre: string, subject_count: int, url: string}>
     */
    private function rankGamesByMentionCount(array $issueOeuvreIds, int $limit, bool $most): array
    {
        $limit = max(1, min(50, $limit));
        if ($issueOeuvreIds === []) {
            return [];
        }

        $order = $most ? 'DESC' : 'ASC';
        $placeholders = implode(',', array_fill(0, count($issueOeuvreIds), '?'));
        $params = array_merge(
            [MediaDomain::JEU, MagazineSubject::JEUX_OFFERTS],
            $issueOeuvreIds
        );

        $stmt = $this->db->prepare(
            'SELECT o.id AS oeuvre_id,
                    o.titre,
                    o.titre_original,
                    COUNT(*) AS subject_count
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvres o
                ON o.id = ms.catalog_oeuvre_id
               AND o.media_domain = ?
             WHERE ms.catalog_oeuvre_id IS NOT NULL
               AND ms.catalog_oeuvre_id > 0
               AND ms.category != ?
               AND oms.oeuvre_id IN (' . $placeholders . ')
             GROUP BY o.id
             HAVING subject_count > 0
             ORDER BY subject_count ' . $order . ', o.titre COLLATE FRENCH_NOCASE ASC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($oeuvreId <= 0) {
                continue;
            }
            $rows[] = [
                'oeuvre_id' => $oeuvreId,
                'titre' => GameTitle::displayTitle($row),
                'subject_count' => (int) ($row['subject_count'] ?? 0),
                'url' => View::gameMagazinesUrl($oeuvreId),
            ];
        }

        return $rows;
    }

    /**
     * Séries (magazines) avec le plus de mentions d’une catégorie sur la période.
     *
     * @param list<int> $issueOeuvreIds
     * @param list<string> $categories
     * @return list<array{series_id: int, titre: string, subject_count: int, url: string}>
     */
    private function rankSeriesByCategory(array $issueOeuvreIds, array $categories, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $categories = array_values(array_filter(array_map('strval', $categories)));
        if ($issueOeuvreIds === [] || $categories === []) {
            return [];
        }

        $catPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        $issuePlaceholders = implode(',', array_fill(0, count($issueOeuvreIds), '?'));
        $params = array_merge(
            [MediaDomain::MAGAZINE, MediaDomain::MAGAZINE],
            $categories,
            $issueOeuvreIds
        );

        $stmt = $this->db->prepare(
            'SELECT s.id AS series_id,
                    s.titre,
                    COUNT(*) AS subject_count
             FROM magazine_subject ms
             INNER JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN oeuvres o_issue
                ON o_issue.id = om.oeuvre_id
               AND o_issue.media_domain = ?
             INNER JOIN series s ON s.id = om.series_id AND s.media_domain = ?
             WHERE ms.category IN (' . $catPlaceholders . ')
               AND oms.oeuvre_id IN (' . $issuePlaceholders . ')
             GROUP BY s.id
             HAVING subject_count > 0
             ORDER BY subject_count DESC, s.titre COLLATE FRENCH_NOCASE ASC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $seriesId = (int) ($row['series_id'] ?? 0);
            if ($seriesId <= 0) {
                continue;
            }
            $rows[] = [
                'series_id' => $seriesId,
                'titre' => (string) ($row['titre'] ?? ''),
                'subject_count' => (int) ($row['subject_count'] ?? 0),
                'url' => View::magazineSeriesUrl($seriesId),
            ];
        }

        return $rows;
    }
}
