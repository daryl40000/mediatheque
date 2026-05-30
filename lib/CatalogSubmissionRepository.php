<?php
/**
 * Accès base aux propositions catalogue (catalogue_soumissions).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class CatalogSubmissionRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'catalogue_soumissions' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function insert(int $userId, string $payloadJson, string $userNote = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO catalogue_soumissions (user_id, payload_json, user_note, status, created_at)
             VALUES (?, ?, ?, ?, datetime(\'now\'))'
        );
        $stmt->execute([
            $userId,
            $payloadJson,
            trim($userNote),
            self::STATUS_PENDING,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM catalogue_soumissions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM catalogue_soumissions WHERE user_id = ?';
        $params = [$userId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(): array
    {
        $stmt = $this->db->query(
            "SELECT s.*, u.nom AS submitter_nom, u.prenom AS submitter_prenom,
                    u.pseudo AS submitter_pseudo, u.email AS submitter_email
             FROM catalogue_soumissions s
             INNER JOIN utilisateurs u ON u.id = s.user_id
             WHERE s.status = 'pending'
             ORDER BY s.created_at ASC, s.id ASC"
        );

        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function countPending(): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        return (int) $this->db->query(
            "SELECT COUNT(*) FROM catalogue_soumissions WHERE status = 'pending'"
        )->fetchColumn();
    }

    public function updatePayload(int $id, string $payloadJson): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE catalogue_soumissions SET payload_json = ?
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([$payloadJson, $id]);

        return $stmt->rowCount() > 0;
    }

    public function markApproved(
        int $id,
        int $oeuvreId,
        int $reviewedBy,
        string $reviewNote = ''
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE catalogue_soumissions
             SET status = ?, resulting_oeuvre_id = ?, review_note = ?,
                 reviewed_by = ?, reviewed_at = datetime('now')
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([
            self::STATUS_APPROVED,
            $oeuvreId,
            trim($reviewNote),
            $reviewedBy,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markRejected(int $id, int $reviewedBy, string $reviewNote = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE catalogue_soumissions
             SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = datetime('now')
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([
            self::STATUS_REJECTED,
            trim($reviewNote),
            $reviewedBy,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function hasPendingForUser(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM catalogue_soumissions WHERE user_id = ? AND status = 'pending' LIMIT 1"
        );
        $stmt->execute([$userId]);

        return $stmt->fetchColumn() !== false;
    }
}
