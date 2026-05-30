<?php
/**
 * Demandes de changement d’adresse e-mail (confirmation sur la nouvelle adresse).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class EmailChangeRepository
{
    public const TTL_SECONDS = 3600;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'email_change_requests' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function purgeExpired(): void
    {
        if (!self::tableExists()) {
            return;
        }

        $this->db->exec(
            'DELETE FROM email_change_requests WHERE expires_at <= datetime(\'now\')'
        );
    }

    public function deleteForUser(int $userId): void
    {
        if ($userId <= 0 || !self::tableExists()) {
            return;
        }

        $this->db->prepare('DELETE FROM email_change_requests WHERE user_id = ?')->execute([$userId]);
    }

    /**
     * @return true|string
     */
    public function create(int $userId, string $oldEmail, string $newEmail): bool|string
    {
        if (!self::tableExists() || $userId <= 0) {
            return 'Fonction indisponible. Appliquez les migrations.';
        }

        $oldEmail = mb_strtolower(trim($oldEmail), 'UTF-8');
        $newEmail = mb_strtolower(trim($newEmail), 'UTF-8');
        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide.';
        }

        $this->purgeExpired();
        $this->deleteForUser($userId);

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = gmdate('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        $this->db->prepare(
            'INSERT INTO email_change_requests (user_id, new_email, old_email, token_hash, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $newEmail, $oldEmail, $hash, $expires]);

        return $plain;
    }

    public function findUserIdByToken(string $plainToken): ?int
    {
        if (!self::tableExists()) {
            return null;
        }

        $this->purgeExpired();
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare(
            'SELECT user_id FROM email_change_requests
             WHERE token_hash = ? AND expires_at > datetime(\'now\')
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int) $userId : null;
    }

    /** @return array{user_id: int, new_email: string, old_email: string}|null */
    public function findRowByToken(string $plainToken): ?array
    {
        if (!self::tableExists()) {
            return null;
        }

        $this->purgeExpired();
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);
        $stmt = $this->db->prepare(
            'SELECT user_id, new_email, old_email FROM email_change_requests
             WHERE token_hash = ? AND expires_at > datetime(\'now\')
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function consume(int $userId): bool
    {
        if ($userId <= 0 || !self::tableExists()) {
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM email_change_requests WHERE user_id = ?');
        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;
    }
}
