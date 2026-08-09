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

        return $years;
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

    /**
     * Types d’articles listés pour la recherche « mois + catégorie de magazine ».
     *
     * @return list<string>
     */
    public static function monthBrowseSubjectCategories(): array
    {
        return [
            MagazineSubject::TEST,
            MagazineSubject::PREVIEW,
            MagazineSubject::DOSSIER,
        ];
    }

    /**
     * Liste tests / previews / dossiers parus un mois donné
     * dans les magazines d’une catégorie de série (ex. Jeux vidéo).
     *
     * @return array{
     *   active: bool,
     *   year: int,
     *   month: int,
     *   series_category_key: string,
     *   series_category_label: string,
     *   month_label: string,
     *   total: int,
     *   groups: list<array{category: string, label: string, subjects: list<array<string, mixed>>}>,
     *   year_choices: list<int>,
     *   month_choices: array<int, string>,
     *   series_category_choices: list<array{key: string, label: string}>
     * }
     */
    public function getMonthCategorySubjects(?int $year, ?int $month, ?string $seriesCategoryKey): array
    {
        $yearChoices = $this->availableYears();
        $monthChoices = MagazineSeriesStats::monthChoices();
        $seriesCategoryChoices = $this->seriesCategoryFilterChoices();

        $year = $year !== null && $year > 0 ? MagazineSubject::normalizeParutionYear($year) : 0;
        $month = $month !== null ? (int) $month : 0;
        $seriesCategoryKey = trim((string) $seriesCategoryKey);
        $seriesCategoryLabel = '';
        foreach ($seriesCategoryChoices as $choice) {
            if (($choice['key'] ?? '') === $seriesCategoryKey) {
                $seriesCategoryLabel = (string) ($choice['label'] ?? '');
                break;
            }
        }
        if ($seriesCategoryLabel === '' && $seriesCategoryKey !== '') {
            $seriesCategoryLabel = MagazineSeriesCategory::normalizeLabel($seriesCategoryKey);
            $seriesCategoryKey = MagazineSeriesCategory::filterKey($seriesCategoryLabel);
        }

        $empty = [
            'active' => false,
            'year' => $year,
            'month' => $month,
            'series_category_key' => $seriesCategoryKey,
            'series_category_label' => $seriesCategoryLabel,
            'month_label' => $monthChoices[$month] ?? '',
            'total' => 0,
            'groups' => [],
            'year_choices' => $yearChoices,
            'month_choices' => $monthChoices,
            'series_category_choices' => $seriesCategoryChoices,
        ];

        if (
            !self::isAvailable()
            || $year < 1900
            || $year > 2100
            || $month < 1
            || $month > 12
            || $seriesCategoryKey === ''
        ) {
            return $empty;
        }

        $subjects = $this->listSubjectsBySeriesCategoryAndMonth($seriesCategoryKey, $year, $month);
        $groups = $this->groupSubjectsByArticleCategory($subjects);

        return [
            'active' => true,
            'year' => $year,
            'month' => $month,
            'series_category_key' => $seriesCategoryKey,
            'series_category_label' => $seriesCategoryLabel !== ''
                ? $seriesCategoryLabel
                : $seriesCategoryKey,
            'month_label' => $monthChoices[$month] ?? (string) $month,
            'total' => count($subjects),
            'groups' => $groups,
            'year_choices' => $yearChoices,
            'month_choices' => $monthChoices,
            'series_category_choices' => $seriesCategoryChoices,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function seriesCategoryFilterChoices(): array
    {
        $choices = [];
        foreach (MagazineSeriesCategory::suggestionLabels() as $label) {
            $key = MagazineSeriesCategory::filterKey($label);
            if ($key === '') {
                continue;
            }
            $choices[] = [
                'key' => $key,
                'label' => MagazineSeriesCategory::normalizeLabel($label),
            ];
        }

        return $choices;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSubjectsBySeriesCategoryAndMonth(
        string $seriesCategoryKey,
        int $year,
        int $month
    ): array {
        if (
            !self::isAvailable()
            || !SeriesRepository::categoriesColumnExists()
            || $year < 1900
            || $year > 2100
            || $month < 1
            || $month > 12
        ) {
            return [];
        }

        $wantedKey = MagazineSeriesCategory::filterKey(
            MagazineSeriesCategory::normalizeLabel($seriesCategoryKey)
        );
        if ($wantedKey === '') {
            $wantedKey = MagazineSeriesCategory::filterKey($seriesCategoryKey);
        }
        if ($wantedKey === '') {
            return [];
        }

        $articleCategories = self::monthBrowseSubjectCategories();
        $filterValues = [];
        foreach ($articleCategories as $articleCategory) {
            foreach (MagazineSubject::categoryFilterValues($articleCategory) as $value) {
                $filterValues[$value] = $value;
            }
        }
        $filterValues = array_values($filterValues);
        if ($filterValues === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($filterValues), '?'));
        $pageSelect = MagazineSubjectRepository::hasPageColumn() ? ', oms.page' : ', 0 AS page';
        $scoreSelect = MagazineSubjectRepository::hasScoreColumn() ? ', oms.score' : ', NULL AS score';
        $catalogSelect = MagazineGameLink::catalogColumnExists()
            ? ', ms.catalog_oeuvre_id'
            : ', 0 AS catalog_oeuvre_id';
        $ratingSelect = SeriesRepository::ratingScaleColumnExists()
            ? ', s.rating_scale AS series_rating_scale'
            : ', \'\' AS series_rating_scale';
        $starMapSelect = SeriesRepository::starPercentMapColumnExists()
            ? ', s.star_percent_map AS series_star_percent_map'
            : ', NULL AS series_star_percent_map';
        $categoriesSelect = ', s.categories AS series_categories';

        $sql = 'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at'
            . $catalogSelect
            . $pageSelect
            . $scoreSelect
            . ', om.oeuvre_id AS issue_oeuvre_id, om.numero, om.numero_ordre, om.date_parution,'
            . ' om.est_hors_serie, om.stored_object_id,'
            . ' s.id AS series_id, s.titre AS series_titre'
            . $categoriesSelect
            . $ratingSelect
            . $starMapSelect
            . ' FROM oeuvre_magazine om'
            . ' INNER JOIN series s ON s.id = om.series_id'
            . ' INNER JOIN oeuvre_magazine_subject oms ON oms.oeuvre_id = om.oeuvre_id'
            . ' INNER JOIN magazine_subject ms ON ms.id = oms.subject_id'
            . ' WHERE ms.category IN (' . $placeholders . ')'
            . ' ORDER BY ms.category ASC,'
            . ' s.titre COLLATE FRENCH_NOCASE ASC,'
            . ' om.numero_ordre ASC,'
            . ' oms.page ASC,'
            . ' ms.label COLLATE FRENCH_NOCASE ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($filterValues);

        $subjectRepo = new MagazineSubjectRepository();
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        $gameLink = MagazineGameLink::isAvailable() ? new MagazineGameLink() : null;

        /** @var array<int, list<array{from_numero_ordre: float, to_numero_ordre: float|null, rating_scale: string}>> $periodsBySeries */
        $periodsBySeries = [];
        /** @var array<int, array<int, float>|null> $starMapBySeries */
        $starMapBySeries = [];
        $out = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $seriesKeys = MagazineSeriesCategory::filterKeysForSeries([
                'categories' => (string) ($row['series_categories'] ?? ''),
            ]);
            if (!in_array($wantedKey, $seriesKeys, true)) {
                continue;
            }

            $dateParution = (string) ($row['date_parution'] ?? '');
            $issueYear = MagazineSeriesStats::extractYear($dateParution);
            $issueMonth = MagazineSeriesStats::extractMonth($dateParution);
            if ($issueYear !== $year || $issueMonth !== $month) {
                continue;
            }

            $seriesId = (int) ($row['series_id'] ?? 0);
            if ($seriesId > 0 && !isset($periodsBySeries[$seriesId])) {
                $periodsBySeries[$seriesId] = MagazineRatingPeriod::listForSeries($seriesId);
            }
            if ($seriesId > 0 && !array_key_exists($seriesId, $starMapBySeries)) {
                $starMapBySeries[$seriesId] = MagazineRatingScale::parseStarPercentMap(
                    $row['series_star_percent_map'] ?? null
                );
            }

            $hydrated = $subjectRepo->hydrateSubjectRowPublic($row);
            $seriesTitre = trim((string) ($row['series_titre'] ?? ''));
            $numero = trim((string) ($row['numero'] ?? ''));
            $isHs = !empty($row['est_hors_serie']);
            $numeroPart = $numero !== ''
                ? ($isHs ? 'HS ' . $numero : 'n°' . $numero)
                : '—';
            $issueLabel = $seriesTitre !== ''
                ? $seriesTitre . ' · ' . $numeroPart
                : $numeroPart;

            $issueOeuvreId = (int) ($row['issue_oeuvre_id'] ?? 0);
            $storedObjectId = (int) ($row['stored_object_id'] ?? 0);
            $numeroOrdre = (float) ($row['numero_ordre'] ?? 0);
            $defaultScale = MagazineRatingScale::normalize($row['series_rating_scale'] ?? null);

            $hydrated['series_id'] = $seriesId;
            $hydrated['series_titre'] = $seriesTitre;
            $hydrated['issue_oeuvre_id'] = $issueOeuvreId;
            $hydrated['issue_label'] = $issueLabel;
            $hydrated['issue_nav_url'] = $issueOeuvreId > 0
                ? View::oeuvreMagazineNavUrl($issueOeuvreId)
                : '';
            $hydrated['stored_object_id'] = $storedObjectId;
            $hydrated['rating_scale'] = MagazineRatingPeriod::resolve(
                $defaultScale,
                $periodsBySeries[$seriesId] ?? [],
                $numeroOrdre
            );

            // Équivalent /100 pour comparer des notes d’échelles différentes (8/10 ≈ 80 %).
            $rawScore = array_key_exists('score', $hydrated) && $hydrated['score'] !== null
                ? (float) $hydrated['score']
                : null;
            $hydrated['score_percent'] = MagazineRatingScale::toPercent(
                $rawScore,
                $hydrated['rating_scale'] ?? null,
                $starMapBySeries[$seriesId] ?? null
            );
            $hydrated['star_percent_map'] = $starMapBySeries[$seriesId] ?? null;

            if ($gameLink !== null) {
                $hydrated = $gameLink->enrichSubjectRow($hydrated, $userId, $foyerId);
            }

            $out[] = $hydrated;
        }

        return $out;
    }

    /**
     * Regroupe les sujets dans l’ordre Test → Preview → Dossier.
     * Chaque groupe est trié alphabétiquement par défaut.
     *
     * @param list<array<string, mixed>> $subjects
     * @return list<array{category: string, label: string, subjects: list<array<string, mixed>>}>
     */
    private function groupSubjectsByArticleCategory(array $subjects): array
    {
        $byCategory = [];
        foreach ($subjects as $subject) {
            $key = MagazineSubject::normalizeCategory((string) ($subject['category'] ?? ''));
            if (!isset($byCategory[$key])) {
                $byCategory[$key] = [];
            }
            $byCategory[$key][] = $subject;
        }

        $groups = [];
        foreach (self::monthBrowseSubjectCategories() as $category) {
            if (!isset($byCategory[$category])) {
                continue;
            }
            $groups[] = [
                'category' => $category,
                'label' => MagazineSubject::label($category),
                'subjects' => self::sortSubjectsAlphabetically($byCategory[$category]),
            ];
            unset($byCategory[$category]);
        }

        // Catégories inattendues (ex. anciennes clés déjà normalisées ailleurs)
        foreach ($byCategory as $extraKey => $extraSubjects) {
            $groups[] = [
                'category' => (string) $extraKey,
                'label' => MagazineSubject::label((string) $extraKey),
                'subjects' => self::sortSubjectsAlphabetically($extraSubjects),
            ];
        }

        return $groups;
    }

    /**
     * Tri alphabétique français sur le titre du sujet.
     *
     * @param list<array<string, mixed>> $subjects
     * @return list<array<string, mixed>>
     */
    public static function sortSubjectsAlphabetically(array $subjects): array
    {
        usort(
            $subjects,
            static function (array $left, array $right): int {
                $leftLabel = trim((string) ($left['label'] ?? $left['display_label'] ?? ''));
                $rightLabel = trim((string) ($right['label'] ?? $right['display_label'] ?? ''));
                if (class_exists(\Collator::class)) {
                    $collator = new \Collator('fr_FR');
                    $compared = $collator->compare($leftLabel, $rightLabel);
                    if (is_int($compared) && $compared !== 0) {
                        return $compared;
                    }
                }

                return strcasecmp($leftLabel, $rightLabel);
            }
        );

        return $subjects;
    }

    /**
     * Tri par note décroissante en équivalent /100 (échelles différentes comparables).
     * Sans note → en fin de liste ; à note égale → alphabétique.
     *
     * @param list<array<string, mixed>> $subjects
     * @return list<array<string, mixed>>
     */
    public static function sortSubjectsByScorePercentDesc(array $subjects): array
    {
        usort(
            $subjects,
            static function (array $left, array $right): int {
                $leftPercent = self::subjectScorePercent($left);
                $rightPercent = self::subjectScorePercent($right);
                $leftHas = $leftPercent !== null;
                $rightHas = $rightPercent !== null;
                if ($leftHas !== $rightHas) {
                    return $leftHas ? -1 : 1;
                }
                if ($leftHas && $rightHas && $leftPercent !== $rightPercent) {
                    // Décroissant : meilleure note d’abord
                    return $rightPercent <=> $leftPercent;
                }

                $leftLabel = trim((string) ($left['label'] ?? $left['display_label'] ?? ''));
                $rightLabel = trim((string) ($right['label'] ?? $right['display_label'] ?? ''));

                return strcasecmp($leftLabel, $rightLabel);
            }
        );

        return $subjects;
    }

    /** @param array<string, mixed> $subject */
    public static function subjectScorePercent(array $subject): ?float
    {
        if (array_key_exists('score_percent', $subject) && $subject['score_percent'] !== null) {
            return (float) $subject['score_percent'];
        }

        if (!array_key_exists('score', $subject) || $subject['score'] === null) {
            return null;
        }

        return MagazineRatingScale::toPercent(
            (float) $subject['score'],
            isset($subject['rating_scale']) ? (string) $subject['rating_scale'] : null,
            isset($subject['star_percent_map'])
                ? MagazineRatingScale::parseStarPercentMap($subject['star_percent_map'])
                : null
        );
    }
}
