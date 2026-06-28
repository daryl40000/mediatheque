<?php
/**
 * Maintenance admin — rattachement rétroactif sujets magazine ↔ jeux catalogue.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineGameLinkMaintenance
{
    private PDO $db;

    private CatalogAuditLog $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new CatalogAuditLog();
    }

    public static function isAvailable(): bool
    {
        return MagazineGameLink::isAvailable();
    }

    /**
     * @return array{linkable_total: int, linked_count: int, unlinked_count: int}
     */
    public function dashboardStats(): array
    {
        if (!self::isAvailable()) {
            return ['linkable_total' => 0, 'linked_count' => 0, 'unlinked_count' => 0];
        }

        $categories = $this->linkableCategorySqlIn();
        $linkableTotal = (int) $this->db->query(
            'SELECT COUNT(*) FROM magazine_subject ms WHERE ms.category IN (' . $categories . ')'
        )->fetchColumn();

        $linkedCount = (int) $this->db->query(
            'SELECT COUNT(*) FROM magazine_subject ms
             WHERE ms.category IN (' . $categories . ')
               AND ms.catalog_oeuvre_id IS NOT NULL'
        )->fetchColumn();

        return [
            'linkable_total' => $linkableTotal,
            'linked_count' => $linkedCount,
            'unlinked_count' => max(0, $linkableTotal - $linkedCount),
        ];
    }

    /**
     * Sujets test/preview/interview sans lien catalogue (avec usage sur numéros).
     *
     * @return list<array<string, mixed>>
     */
    public function findUnlinkedSubjects(string $query = '', int $limit = 100): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        return $this->findSubjects(false, $query, $limit);
    }

    /**
     * Sujets déjà reliés à une fiche jeu catalogue (correction / retrait).
     *
     * @return list<array<string, mixed>>
     */
    public function findLinkedSubjects(string $query = '', int $limit = 50): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        return $this->findSubjects(true, $query, $limit);
    }

    /**
     * Propositions catalogue pour un libellé de sujet (aide admin, homonymes possibles).
     *
     * @return list<array<string, mixed>>
     */
    public function suggestCatalogMatches(string $label, int $limit = 5): array
    {
        if (!self::isAvailable() || !GameRepository::isAvailable()) {
            return [];
        }

        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $games = (new GameRepository())->searchCatalog($label, max(1, min($limit, 10)));
        $out = [];
        foreach ($games as $game) {
            $out[] = [
                'oeuvre_id' => (int) ($game['oeuvre_id'] ?? 0),
                'display_label' => (string) ($game['display_label'] ?? GameRowMapper::displayTitle($game)),
                'platform_label' => (string) ($game['platform_label'] ?? ''),
                'annee' => (int) ($game['annee'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Relie ou retire le lien catalogue d’un sujet (journal audit admin).
     *
     * @return true|string
     */
    public function setSubjectCatalogLink(int $subjectId, ?int $catalogOeuvreId, int $adminUserId): bool|string
    {
        if (!self::isAvailable() || $subjectId <= 0) {
            return 'Pont magazine ↔ jeu non disponible.';
        }

        $subject = $this->findSubjectRow($subjectId);
        if ($subject === null) {
            return 'Sujet introuvable.';
        }

        if (!MagazineGameLink::supportsSubjectCategory((string) ($subject['category'] ?? ''))) {
            return 'Cette catégorie de sujet ne peut pas être reliée à un jeu.';
        }

        $previousOeuvreId = (int) ($subject['catalog_oeuvre_id'] ?? 0);
        $result = (new MagazineGameLink())->setSubjectCatalogLink(
            $subjectId,
            $catalogOeuvreId !== null && $catalogOeuvreId > 0 ? $catalogOeuvreId : null
        );
        if ($result !== true) {
            return $result;
        }

        $newOeuvreId = $catalogOeuvreId !== null && $catalogOeuvreId > 0 ? $catalogOeuvreId : 0;
        if ($newOeuvreId !== $previousOeuvreId) {
            $details = 'Sujet #' . $subjectId . ' « ' . (string) ($subject['display_label'] ?? '') . ' »';
            if ($newOeuvreId > 0) {
                $game = (new GameRepository())->findCatalogByOeuvreId($newOeuvreId);
                $gameLabel = $game !== null
                    ? (string) ($game['display_label'] ?? $game['titre'] ?? '')
                    : '#' . $newOeuvreId;
                $details .= ' → jeu ' . $gameLabel . ' (#' . $newOeuvreId . ')';
            } else {
                $details .= ' — lien catalogue retiré';
            }

            $this->audit->log(
                $adminUserId,
                CatalogAuditLog::ACTION_MAGAZINE_GAME_LINK,
                $newOeuvreId > 0 ? $newOeuvreId : null,
                $details
            );
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findSubjects(bool $linkedOnly, string $query, int $limit): array
    {
        $limit = max(1, min($limit, 200));
        $params = [];
        $where = ['ms.category IN (' . $this->linkableCategorySqlIn() . ')'];

        if ($linkedOnly) {
            $where[] = 'ms.catalog_oeuvre_id IS NOT NULL';
        } else {
            $where[] = '(ms.catalog_oeuvre_id IS NULL OR ms.catalog_oeuvre_id = 0)';
        }

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(fold_search(ms.label) LIKE :q_label ESCAPE \'\\\'
                          OR fold_search(COALESCE(ms.detail, \'\')) LIKE :q_detail ESCAPE \'\\\''
                          . ($linkedOnly ? ' OR fold_search(COALESCE(o_game.titre, \'\')) LIKE :q_game ESCAPE \'\\\'' : '') . ')';
            $pattern = SearchMatch::foldedContainsPattern($query);
            $params['q_label'] = $pattern;
            $params['q_detail'] = $pattern;
            if ($linkedOnly) {
                $params['q_game'] = $pattern;
            }
        }

        $joinGame = $linkedOnly
            ? ' LEFT JOIN oeuvres o_game ON o_game.id = ms.catalog_oeuvre_id'
            : '';

        $sql = 'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at,
                       ms.catalog_oeuvre_id,
                       COUNT(DISTINCT oms.oeuvre_id) AS usage_count
                       ' . ($linkedOnly ? ', o_game.titre AS catalog_game_titre' : '') . '
                FROM magazine_subject ms
                LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
                ' . $joinGame . '
                WHERE ' . implode(' AND ', $where) . '
                GROUP BY ms.id
                ORDER BY usage_count DESC, ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC
                LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $hydrated = $this->hydrateRow($row);
            if ($linkedOnly) {
                $oeuvreId = (int) ($row['catalog_oeuvre_id'] ?? 0);
                $game = $oeuvreId > 0 ? (new GameRepository())->findCatalogByOeuvreId($oeuvreId) : null;
                $hydrated['catalog_game'] = $game;
                $hydrated['catalog_game_label'] = $game !== null
                    ? (string) ($game['display_label'] ?? $game['titre'] ?? '')
                    : trim((string) ($row['catalog_game_titre'] ?? ''));
            } else {
                $hydrated['suggestions'] = $this->suggestCatalogMatches((string) ($row['label'] ?? ''), 3);
            }
            $rows[] = $hydrated;
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function findSubjectRow(int $subjectId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at,
                    ms.catalog_oeuvre_id,
                    COUNT(DISTINCT oms.oeuvre_id) AS usage_count
             FROM magazine_subject ms
             LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             WHERE ms.id = ?
             GROUP BY ms.id
             LIMIT 1'
        );
        $stmt->execute([$subjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrateRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row): array
    {
        $category = MagazineSubject::normalizeCategory((string) ($row['category'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        $detail = trim((string) ($row['detail'] ?? ''));
        $parutionYear = (int) ($row['parution_year'] ?? 0);

        $row['category'] = $category;
        $row['category_label'] = MagazineSubject::label($category);
        $row['parution_year'] = $parutionYear;
        $row['display_label'] = MagazineSubject::displayLabel($label, $detail, $parutionYear);
        $row['usage_count'] = (int) ($row['usage_count'] ?? 0);
        $row['catalog_oeuvre_id'] = (int) ($row['catalog_oeuvre_id'] ?? 0);

        return $row;
    }

    private function linkableCategorySqlIn(): string
    {
        $parts = [];
        foreach ([MagazineSubject::TEST, MagazineSubject::PREVIEW, MagazineSubject::INTERVIEW] as $category) {
            $parts[] = $this->db->quote($category);
        }

        return implode(', ', $parts);
    }
}
