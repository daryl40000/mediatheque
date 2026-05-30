<?php
/**
 * Journal des actions administrateur sur le catalogue partagé.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogAuditLog
{
    public const ACTION_DELETE = 'delete_oeuvre';
    public const ACTION_MERGE = 'merge_oeuvres';
    public const ACTION_PURGE_POSTERS = 'purge_orphan_posters';
    public const ACTION_DB_EXPORT = 'export_database';
    public const ACTION_DB_RESTORE = 'restore_database';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(PDO $db): bool
    {
        $stmt = $db->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'catalog_admin_audit' LIMIT 1"
        );

        return (bool) $stmt->fetchColumn();
    }

    public function log(int $userId, string $action, ?int $oeuvreId, string $details = ''): void
    {
        if ($userId <= 0 || $action === '' || !self::tableExists($this->db)) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO catalog_admin_audit (user_id, action, oeuvre_id, details, created_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))'
        )->execute([
            $userId,
            $action,
            $oeuvreId !== null && $oeuvreId > 0 ? $oeuvreId : null,
            trim($details),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $limit = 30): array
    {
        if (!self::tableExists($this->db)) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT a.id, a.user_id, a.action, a.oeuvre_id, a.details, a.created_at,
                    u.nom AS user_nom
             FROM catalog_admin_audit a
             LEFT JOIN utilisateurs u ON u.id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_DELETE => 'Suppression',
            self::ACTION_MERGE => 'Fusion de doublons',
            self::ACTION_PURGE_POSTERS => 'Nettoyage affiches',
            self::ACTION_DB_EXPORT => 'Export base SQLite',
            self::ACTION_DB_RESTORE => 'Restauration base SQLite',
            default => $action,
        };
    }
}
