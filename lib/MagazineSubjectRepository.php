<?php
/**
 * Catalogue de sujets magazines et liens numéro ↔ sujet.
 */

declare(strict_types=1);

namespace Moncine;

use Moncine\Repository\SqlNamedParamsTrait;
use PDO;

final class MagazineSubjectRepository
{
    use SqlNamedParamsTrait;

    public const ISSUES_PER_PAGE = 48;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        return MagazineRepository::isAvailable() && self::tableExists();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_subject' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** Colonne page sur oeuvre_magazine_subject (migration 071). */
    public static function hasPageColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        if (!self::tableExists()) {
            return $cache = false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_magazine_subject)');
        if ($stmt === false) {
            return $cache = false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if (($col['name'] ?? '') === 'page') {
                return $cache = true;
            }
        }

        return $cache = false;
    }

    /** Colonne score sur oeuvre_magazine_subject (migration 073). */
    public static function hasScoreColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        if (!self::tableExists()) {
            return $cache = false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(oeuvre_magazine_subject)');
        if ($stmt === false) {
            return $cache = false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if (($col['name'] ?? '') === 'score') {
                return $cache = true;
            }
        }

        return $cache = false;
    }

    /** Colonne score sur magazine_supplement_subject (migration 073). */
    public static function hasSupplementScoreColumn(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        if (!self::hasSupplementSubjectTable()) {
            return $cache = false;
        }

        $stmt = Database::getInstance()->query('PRAGMA table_info(magazine_supplement_subject)');
        if ($stmt === false) {
            return $cache = false;
        }

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
            if (($col['name'] ?? '') === 'score') {
                return $cache = true;
            }
        }

        return $cache = false;
    }

    /** Normalise un numéro de page PDF (0 = non renseigné). */
    public static function normalizePage(int|string|null $page): int
    {
        $page = (int) $page;
        if ($page < 0) {
            return 0;
        }
        if ($page > 9999) {
            return 9999;
        }

        return $page;
    }

    /** Table magazine_supplement_subject (migration 072). */
    public static function hasSupplementSubjectTable(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'magazine_supplement_subject' LIMIT 1"
        );

        return $cache = ($stmt !== false && $stmt->fetchColumn() !== false);
    }

    /** Colonnes magazine_subject (inclut catalog_oeuvre_id si migration 039 appliquée). */
    private static function selectSubjectColumns(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $cols = $prefix . 'id, ' . $prefix . 'category, ' . $prefix . 'label, ' . $prefix . 'detail, '
            . $prefix . 'parution_year, ' . $prefix . 'created_at';
        if (MagazineGameLink::catalogColumnExists()) {
            $cols .= ', ' . $prefix . 'catalog_oeuvre_id';
        }

        return $cols;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $subjectId): ?array
    {
        if (!self::tableExists() || $subjectId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectSubjectColumns() . ' FROM magazine_subject WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$subjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateSubjectRow($row) : null;
    }

    /**
     * Recherche dans le catalogue de sujets (autocomplétion).
     *
     * @return list<array<string, mixed>>
     */
    public function searchCatalog(string $query, ?string $category = null, int $limit = 20): array
    {
        if (!self::tableExists()) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $prefetchLimit = min(max($limit * 8, 80), 250);
        $params = [];
        $where = ['1=1'];

        if ($category !== null && trim($category) !== '') {
            $filterCategories = MagazineSubject::categoryFilterValues($category);
            if (count($filterCategories) === 1) {
                $where[] = 'ms.category = :category';
                $params['category'] = $filterCategories[0];
            } else {
                $parts = [];
                foreach ($filterCategories as $index => $filterCategory) {
                    $key = 'category_' . $index;
                    $parts[] = ':' . $key;
                    $params[$key] = $filterCategory;
                }
                $where[] = 'ms.category IN (' . implode(', ', $parts) . ')';
            }
        }

        $query = trim($query);
        if ($query !== '') {
            $conditions = [
                'fold_search(ms.label) LIKE :q_label ESCAPE \'\\\'',
                'fold_search(COALESCE(ms.detail, \'\')) LIKE :q_detail ESCAPE \'\\\'',
            ];
            $params['q_label'] = SearchMatch::foldedContainsPattern($query);
            $params['q_detail'] = SearchMatch::foldedContainsPattern($query);

            $prefixPattern = SearchMatch::foldedPrefixPattern($query, 2);
            if ($prefixPattern !== '') {
                $conditions[] = 'fold_search(ms.label) LIKE :q_prefix ESCAPE \'\\\'';
                $conditions[] = 'fold_search(COALESCE(ms.detail, \'\')) LIKE :q_prefix_detail ESCAPE \'\\\'';
                $params['q_prefix'] = $prefixPattern;
                $params['q_prefix_detail'] = $prefixPattern;
            }

            $ftsMatch = MagazineSubjectFts::isAvailable()
                ? MagazineSubjectFts::matchExpression($query)
                : '';
            if ($ftsMatch !== '') {
                $conditions[] = 'ms.id IN (
                    SELECT magazine_subject_fts.subject_id
                    FROM magazine_subject_fts
                    WHERE magazine_subject_fts MATCH :subject_fts
                )';
                $params['subject_fts'] = $ftsMatch;
            }

            $where[] = '(' . implode(' OR ', $conditions) . ')';
        }

        $sql = 'SELECT ' . self::selectSubjectColumns('ms') . ',
                       COUNT(DISTINCT oms.oeuvre_id) AS usage_count
                FROM magazine_subject ms
                LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY ms.id
                ORDER BY ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC, ms.detail COLLATE FRENCH_NOCASE ASC
                LIMIT ' . ($query !== '' ? $prefetchLimit : $limit);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($query !== '') {
            $rows = SearchMatch::filterRankLimit(
                ListOf::assocRows($rows),
                $query,
                static fn (array $row): string => (string) ($row['label'] ?? '')
                    . ' '
                    . (string) ($row['detail'] ?? ''),
                $limit
            );
        }

        return ListOf::assocRows(array_map(fn (array $row): array => $this->hydrateSubjectRow($row), $rows));
    }

    /**
     * Valide catégorie, libellé, plateforme et année pour un numéro donné.
     *
     * @param array<string, mixed> $series
     * @param array<string, mixed> $issue
     * @return array<string, mixed>|string
     */
    public function prepareSubjectForIssue(
        string $category,
        string $label,
        string $userDetail,
        array $series,
        array $issue,
        int $userParutionYear = 0
    ): array|string {
        $label = trim($label);
        if ($label === '') {
            return 'Indiquez un nom de sujet.';
        }

        $parutionYear = MagazineSubject::normalizeParutionYear($userParutionYear);
        if ($parutionYear <= 0) {
            return 'Choisissez une année pour ce sujet.';
        }

        $category = MagazineSubject::normalizeCategory($category);
        $detail = MagazineSeriesTag::resolveDetailForSubject($series, $userDetail);
        if (MagazineSeriesTag::requiresTagChoice($series) && $detail === '') {
            return 'Choisissez un tag pour ce sujet.';
        }

        return [
            'category' => $category,
            'label' => $label,
            'detail' => $detail,
            'parution_year' => $parutionYear,
        ];
    }

    /**
     * Prépare un sujet en s’appuyant sur une fiche jeu du catalogue (titre, plateforme, année).
     *
     * @param array<string, mixed> $series
     * @param array<string, mixed> $issue
     * @return array<string, mixed>|string
     */
    public function prepareSubjectForIssueWithCatalog(
        string $category,
        string $label,
        string $userDetail,
        array $series,
        array $issue,
        int $userParutionYear,
        int $catalogOeuvreId,
        string $catalogMediaDomain = ''
    ): array|string {
        $prepared = $this->prepareSubjectForIssue(
            $category,
            $label,
            $userDetail,
            $series,
            $issue,
            $userParutionYear
        );
        if (!is_array($prepared)) {
            return $prepared;
        }

        if ($catalogOeuvreId <= 0 || !MagazineSubjectCatalogLink::isAvailable()) {
            return $prepared;
        }

        if (!MagazineSubject::supportsCatalogGameLink((string) ($prepared['category'] ?? ''))) {
            return 'Cette catégorie de sujet ne peut pas être reliée à une fiche catalogue.';
        }

        $valid = MagazineSubjectCatalogLink::validateCatalogOeuvreId(
            $catalogOeuvreId,
            $catalogMediaDomain
        );
        if ($valid !== true) {
            return $valid;
        }

        $catalog = (new MagazineSubjectCatalogLink())->resolveCatalogRow($catalogOeuvreId);
        if ($catalog === null) {
            return 'La fiche catalogue sélectionnée est introuvable.';
        }

        $prepared['label'] = trim((string) ($catalog['title'] ?? ''));
        if ($prepared['label'] === '') {
            return 'La fiche catalogue sélectionnée est incomplète (titre manquant).';
        }

        $detailHint = trim((string) ($catalog['detail_hint'] ?? ''));
        if ($prepared['detail'] === '' && $detailHint !== '') {
            $prepared['detail'] = $detailHint;
        }

        $prepared['catalog_oeuvre_id'] = $catalogOeuvreId;

        return $prepared;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrCreate(string $category, string $label, string $detail = '', int $parutionYear = 0): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $category = MagazineSubject::normalizeCategory($category);
        $label = trim($label);
        $detail = trim($detail);
        $parutionYear = max(0, $parutionYear);
        if ($label === '' || $parutionYear <= 0) {
            return null;
        }

        $existing = $this->findByUniqueKey($category, $label, $detail, $parutionYear);
        if ($existing !== null) {
            return $existing;
        }

        $similar = $this->findBySimilarLabelKey($category, $label, $detail, $parutionYear);
        if ($similar !== null) {
            return $similar;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO magazine_subject (category, label, detail, parution_year) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$category, $label, $detail, $parutionYear]);
        $id = (int) $this->db->lastInsertId();

        MagazineSubjectFts::upsert($id);

        return $this->findById($id);
    }

    /** @return list<array<string, mixed>> */
    public function listForOeuvre(int $oeuvreId): array
    {
        if (!self::tableExists() || $oeuvreId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectSubjectColumns('ms')
            . (self::hasPageColumn() ? ', oms.page' : ', 0 AS page')
            . (self::hasScoreColumn() ? ', oms.score' : ', NULL AS score')
            . ', oms.created_at AS linked_at
             FROM oeuvre_magazine_subject oms
             INNER JOIN magazine_subject ms ON ms.id = oms.subject_id
             WHERE oms.oeuvre_id = ?
             ORDER BY ms.category ASC, ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC, ms.detail COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([$oeuvreId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ListOf::assocRows(array_map(fn (array $row): array => $this->hydrateSubjectRow($row), $rows));
    }

    /** @return list<array<string, mixed>> */
    public function listForSupplement(int $supplementId): array
    {
        if (!self::tableExists() || !self::hasSupplementSubjectTable() || $supplementId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectSubjectColumns('ms')
            . ', mss.page'
            . (self::hasSupplementScoreColumn() ? ', mss.score' : ', NULL AS score')
            . ', mss.created_at AS linked_at
             FROM magazine_supplement_subject mss
             INNER JOIN magazine_subject ms ON ms.id = mss.subject_id
             WHERE mss.supplement_id = ?
             ORDER BY ms.category ASC, ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC, ms.detail COLLATE FRENCH_NOCASE ASC'
        );
        $stmt->execute([$supplementId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ListOf::assocRows(array_map(fn (array $row): array => $this->hydrateSubjectRow($row), $rows));
    }

    /** @return true|string */
    public function attachToSupplement(int $supplementId, int $subjectId, int $page = 0): bool|string
    {
        if (!self::tableExists() || !self::hasSupplementSubjectTable() || $supplementId <= 0 || $subjectId <= 0) {
            return 'Supplément ou sujet invalide.';
        }
        if ($this->findById($subjectId) === null) {
            return 'Sujet introuvable.';
        }

        $page = self::normalizePage($page);
        $existsStmt = $this->db->prepare(
            'SELECT page FROM magazine_supplement_subject WHERE supplement_id = ? AND subject_id = ? LIMIT 1'
        );
        $existsStmt->execute([$supplementId, $subjectId]);
        $existingPage = $existsStmt->fetchColumn();
        if ($existingPage !== false) {
            if ($page > 0 && (int) $existingPage !== $page) {
                return $this->updateSupplementLinkPage($supplementId, $subjectId, $page);
            }

            return true;
        }

        $this->db->prepare(
            'INSERT INTO magazine_supplement_subject (supplement_id, subject_id, page) VALUES (?, ?, ?)'
        )->execute([$supplementId, $subjectId, $page]);

        return true;
    }

    /** @return true|string */
    public function updateSupplementLinkPage(int $supplementId, int $subjectId, int $page): bool|string
    {
        if (!self::hasSupplementSubjectTable() || $supplementId <= 0 || $subjectId <= 0) {
            return 'Supplément ou sujet invalide.';
        }

        $page = self::normalizePage($page);
        $stmt = $this->db->prepare(
            'UPDATE magazine_supplement_subject SET page = ? WHERE supplement_id = ? AND subject_id = ?'
        );
        $stmt->execute([$page, $supplementId, $subjectId]);

        if ($stmt->rowCount() <= 0) {
            $check = $this->db->prepare(
                'SELECT 1 FROM magazine_supplement_subject WHERE supplement_id = ? AND subject_id = ? LIMIT 1'
            );
            $check->execute([$supplementId, $subjectId]);
            if ($check->fetchColumn() === false) {
                return 'Ce sujet n’est pas lié à ce supplément.';
            }
        }

        return true;
    }

    /**
     * Met à jour la note d’un test rattaché à un supplément.
     *
     * @return true|string
     */
    public function updateSupplementLinkScore(int $supplementId, int $subjectId, ?float $score): bool|string
    {
        if (!self::hasSupplementSubjectTable() || $supplementId <= 0 || $subjectId <= 0) {
            return 'Supplément ou sujet invalide.';
        }
        if (!self::hasSupplementScoreColumn()) {
            return 'La colonne score n’est pas encore disponible (migration 073).';
        }

        $stmt = $this->db->prepare(
            'UPDATE magazine_supplement_subject SET score = ? WHERE supplement_id = ? AND subject_id = ?'
        );
        $stmt->execute([$score, $supplementId, $subjectId]);

        if ($stmt->rowCount() <= 0) {
            $check = $this->db->prepare(
                'SELECT 1 FROM magazine_supplement_subject WHERE supplement_id = ? AND subject_id = ? LIMIT 1'
            );
            $check->execute([$supplementId, $subjectId]);
            if ($check->fetchColumn() === false) {
                return 'Ce sujet n’est pas lié à ce supplément.';
            }
        }

        return true;
    }

    public function detachFromSupplement(int $supplementId, int $subjectId): bool
    {
        if (!self::hasSupplementSubjectTable() || $supplementId <= 0 || $subjectId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM magazine_supplement_subject WHERE supplement_id = ? AND subject_id = ?'
        );
        $stmt->execute([$supplementId, $subjectId]);

        return $stmt->rowCount() > 0;
    }

    /** @return true|string */
    public function attachToOeuvre(int $oeuvreId, int $subjectId, int $page = 0): bool|string
    {
        if (!self::tableExists() || $oeuvreId <= 0 || $subjectId <= 0) {
            return 'Sujet ou numéro invalide.';
        }
        if ($this->findById($subjectId) === null) {
            return 'Sujet introuvable.';
        }

        $page = self::normalizePage($page);

        if (self::hasPageColumn()) {
            $existsStmt = $this->db->prepare(
                'SELECT page FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ? LIMIT 1'
            );
            $existsStmt->execute([$oeuvreId, $subjectId]);
            $existingPage = $existsStmt->fetchColumn();
            if ($existingPage !== false) {
                // Lien déjà présent : mettre à jour la page si une nouvelle valeur est fournie.
                if ($page > 0 && (int) $existingPage !== $page) {
                    return $this->updateLinkPage($oeuvreId, $subjectId, $page);
                }

                return true;
            }

            $this->db->prepare(
                'INSERT INTO oeuvre_magazine_subject (oeuvre_id, subject_id, page) VALUES (?, ?, ?)'
            )->execute([$oeuvreId, $subjectId, $page]);

            return true;
        }

        $this->db->prepare(
            'INSERT OR IGNORE INTO oeuvre_magazine_subject (oeuvre_id, subject_id) VALUES (?, ?)'
        )->execute([$oeuvreId, $subjectId]);

        return true;
    }

    /**
     * Met à jour la page PDF d’un lien sujet déjà rattaché à un numéro.
     *
     * @return true|string
     */
    public function updateLinkPage(int $oeuvreId, int $subjectId, int $page): bool|string
    {
        if (!self::tableExists() || $oeuvreId <= 0 || $subjectId <= 0) {
            return 'Sujet ou numéro invalide.';
        }
        if (!self::hasPageColumn()) {
            return 'La colonne page n’est pas encore disponible (migration 071).';
        }

        $page = self::normalizePage($page);
        $stmt = $this->db->prepare(
            'UPDATE oeuvre_magazine_subject SET page = ? WHERE oeuvre_id = ? AND subject_id = ?'
        );
        $stmt->execute([$page, $oeuvreId, $subjectId]);

        if ($stmt->rowCount() <= 0) {
            // SQLite peut renvoyer 0 si la valeur est identique : vérifier que le lien existe.
            $check = $this->db->prepare(
                'SELECT 1 FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ? LIMIT 1'
            );
            $check->execute([$oeuvreId, $subjectId]);
            if ($check->fetchColumn() === false) {
                return 'Ce sujet n’est pas lié à ce numéro.';
            }
        }

        return true;
    }

    /**
     * Met à jour la note d’un test rattaché à un numéro.
     *
     * @return true|string
     */
    public function updateLinkScore(int $oeuvreId, int $subjectId, ?float $score): bool|string
    {
        if (!self::tableExists() || $oeuvreId <= 0 || $subjectId <= 0) {
            return 'Sujet ou numéro invalide.';
        }
        if (!self::hasScoreColumn()) {
            return 'La colonne score n’est pas encore disponible (migration 073).';
        }

        $stmt = $this->db->prepare(
            'UPDATE oeuvre_magazine_subject SET score = ? WHERE oeuvre_id = ? AND subject_id = ?'
        );
        $stmt->execute([$score, $oeuvreId, $subjectId]);

        if ($stmt->rowCount() <= 0) {
            $check = $this->db->prepare(
                'SELECT 1 FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ? LIMIT 1'
            );
            $check->execute([$oeuvreId, $subjectId]);
            if ($check->fetchColumn() === false) {
                return 'Ce sujet n’est pas lié à ce numéro.';
            }
        }

        return true;
    }

    public function detachFromOeuvre(int $oeuvreId, int $subjectId): bool
    {
        if (!self::tableExists() || $oeuvreId <= 0 || $subjectId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'DELETE FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ?'
        );
        $stmt->execute([$oeuvreId, $subjectId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Après fusion catalogue : conserver les liens sujets magazines de la fiche supprimée.
     *
     * - sujet → fiche catalogue (catalog_oeuvre_id) : réaffectés vers keep
     * - numéro magazine → sujets (oeuvre_magazine_subject) : copiés vers keep
     */
    public function reassignOnOeuvreMerge(int $keepOeuvreId, int $removeOeuvreId): void
    {
        if (!self::tableExists() || $keepOeuvreId <= 0 || $removeOeuvreId <= 0 || $keepOeuvreId === $removeOeuvreId) {
            return;
        }

        // Pont sujet magazine → fiche catalogue (jeu/film…).
        // Sans cela, ON DELETE SET NULL efface le lien à la suppression de removeId.
        if (MagazineGameLink::catalogColumnExists()) {
            $this->db->prepare(
                'UPDATE magazine_subject SET catalog_oeuvre_id = ? WHERE catalog_oeuvre_id = ?'
            )->execute([$keepOeuvreId, $removeOeuvreId]);
        }

        // Numéro magazine → sujets : transférer avant le CASCADE sur removeId.
        if (self::hasPageColumn()) {
            $this->db->prepare(
                'INSERT INTO oeuvre_magazine_subject (oeuvre_id, subject_id, page)
                 SELECT ?, subject_id, page FROM oeuvre_magazine_subject WHERE oeuvre_id = ?
                 ON CONFLICT(oeuvre_id, subject_id) DO UPDATE SET
                    page = CASE
                        WHEN oeuvre_magazine_subject.page > 0 THEN oeuvre_magazine_subject.page
                        ELSE excluded.page
                    END'
            )->execute([$keepOeuvreId, $removeOeuvreId]);
        } else {
            $this->db->prepare(
                'INSERT OR IGNORE INTO oeuvre_magazine_subject (oeuvre_id, subject_id)
                 SELECT ?, subject_id FROM oeuvre_magazine_subject WHERE oeuvre_id = ?'
            )->execute([$keepOeuvreId, $removeOeuvreId]);
        }

        $this->db->prepare(
            'DELETE FROM oeuvre_magazine_subject WHERE oeuvre_id = ?'
        )->execute([$removeOeuvreId]);
    }

    /**
     * @return array{issue_count: int, series_count: int}
     */
    public function countInLibrary(int $subjectId, int $userId, int $foyerId): array
    {
        if (!self::tableExists() || $subjectId <= 0) {
            return ['issue_count' => 0, 'series_count' => 0];
        }

        [$librarySql, $params] = $this->libraryScopeSql($userId, $foyerId, null);
        $params['subject_id'] = $subjectId;

        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT b.id) AS issue_count, COUNT(DISTINCT om.series_id) AS series_count
             FROM oeuvre_magazine_subject oms
             INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
             INNER JOIN bibliotheque b ON b.oeuvre_id = om.oeuvre_id
             WHERE oms.subject_id = :subject_id AND ' . $librarySql
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'issue_count' => (int) ($row['issue_count'] ?? 0),
            'series_count' => (int) ($row['series_count'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listIssuesInLibrary(
        int $subjectId,
        int $userId,
        int $foyerId,
        ?string $statut = null,
        int $limit = self::ISSUES_PER_PAGE,
        int $offset = 0
    ): array {
        if (!self::tableExists() || $subjectId <= 0) {
            return [];
        }

        [$librarySql, $params] = $this->libraryScopeSql($userId, $foyerId, $statut);
        $params['subject_id'] = $subjectId;

        $sql = 'SELECT b.id AS bib_id, b.statut, b.support_physique,
                       o.id AS oeuvre_id, o.titre, o.poster_url,
                       om.series_id, om.numero, om.numero_ordre, om.date_parution, om.pages,
                       om.est_hors_serie, om.stored_object_id,
                       s.titre AS series_titre, s.publication_type
                FROM oeuvre_magazine_subject oms
                INNER JOIN magazine_subject ms ON ms.id = oms.subject_id
                INNER JOIN oeuvre_magazine om ON om.oeuvre_id = oms.oeuvre_id
                INNER JOIN oeuvres o ON o.id = om.oeuvre_id AND o.media_domain = :domain
                INNER JOIN bibliotheque b ON b.oeuvre_id = o.id
                INNER JOIN series s ON s.id = om.series_id
                WHERE ms.id = :subject_id AND ' . $librarySql . '
                ORDER BY om.date_parution DESC, om.numero_ordre DESC, b.id DESC
                LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        $params['domain'] = MediaDomain::MAGAZINE;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($this->filterParamsForSql($sql, $params));

        return ListOf::assocRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function countIssuesInLibrary(int $subjectId, int $userId, int $foyerId, ?string $statut = null): int
    {
        return $this->countInLibrary($subjectId, $userId, $foyerId)['issue_count'];
    }

    /**
     * Retrouve un sujet existant dont le libellé diffère seulement par espaces ou ponctuation.
     */
    private function findBySimilarLabelKey(string $category, string $label, string $detail, int $parutionYear): ?array
    {
        $labelKey = MagazineSubject::normalizeLabelKey($label);
        if ($labelKey === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ' . self::selectSubjectColumns() . '
             FROM magazine_subject
             WHERE category = ?
               AND LOWER(detail) = LOWER(?)
               AND parution_year = ?'
        );
        $stmt->execute([$category, $detail, $parutionYear]);

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (MagazineSubject::normalizeLabelKey((string) ($row['label'] ?? '')) === $labelKey) {
                return $this->hydrateSubjectRow($row);
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function findByUniqueKey(string $category, string $label, string $detail, int $parutionYear): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::selectSubjectColumns() . '
             FROM magazine_subject
             WHERE category = ?
               AND LOWER(label) = LOWER(?)
               AND LOWER(detail) = LOWER(?)
               AND parution_year = ?
             LIMIT 1'
        );
        $stmt->execute([$category, $label, $detail, $parutionYear]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateSubjectRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    public function hydrateSubjectRowPublic(array $row): array
    {
        return $this->hydrateSubjectRow($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrateSubjectRow(array $row): array
    {
        $category = MagazineSubject::normalizeCategory((string) ($row['category'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        $detail = trim((string) ($row['detail'] ?? ''));
        $parutionYear = (int) ($row['parution_year'] ?? 0);

        $row['category'] = $category;
        $row['category_label'] = MagazineSubject::label($category);
        $row['parution_year'] = $parutionYear;
        $row['detail_label'] = MagazineSeriesTag::detailLabel($detail);
        $row['display_label'] = MagazineSubject::displayLabel($label, $detail, $parutionYear);
        $row['usage_count'] = (int) ($row['usage_count'] ?? 0);
        $row['catalog_oeuvre_id'] = (int) ($row['catalog_oeuvre_id'] ?? 0);
        $row['page'] = self::normalizePage($row['page'] ?? 0);
        if (array_key_exists('score', $row) && $row['score'] !== null && $row['score'] !== '') {
            $row['score'] = (float) $row['score'];
        } else {
            $row['score'] = null;
        }

        return $row;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function libraryScopeSql(int $userId, int $foyerId, ?string $statut): array
    {
        $statut = $statut !== null ? LibraryStatut::normalize($statut) : null;

        if ($statut === LibraryStatut::COLLECTION) {
            return [
                '(b.statut = :collection_filter AND b.foyer_id = :foyer_id)',
                [
                    'collection_filter' => LibraryStatut::COLLECTION,
                    'foyer_id' => $foyerId,
                ],
            ];
        }

        if ($statut === LibraryStatut::WISHLIST) {
            return [
                '(b.statut = :wishlist_filter AND b.user_id = :user_id)',
                [
                    'wishlist_filter' => LibraryStatut::WISHLIST,
                    'user_id' => $userId,
                ],
            ];
        }

        return [
            '((b.statut = :collection_scope AND b.foyer_id = :foyer_id)
              OR (b.statut = :wishlist_scope AND b.user_id = :user_id))',
            [
                'collection_scope' => LibraryStatut::COLLECTION,
                'wishlist_scope' => LibraryStatut::WISHLIST,
                'foyer_id' => $foyerId,
                'user_id' => $userId,
            ],
        ];
    }
}
