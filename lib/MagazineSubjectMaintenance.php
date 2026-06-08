<?php
/**
 * Maintenance admin du catalogue de sujets magazines (orphelins, doublons, fusion).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class MagazineSubjectMaintenance
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
        return MagazineSubjectRepository::isAvailable();
    }

    /** @return array{total: int, orphan_count: int, duplicate_groups: int} */
    public function dashboardStats(): array
    {
        if (!self::isAvailable()) {
            return ['total' => 0, 'orphan_count' => 0, 'duplicate_groups' => 0];
        }

        $total = (int) $this->db->query('SELECT COUNT(*) FROM magazine_subject')->fetchColumn();
        $orphanCount = count($this->findOrphanSubjects(5000));

        return [
            'total' => $total,
            'orphan_count' => $orphanCount,
            'duplicate_groups' => count($this->findDuplicateGroupsByLabelKey(500)),
        ];
    }

    /**
     * Sujets sans aucun numéro associé (souvent créés par erreur puis abandonnés).
     *
     * @return list<array<string, mixed>>
     */
    public function findOrphanSubjects(int $limit = 100): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $limit = max(1, min($limit, 500));
        $stmt = $this->db->query(
            'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at
             FROM magazine_subject ms
             LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             WHERE oms.subject_id IS NULL
             ORDER BY ms.created_at DESC, ms.id DESC
             LIMIT ' . $limit
        );

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->hydrateRow($row, 0), $rows);
    }

    /**
     * Sujets proches (même catégorie, tag, année, libellé normalisé) avec libellés différents.
     *
     * @return list<array<string, mixed>>
     */
    public function findDuplicateGroupsByLabelKey(int $limit = 40): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $stmt = $this->db->query(
            'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at,
                    COUNT(DISTINCT oms.oeuvre_id) AS usage_count
             FROM magazine_subject ms
             LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             GROUP BY ms.id
             ORDER BY ms.parution_year DESC, ms.label COLLATE FRENCH_NOCASE ASC'
        );

        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $hydrated = $this->hydrateRow($row, (int) ($row['usage_count'] ?? 0));
            $groupKey = $this->duplicateGroupKey($hydrated);
            if ($groupKey === '') {
                continue;
            }
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'subjects' => [],
                ];
            }
            $groups[$groupKey]['subjects'][] = $hydrated;
        }

        $out = [];
        foreach ($groups as $group) {
            if (count($group['subjects']) < 2) {
                continue;
            }
            $out[] = $group;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Supprime un sujet orphelin (aucun numéro lié).
     *
     * @return true|string
     */
    public function deleteOrphanSubject(int $subjectId, int $adminUserId): bool|string
    {
        if (!self::isAvailable() || $subjectId <= 0) {
            return 'Sujet invalide.';
        }

        $subject = $this->findSubjectRow($subjectId);
        if ($subject === null) {
            return 'Sujet introuvable.';
        }

        if ($this->countLinks($subjectId) > 0) {
            return 'Ce sujet est encore lié à des numéros — fusionnez-le plutôt qu’une suppression.';
        }

        $this->db->prepare('DELETE FROM magazine_subject WHERE id = ?')->execute([$subjectId]);

        $this->audit->log(
            $adminUserId,
            CatalogAuditLog::ACTION_MAGAZINE_SUBJECT_DELETE,
            null,
            'Sujet #' . $subjectId . ' supprimé (« ' . (string) ($subject['display_label'] ?? '') . ' »)'
        );

        return true;
    }

    /**
     * @return array{deleted: int, errors: list<string>}
     */
    public function purgeOrphanSubjects(int $adminUserId): array
    {
        $orphans = $this->findOrphanSubjects(5000);
        $deleted = 0;
        $errors = [];

        foreach ($orphans as $subject) {
            $subjectId = (int) ($subject['id'] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }
            $result = $this->deleteOrphanSubject($subjectId, $adminUserId);
            if ($result === true) {
                $deleted++;
            } else {
                $errors[] = '#' . $subjectId;
            }
        }

        if ($deleted > 0) {
            $this->audit->log(
                $adminUserId,
                CatalogAuditLog::ACTION_MAGAZINE_SUBJECT_PURGE,
                null,
                $deleted . ' sujet(s) orphelin(s) supprimé(s)'
            );
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * Fusionne removeId dans keepId (numéros réaffectés, sujet doublon supprimé).
     *
     * @return true|string
     */
    public function mergeSubjects(int $keepId, int $removeId, int $adminUserId): bool|string
    {
        if (!self::isAvailable() || $keepId <= 0 || $removeId <= 0) {
            return 'Identifiants invalides.';
        }
        if ($keepId === $removeId) {
            return 'Choisissez deux sujets différents.';
        }

        $keep = $this->findSubjectRow($keepId);
        $remove = $this->findSubjectRow($removeId);
        if ($keep === null || $remove === null) {
            return 'Un des sujets est introuvable.';
        }

        $this->db->beginTransaction();
        try {
            $stmtLinks = $this->db->prepare(
                'SELECT oeuvre_id FROM oeuvre_magazine_subject WHERE subject_id = ?'
            );
            $stmtLinks->execute([$removeId]);
            $oeuvreIds = $stmtLinks->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $attach = $this->db->prepare(
                'INSERT OR IGNORE INTO oeuvre_magazine_subject (oeuvre_id, subject_id) VALUES (?, ?)'
            );
            $detach = $this->db->prepare(
                'DELETE FROM oeuvre_magazine_subject WHERE oeuvre_id = ? AND subject_id = ?'
            );

            foreach ($oeuvreIds as $oeuvreId) {
                $oeuvreId = (int) $oeuvreId;
                if ($oeuvreId <= 0) {
                    continue;
                }
                $attach->execute([$oeuvreId, $keepId]);
                $detach->execute([$oeuvreId, $removeId]);
            }

            $this->db->prepare('DELETE FROM magazine_subject WHERE id = ?')->execute([$removeId]);

            MagazineSubjectFts::upsert($keepId);

            $this->audit->log(
                $adminUserId,
                CatalogAuditLog::ACTION_MAGAZINE_SUBJECT_MERGE,
                null,
                'Sujet #' . $removeId . ' → #' . $keepId
                . ' (« ' . (string) ($remove['display_label'] ?? '') . ' »'
                . ' → « ' . (string) ($keep['display_label'] ?? '') . ' »)'
            );

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return 'Fusion impossible : ' . $e->getMessage();
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row, int $usageCount): array
    {
        $category = MagazineSubject::normalizeCategory((string) ($row['category'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        $detail = trim((string) ($row['detail'] ?? ''));
        $parutionYear = (int) ($row['parution_year'] ?? 0);

        $row['category'] = $category;
        $row['category_label'] = MagazineSubject::label($category);
        $row['parution_year'] = $parutionYear;
        $row['display_label'] = MagazineSubject::displayLabel($label, $detail, $parutionYear);
        $row['usage_count'] = $usageCount;
        $row['label_key'] = MagazineSubject::normalizeLabelKey($label);
        $row['is_empty_label'] = $label === '' || MagazineSubject::normalizeLabelKey($label) === '';

        return $row;
    }

    /** @param array<string, mixed> $subject */
    private function duplicateGroupKey(array $subject): string
    {
        $labelKey = (string) ($subject['label_key'] ?? '');
        if ($labelKey === '') {
            return '';
        }

        return implode('|', [
            (string) ($subject['category'] ?? ''),
            mb_strtolower(trim((string) ($subject['detail'] ?? ''))),
            (string) (int) ($subject['parution_year'] ?? 0),
            $labelKey,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findSubjectRow(int $subjectId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ms.id, ms.category, ms.label, ms.detail, ms.parution_year, ms.created_at,
                    COUNT(DISTINCT oms.oeuvre_id) AS usage_count
             FROM magazine_subject ms
             LEFT JOIN oeuvre_magazine_subject oms ON oms.subject_id = ms.id
             WHERE ms.id = ?
             GROUP BY ms.id
             LIMIT 1'
        );
        $stmt->execute([$subjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false
            ? $this->hydrateRow($row, (int) ($row['usage_count'] ?? 0))
            : null;
    }

    private function countLinks(int $subjectId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM oeuvre_magazine_subject WHERE subject_id = ?'
        );
        $stmt->execute([$subjectId]);

        return (int) $stmt->fetchColumn();
    }
}
