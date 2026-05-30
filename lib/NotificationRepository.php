<?php
/**
 * Notifications in-app par utilisateur.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class NotificationRepository
{
    public const KIND_SUBMISSION_NEW = 'catalogue_submission_new';
    public const KIND_SUBMISSION_APPROVED = 'catalogue_submission_approved';
    public const KIND_SUBMISSION_REJECTED = 'catalogue_submission_rejected';
    public const KIND_FRIEND_REQUEST = 'friend_request';
    public const KIND_FRIEND_ACCEPTED = 'friend_accepted';
    public const KIND_GROUP_INVITE = 'group_invite';
    public const KIND_LOAN_REQUEST = 'loan_request';
    public const KIND_LOAN_ACCEPTED = 'loan_request_accepted';
    public const KIND_LOAN_DECLINED = 'loan_request_declined';
    public const KIND_LOAN_LENT = 'loan_lent';
    public const KIND_LOAN_RETURNED = 'loan_returned';
    public const KIND_REGISTRATION_PENDING = 'registration_pending';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'notifications' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function insert(
        int $userId,
        string $kind,
        string $title,
        string $body,
        string $linkUrl,
        ?int $submissionId = null,
        ?int $oeuvreId = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (
                user_id, kind, title, body, link_url,
                related_submission_id, related_oeuvre_id, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
        );
        $stmt->execute([
            $userId,
            $kind,
            trim($title),
            trim($body),
            $linkUrl,
            $submissionId,
            $oeuvreId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function countUnread(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    public function findByIdForUser(int $id, int $userId): ?array
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET read_at = datetime('now')
             WHERE id = ? AND user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "UPDATE notifications SET read_at = datetime('now')
             WHERE user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }
}
