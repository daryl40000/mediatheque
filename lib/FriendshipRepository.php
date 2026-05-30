<?php
/**
 * Demandes et liens d’amitié entre utilisateurs.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FriendshipRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_BLOCKED = 'blocked';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'friendships' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public static function isAvailable(): bool
    {
        return self::tableExists();
    }

    /** @return array<string, mixed>|null */
    public function findBetween(int $userIdA, int $userIdB): ?array
    {
        if ($userIdA <= 0 || $userIdB <= 0 || $userIdA === $userIdB) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, requester_id, addressee_id, status, created_at, responded_at
             FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?)
                OR (requester_id = ? AND addressee_id = ?)
             LIMIT 1'
        );
        $stmt->execute([$userIdA, $userIdB, $userIdB, $userIdA]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function areFriends(int $userIdA, int $userIdB): bool
    {
        $row = $this->findBetween($userIdA, $userIdB);

        return $row !== null && (string) ($row['status'] ?? '') === self::STATUS_ACCEPTED;
    }

    /** Blocage actif entre deux comptes (dans un sens ou l’autre). */
    public function isBlockedBetween(int $userIdA, int $userIdB): bool
    {
        $row = $this->findBetween($userIdA, $userIdB);

        return $row !== null && (string) ($row['status'] ?? '') === self::STATUS_BLOCKED;
    }

    /**
     * @return true|string
     */
    public function blockUser(int $blockerId, int $blockedId): bool|string
    {
        if (!self::isAvailable()) {
            return 'Les demandes d’ami ne sont pas disponibles.';
        }
        if ($blockerId <= 0 || $blockedId <= 0) {
            return 'Compte invalide.';
        }
        if ($blockerId === $blockedId) {
            return 'Action impossible.';
        }

        $blocked = (new UtilisateurRepository())->findById($blockedId);
        if ($blocked === null || (int) ($blocked['actif'] ?? 0) !== 1) {
            return 'Utilisateur introuvable.';
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'DELETE FROM friendships
                 WHERE (requester_id = ? AND addressee_id = ?)
                    OR (requester_id = ? AND addressee_id = ?)'
            )->execute([$blockerId, $blockedId, $blockedId, $blockerId]);

            $this->db->prepare(
                'INSERT INTO friendships (requester_id, addressee_id, status, created_at, responded_at)
                 VALUES (?, ?, ?, datetime(\'now\'), datetime(\'now\'))'
            )->execute([$blockerId, $blockedId, self::STATUS_BLOCKED]);

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return true|string
     */
    public function unblockUser(int $blockerId, int $blockedId): bool|string
    {
        if (!self::isAvailable()) {
            return 'Les demandes d’ami ne sont pas disponibles.';
        }
        if ($blockerId <= 0 || $blockedId <= 0) {
            return 'Compte invalide.';
        }

        $stmt = $this->db->prepare(
            'DELETE FROM friendships
             WHERE requester_id = ? AND addressee_id = ? AND status = ?'
        );
        $stmt->execute([$blockerId, $blockedId, self::STATUS_BLOCKED]);

        return $stmt->rowCount() > 0
            ? true
            : 'Aucun blocage à lever pour cet utilisateur.';
    }

    /** @return list<array<string, mixed>> */
    public function listBlockedUsers(int $blockerId): array
    {
        if ($blockerId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nom, u.prenom, u.pseudo, u.ville, f.id AS friendship_id
             FROM friendships f
             INNER JOIN utilisateurs u ON u.id = f.addressee_id
             WHERE f.requester_id = ? AND f.status = ? AND u.actif = 1
             ORDER BY u.pseudo COLLATE FRENCH_NOCASE, u.nom COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$blockerId, self::STATUS_BLOCKED]);

        return $stmt->fetchAll();
    }

    /**
     * @return true|int|string ID de la demande ou message d’erreur
     */
    public function sendRequest(int $requesterId, int $addresseeId): bool|int|string
    {
        if (!self::isAvailable()) {
            return 'Les demandes d’ami ne sont pas disponibles.';
        }
        if ($requesterId <= 0 || $addresseeId <= 0) {
            return 'Compte invalide.';
        }
        if ($requesterId === $addresseeId) {
            return 'Vous ne pouvez pas vous ajouter vous-même.';
        }

        if (!SocialRateLimit::allowFriendRequest($requesterId)) {
            return SocialRateLimit::friendRequestLimitMessage($requesterId);
        }

        if ($this->isBlockedBetween($requesterId, $addresseeId)) {
            return 'Impossible d’envoyer une demande à cet utilisateur.';
        }

        $addressee = (new UtilisateurRepository())->findById($addresseeId);
        if ($addressee === null || (int) ($addressee['actif'] ?? 0) !== 1) {
            return 'Utilisateur introuvable.';
        }

        $existing = $this->findBetween($requesterId, $addresseeId);
        if ($existing !== null) {
            $status = (string) ($existing['status'] ?? '');
            if ($status === self::STATUS_ACCEPTED) {
                return 'Vous êtes déjà amis.';
            }
            if ($status === self::STATUS_BLOCKED) {
                return 'Impossible d’envoyer une demande à cet utilisateur.';
            }
            $reqId = (int) ($existing['requester_id'] ?? 0);
            $addrId = (int) ($existing['addressee_id'] ?? 0);
            if ($status === self::STATUS_PENDING) {
                if ($reqId === $addresseeId && $addrId === $requesterId) {
                    $accept = $this->acceptRequest((int) ($existing['id'] ?? 0), $requesterId);
                    if ($accept === true) {
                        return (int) ($existing['id'] ?? 0);
                    }

                    return $accept;
                }
                if ($reqId === $requesterId) {
                    return 'Une demande est déjà en attente.';
                }
            }
        }

        $this->db->prepare(
            'INSERT INTO friendships (requester_id, addressee_id, status, created_at)
             VALUES (?, ?, ?, datetime(\'now\'))'
        )->execute([$requesterId, $addresseeId, self::STATUS_PENDING]);

        SocialRateLimit::recordFriendRequest($requesterId);

        return (int) $this->db->lastInsertId();
    }

    /** @return true|string */
    public function acceptRequest(int $friendshipId, int $actingUserId): bool|string
    {
        $row = $this->findById($friendshipId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((int) ($row['addressee_id'] ?? 0) !== $actingUserId) {
            return 'Vous ne pouvez pas accepter cette demande.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return 'Cette demande n’est plus en attente.';
        }

        $requesterId = (int) ($row['requester_id'] ?? 0);
        if ($requesterId > 0 && $this->isBlockedBetween($actingUserId, $requesterId)) {
            return 'Impossible d’accepter cette demande.';
        }

        $this->db->prepare(
            'UPDATE friendships SET status = ?, responded_at = datetime(\'now\') WHERE id = ?'
        )->execute([self::STATUS_ACCEPTED, $friendshipId]);

        return true;
    }

    /** @return true|string */
    public function rejectRequest(int $friendshipId, int $actingUserId): bool|string
    {
        $row = $this->findById($friendshipId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((int) ($row['addressee_id'] ?? 0) !== $actingUserId) {
            return 'Vous ne pouvez pas refuser cette demande.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return 'Cette demande n’est plus en attente.';
        }

        $this->db->prepare('DELETE FROM friendships WHERE id = ?')->execute([$friendshipId]);

        return true;
    }

    /** @return true|string */
    public function cancelRequest(int $friendshipId, int $actingUserId): bool|string
    {
        $row = $this->findById($friendshipId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((int) ($row['requester_id'] ?? 0) !== $actingUserId) {
            return 'Vous ne pouvez pas annuler cette demande.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return 'Cette demande n’est plus en attente.';
        }

        $this->db->prepare('DELETE FROM friendships WHERE id = ?')->execute([$friendshipId]);

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function listFriends(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nom, u.prenom, u.pseudo, u.ville, f.id AS friendship_id
             FROM friendships f
             INNER JOIN utilisateurs u ON u.id = CASE
                 WHEN f.requester_id = ? THEN f.addressee_id
                 ELSE f.requester_id
             END
             WHERE f.status = ?
               AND (f.requester_id = ? OR f.addressee_id = ?)
               AND u.actif = 1
             ORDER BY u.pseudo COLLATE FRENCH_NOCASE, u.nom COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([
            $userId,
            self::STATUS_ACCEPTED,
            $userId,
            $userId,
        ]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listPendingReceived(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT f.id, f.requester_id, f.created_at,
                    u.nom, u.prenom, u.pseudo, u.ville
             FROM friendships f
             INNER JOIN utilisateurs u ON u.id = f.requester_id
             WHERE f.addressee_id = ? AND f.status = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$userId, self::STATUS_PENDING]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function listPendingSent(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT f.id, f.addressee_id, f.created_at,
                    u.nom, u.prenom, u.pseudo, u.ville
             FROM friendships f
             INNER JOIN utilisateurs u ON u.id = f.addressee_id
             WHERE f.requester_id = ? AND f.status = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$userId, self::STATUS_PENDING]);

        return $stmt->fetchAll();
    }

    /**
     * Statut pour l’affichage recherche : none|pending_sent|pending_received|friends|blocked
     */
    public function relationStatus(int $viewerId, int $otherUserId): string
    {
        $row = $this->findBetween($viewerId, $otherUserId);
        if ($row === null) {
            return 'none';
        }
        $status = (string) ($row['status'] ?? '');
        if ($status === self::STATUS_ACCEPTED) {
            return 'friends';
        }
        if ($status === self::STATUS_BLOCKED) {
            return (int) ($row['requester_id'] ?? 0) === $viewerId ? 'blocked_by_me' : 'blocked_me';
        }
        if ($status === self::STATUS_PENDING) {
            return (int) ($row['requester_id'] ?? 0) === $viewerId ? 'pending_sent' : 'pending_received';
        }

        return 'none';
    }

    /** @return array<string, mixed>|null */
    private function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, requester_id, addressee_id, status, created_at, responded_at
             FROM friendships WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}
