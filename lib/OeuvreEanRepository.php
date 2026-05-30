<?php
/**
 * Codes EAN catalogue par œuvre et support physique.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class OeuvreEanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oeuvre_eans' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    public function listForOeuvre(int $oeuvreId): array
    {
        if ($oeuvreId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, oeuvre_id, ean, support_physique, label, source, created_at
             FROM oeuvre_eans
             WHERE oeuvre_id = ?
             ORDER BY support_physique COLLATE FRENCH_NOCASE, ean ASC'
        );
        $stmt->execute([$oeuvreId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findByEan(string $ean): ?array
    {
        $ean = self::normalizeEan($ean);
        if ($ean === '' || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, oeuvre_id, ean, support_physique, label, source, created_at
             FROM oeuvre_eans WHERE ean = ? LIMIT 1'
        );
        $stmt->execute([$ean]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function findForOeuvreAndSupport(int $oeuvreId, string $supportKey): ?array
    {
        if ($oeuvreId <= 0 || !self::tableExists()) {
            return null;
        }
        $supportKey = SupportPhysique::normalize($supportKey);
        $stmt = $this->db->prepare(
            'SELECT id, oeuvre_id, ean, support_physique, label, source, created_at
             FROM oeuvre_eans
             WHERE oeuvre_id = ? AND support_physique = ?
             LIMIT 1'
        );
        $stmt->execute([$oeuvreId, $supportKey]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return true|int|string
     */
    public function add(int $oeuvreId, string $ean, string $supportKey, string $label = '', string $source = 'manual'): bool|int|string
    {
        if (!self::tableExists()) {
            return 'Les codes EAN catalogue ne sont pas disponibles (migration en attente).';
        }
        if ($oeuvreId <= 0) {
            return 'Œuvre invalide.';
        }

        $ean = self::normalizeEan($ean);
        if ($ean === '') {
            return 'Le code EAN est obligatoire.';
        }
        if (strlen($ean) < 8 || strlen($ean) > 14) {
            return 'Le code EAN doit contenir entre 8 et 14 chiffres.';
        }

        $supportKey = SupportPhysique::normalize($supportKey);
        $label = trim($label);

        if ((new OeuvreRepository())->findById($oeuvreId) === null) {
            return 'Œuvre introuvable.';
        }

        $existingEan = $this->findByEan($ean);
        if ($existingEan !== null && (int) ($existingEan['oeuvre_id'] ?? 0) !== $oeuvreId) {
            return 'Ce code EAN est déjà utilisé par une autre œuvre du catalogue.';
        }

        $existingSupport = $this->findForOeuvreAndSupport($oeuvreId, $supportKey);
        if ($existingSupport !== null) {
            return 'Un EAN existe déjà pour ce support sur cette œuvre.';
        }

        $this->db->prepare(
            'INSERT INTO oeuvre_eans (oeuvre_id, ean, support_physique, label, source, created_at)
             VALUES (?, ?, ?, ?, ?, datetime(\'now\'))'
        )->execute([$oeuvreId, $ean, $supportKey, $label, $source]);

        return (int) $this->db->lastInsertId();
    }

    /** @return true|string */
    public function delete(int $id, int $oeuvreId): bool|string
    {
        if ($id <= 0 || $oeuvreId <= 0) {
            return 'Entrée invalide.';
        }
        $stmt = $this->db->prepare('DELETE FROM oeuvre_eans WHERE id = ? AND oeuvre_id = ?');
        $stmt->execute([$id, $oeuvreId]);

        return $stmt->rowCount() > 0 ? true : 'Code EAN introuvable.';
    }

    /** Chiffres uniquement (supprime espaces, tirets, séparateurs Unicode, etc.). */
    public static function normalizeEan(string $ean): string
    {
        $ean = preg_replace('/\p{Z}+/u', '', $ean) ?? $ean;

        return preg_replace('/\D+/', '', $ean) ?? '';
    }
}
