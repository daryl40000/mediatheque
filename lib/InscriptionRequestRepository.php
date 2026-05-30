<?php
/**
 * Demandes d’inscription (confirmation e-mail, approbation admin).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;
use PDOException;

final class InscriptionRequestRepository
{
    public const STATUS_PENDING_EMAIL = 'pending_email';
    public const STATUS_PENDING_ADMIN = 'pending_admin';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /** Validité du lien de confirmation (secondes). */
    /** 24 h (réduit l’exposition des données de demande en base). */
    public const CONFIRM_TTL_SECONDS = 86400;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'inscription_requests' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function hasActiveRequestForEmail(string $email): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT 1 FROM inscription_requests
             WHERE LOWER(TRIM(email)) = ?
               AND status IN ('pending_email', 'pending_admin')
             LIMIT 1"
        );
        $stmt->execute([$email]);

        return $stmt->fetchColumn() !== false;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM inscription_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function findByConfirmToken(string $plainToken): ?array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare(
            "SELECT * FROM inscription_requests
             WHERE confirm_token_hash = ?
               AND status = 'pending_email'
               AND confirm_expires_at > datetime('now')
             LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPendingAdmin(): array
    {
        return $this->db->query(
            "SELECT * FROM inscription_requests
             WHERE status = 'pending_admin'
             ORDER BY email_confirmed_at ASC, id ASC"
        )->fetchAll();
    }

    public function countPendingAdmin(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM inscription_requests WHERE status = 'pending_admin'"
        )->fetchColumn();
    }

    /**
     * @return true|string
     */
    public function insertPendingEmail(
        string $nom,
        string $prenom,
        string $pseudo,
        string $email,
        string $passwordHash,
        string $confirmTokenHash,
        string $confirmExpiresAt
    ): bool|string {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return 'Adresse e-mail invalide.';
        }

        try {
            $this->db->prepare(
                'INSERT INTO inscription_requests (
                    email, nom, prenom, pseudo, password_hash, status,
                    confirm_token_hash, confirm_expires_at, created_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
            )->execute([
                $email,
                trim($nom),
                trim($prenom),
                UserProfile::sanitizePseudo($pseudo),
                $passwordHash,
                self::STATUS_PENDING_EMAIL,
                $confirmTokenHash,
                $confirmExpiresAt,
            ]);
        } catch (PDOException $e) {
            if (self::isUniqueConstraintViolation($e)) {
                return 'Une demande est déjà en cours pour cette adresse e-mail.';
            }

            error_log('Moncine inscription_requests INSERT: ' . $e->getMessage());

            return 'Impossible d’enregistrer la demande. Réessayez dans quelques minutes.';
        }

        return true;
    }

    private static function isUniqueConstraintViolation(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique constraint failed');
    }

    public function markEmailConfirmed(int $id, string $newStatus): bool
    {
        if ($id <= 0 || !in_array($newStatus, [self::STATUS_PENDING_ADMIN, self::STATUS_APPROVED], true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE inscription_requests
             SET status = ?, email_confirmed_at = datetime('now'), updated_at = datetime('now'),
                 confirm_token_hash = '', confirm_expires_at = datetime('now')
             WHERE id = ? AND status = 'pending_email'"
        );
        $stmt->execute([$newStatus, $id]);

        return $stmt->rowCount() > 0;
    }

    public function markApproved(int $id, int $userId, int $reviewedBy, string $reviewNote = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE inscription_requests
             SET status = ?, user_id = ?, reviewed_by = ?, review_note = ?,
                 password_hash = '', updated_at = datetime('now')
             WHERE id = ? AND status = 'pending_admin'"
        );
        $stmt->execute([
            self::STATUS_APPROVED,
            $userId,
            $reviewedBy,
            trim($reviewNote),
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markApprovedDirect(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE inscription_requests
             SET status = ?, user_id = ?, email_confirmed_at = COALESCE(email_confirmed_at, datetime('now')),
                 password_hash = '', confirm_token_hash = '', confirm_expires_at = datetime('now'),
                 updated_at = datetime('now')
             WHERE id = ? AND status = 'pending_email'"
        );
        $stmt->execute([self::STATUS_APPROVED, $userId, $id]);

        return $stmt->rowCount() > 0;
    }

    public function markRejected(int $id, int $reviewedBy, string $reviewNote = ''): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE inscription_requests
             SET status = ?, reviewed_by = ?, review_note = ?, password_hash = '',
                 updated_at = datetime('now')
             WHERE id = ? AND status = 'pending_admin'"
        );
        $stmt->execute([
            self::STATUS_REJECTED,
            $reviewedBy,
            trim($reviewNote),
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function purgeExpiredPendingEmail(): void
    {
        $this->db->exec(
            "DELETE FROM inscription_requests
             WHERE status = 'pending_email' AND confirm_expires_at <= datetime('now')"
        );
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
