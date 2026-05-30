<?php
/**
 * Versions recherchées sur une envie (support + EAN) — préparation achats en ligne.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class WishlistTargetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'wishlist_targets' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return ?array<string, mixed> */
    public function findByIdForBibliotheque(int $targetId, int $bibliothequeId): ?array
    {
        if ($targetId <= 0 || $bibliothequeId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, support_physique, ean, oeuvre_ean_id, label, created_at
             FROM wishlist_targets
             WHERE id = ? AND bibliotheque_id = ?
             LIMIT 1'
        );
        $stmt->execute([$targetId, $bibliothequeId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listForBibliothequeId(int $bibliothequeId): array
    {
        if ($bibliothequeId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, support_physique, ean, oeuvre_ean_id, label, created_at
             FROM wishlist_targets
             WHERE bibliotheque_id = ?
             ORDER BY support_physique COLLATE FRENCH_NOCASE, ean ASC'
        );
        $stmt->execute([$bibliothequeId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<int> $bibliothequeIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function mapByBibliothequeIds(array $bibliothequeIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $bibliothequeIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === [] || !self::tableExists()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, support_physique, ean, oeuvre_ean_id, label, created_at
             FROM wishlist_targets
             WHERE bibliotheque_id IN (' . $placeholders . ')
             ORDER BY bibliotheque_id, support_physique COLLATE FRENCH_NOCASE, ean ASC'
        );
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bid = (int) ($row['bibliotheque_id'] ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $map[$bid][] = $row;
        }

        return $map;
    }

    /**
     * @return true|int|string
     */
    public function add(
        int $bibliothequeId,
        string $supportKey,
        string $ean = '',
        string $label = '',
        ?int $oeuvreEanId = null
    ): bool|int|string {
        if (!self::tableExists()) {
            return 'Les cibles d’achat ne sont pas disponibles (migration en attente).';
        }
        if ($bibliothequeId <= 0) {
            return 'Envie invalide.';
        }

        $supportKey = SupportPhysique::normalize($supportKey);
        if (!SupportPhysique::isValid($supportKey)) {
            return 'Choisissez un support (DVD, Blu-ray ou Blu-ray 4K).';
        }

        $ean = OeuvreEanRepository::normalizeEan($ean);
        if ($ean !== '' && (strlen($ean) < 8 || strlen($ean) > 14)) {
            return 'Le code EAN doit contenir entre 8 et 14 chiffres.';
        }

        $label = trim($label);

        if ($this->findForBibliothequeAndSupport($bibliothequeId, $supportKey) !== null) {
            return 'Une version est déjà indiquée pour ce support.';
        }

        if ($oeuvreEanId !== null && $oeuvreEanId > 0) {
            $catalogRow = $this->fetchOeuvreEanRow($oeuvreEanId);
            if ($catalogRow === null) {
                return 'Édition catalogue introuvable.';
            }
            if ($supportKey !== '' && (string) ($catalogRow['support_physique'] ?? '') !== $supportKey) {
                return 'Le support ne correspond pas à l’édition catalogue choisie.';
            }
            $catalogEan = (string) ($catalogRow['ean'] ?? '');
            if ($ean !== '' && $ean !== $catalogEan) {
                return 'Le code EAN ne correspond pas à l’édition catalogue.';
            }
            if ($ean === '') {
                $ean = $catalogEan;
            }
            $supportKey = SupportPhysique::normalize((string) ($catalogRow['support_physique'] ?? $supportKey));
        }

        $this->db->prepare(
            'INSERT INTO wishlist_targets (
                bibliotheque_id, support_physique, ean, oeuvre_ean_id, label, created_at
             ) VALUES (?, ?, ?, ?, ?, datetime(\'now\'))'
        )->execute([
            $bibliothequeId,
            $supportKey,
            $ean,
            $oeuvreEanId !== null && $oeuvreEanId > 0 ? $oeuvreEanId : null,
            $label,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return true|int|string
     */
    public function addFromCatalogEan(int $bibliothequeId, int $oeuvreEanId, int $oeuvreId): bool|int|string
    {
        $row = $this->fetchOeuvreEanRow($oeuvreEanId);
        if ($row === null) {
            return 'Édition catalogue introuvable.';
        }
        if ($oeuvreId > 0 && (int) ($row['oeuvre_id'] ?? 0) !== $oeuvreId) {
            return 'Cette édition ne correspond pas au film.';
        }

        return $this->add(
            $bibliothequeId,
            (string) ($row['support_physique'] ?? ''),
            (string) ($row['ean'] ?? ''),
            (string) ($row['label'] ?? ''),
            $oeuvreEanId
        );
    }

    /** @return true|string */
    public function delete(int $targetId, int $bibliothequeId): bool|string
    {
        if ($targetId <= 0 || $bibliothequeId <= 0 || !self::tableExists()) {
            return 'Cible invalide.';
        }
        $stmt = $this->db->prepare(
            'DELETE FROM wishlist_targets WHERE id = ? AND bibliotheque_id = ?'
        );
        $stmt->execute([$targetId, $bibliothequeId]);

        return $stmt->rowCount() > 0 ? true : 'Version recherchée introuvable.';
    }

    public function deleteAllForBibliotheque(int $bibliothequeId): void
    {
        if ($bibliothequeId <= 0 || !self::tableExists()) {
            return;
        }
        $this->db->prepare('DELETE FROM wishlist_targets WHERE bibliotheque_id = ?')
            ->execute([$bibliothequeId]);
    }

    /** @return ?array<string, mixed> */
    private function findForBibliothequeAndSupport(int $bibliothequeId, string $supportKey): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, support_physique, ean, oeuvre_ean_id, label, created_at
             FROM wishlist_targets
             WHERE bibliotheque_id = ? AND support_physique = ?
             LIMIT 1'
        );
        $stmt->execute([$bibliothequeId, $supportKey]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return ?array<string, mixed> */
    private function fetchOeuvreEanRow(int $oeuvreEanId): ?array
    {
        if ($oeuvreEanId <= 0 || !OeuvreEanRepository::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, oeuvre_id, ean, support_physique, label
             FROM oeuvre_eans WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$oeuvreEanId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}
