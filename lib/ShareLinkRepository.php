<?php
/**
 * Liens de partage lecture seule (share_links).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class ShareLinkRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'share_links' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /** @return array<string, mixed>|null */
    public function findByTokenHash(string $tokenHash): ?array
    {
        if ($tokenHash === '' || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM share_links WHERE token_hash = ? LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        if ($id <= 0 || $userId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM share_links WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function countActiveForUser(int $userId): int
    {
        if ($userId <= 0 || !self::tableExists()) {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM share_links
             WHERE user_id = ? AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at >= datetime('now'))"
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        if ($userId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM share_links
             WHERE user_id = ? AND revoked_at IS NULL
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll() ?: [];
    }

    public function insert(
        string $tokenHash,
        string $scope,
        int $userId,
        ?int $foyerId,
        string $label,
        ?string $expiresAt
    ): int {
        $this->db->prepare(
            'INSERT INTO share_links (token_hash, scope, user_id, foyer_id, label, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))'
        )->execute([
            $tokenHash,
            ShareLinkScope::normalize($scope),
            $userId,
            $foyerId,
            trim($label),
            $expiresAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function revoke(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE share_links SET revoked_at = datetime('now')
             WHERE id = ? AND user_id = ? AND revoked_at IS NULL"
        );
        $stmt->execute([$id, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function recordAccess(int $id): void
    {
        $this->db->prepare(
            "UPDATE share_links
             SET access_count = access_count + 1, last_access_at = datetime('now')
             WHERE id = ?"
        )->execute([$id]);
    }

    public function isActive(array $link): bool
    {
        if ((string) ($link['revoked_at'] ?? '') !== '') {
            return false;
        }
        $expires = (string) ($link['expires_at'] ?? '');
        if ($expires === '') {
            return true;
        }

        return strtotime($expires) >= time();
    }
}
