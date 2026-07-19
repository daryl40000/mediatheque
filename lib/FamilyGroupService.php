<?php
/**
 * Groupes « famille » : création, invitations, membres (phase 6).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class FamilyGroupService
{
    public const KIND_FAMILLE = 'famille';
    public const ROLE_FOUNDER = 'founder';
    public const ROLE_MEMBER = 'member';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function isAvailable(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'group_members' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    public function userHasFamilyGroup(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (!self::isAvailable()) {
            return (new FoyerRepository())->currentFoyerIdForUser($userId) > 0;
        }

        $group = $this->findGroupForUser($userId);
        if ($group === null) {
            return false;
        }

        // Un foyer personnel solo (« Mon foyer ») n’est pas un groupe famille partagé.
        return !$this->isSoloPersonalFoyer($group, $userId);
    }

    /**
     * Foyer auto créé pour un compte seul (1 membre), distinct d’un vrai groupe famille.
     *
     * @param array<string, mixed> $group
     */
    private function isSoloPersonalFoyer(array $group, int $userId): bool
    {
        $foyerId = (int) ($group['id'] ?? 0);
        if ($foyerId <= 0 || $userId <= 0) {
            return false;
        }

        $members = $this->listMembers($foyerId);
        if (count($members) !== 1 || (int) ($members[0]['id'] ?? 0) !== $userId) {
            return false;
        }

        $nom = trim((string) ($group['nom'] ?? ''));
        $kind = trim((string) ($group['kind'] ?? ''));

        return $nom === FoyerRepository::PERSONAL_DEFAULT_NAME
            || $nom === FoyerRepository::DEFAULT_NAME
            || $kind === 'personnel';
    }

    /** @return array<string, mixed>|null */
    public function findGroupForUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        if (self::isAvailable()) {
            $stmt = $this->db->prepare(
                'SELECT f.id, f.nom, f.kind, f.created_by_user_id, f.created_at, gm.role
                 FROM group_members gm
                 INNER JOIN foyers f ON f.id = gm.foyer_id
                 WHERE gm.user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();

            return $row !== false ? $row : null;
        }

        return (new FoyerRepository())->findForUser($userId);
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $foyerId): array
    {
        if ($foyerId <= 0) {
            return [];
        }
        if (!self::isAvailable()) {
            return (new FoyerRepository())->listMembers($foyerId);
        }
        $stmt = $this->db->prepare(
            'SELECT u.id, u.nom, u.prenom, u.pseudo, u.email, u.role AS account_role,
                    gm.role AS group_role, gm.joined_at
             FROM group_members gm
             INNER JOIN utilisateurs u ON u.id = gm.user_id
             WHERE gm.foyer_id = ?
             ORDER BY gm.role DESC, u.nom COLLATE FRENCH_NOCASE'
        );
        $stmt->execute([$foyerId]);

        return $stmt->fetchAll();
    }

    /**
     * @return int|string ID du groupe ou message d’erreur
     */
    public function createGroup(int $founderId, string $nom): int|string
    {
        if (!self::isAvailable()) {
            return 'Les groupes famille ne sont pas disponibles.';
        }
        if ($founderId <= 0) {
            return 'Compte invalide.';
        }
        if ($this->userHasFamilyGroup($founderId)) {
            return 'Vous appartenez déjà à un groupe famille. Quittez-le avant d’en créer un autre.';
        }

        $nom = trim($nom);
        if ($nom === '') {
            return 'Le nom du groupe est obligatoire.';
        }

        // Foyer personnel solo → le promouvoir en groupe famille nommé (évite un 2ᵉ foyer).
        $existing = $this->findGroupForUser($founderId);
        if ($existing !== null && $this->isSoloPersonalFoyer($existing, $founderId)) {
            $foyerId = (int) ($existing['id'] ?? 0);
            $manageTransaction = !$this->db->inTransaction();
            if ($manageTransaction) {
                $this->db->beginTransaction();
            }
            try {
                if (FoyerRepository::tableExists($this->db) && $this->foyerHasKindColumn()) {
                    $this->db->prepare(
                        'UPDATE foyers SET nom = ?, kind = ?, created_by_user_id = COALESCE(created_by_user_id, ?)
                         WHERE id = ?'
                    )->execute([$nom, self::KIND_FAMILLE, $founderId, $foyerId]);
                } else {
                    $this->db->prepare('UPDATE foyers SET nom = ? WHERE id = ?')
                        ->execute([$nom, $foyerId]);
                }
                $this->db->prepare(
                    'UPDATE group_members SET role = ? WHERE foyer_id = ? AND user_id = ?'
                )->execute([self::ROLE_FOUNDER, $foyerId, $founderId]);
                if ($manageTransaction) {
                    $this->db->commit();
                }

                return $foyerId;
            } catch (\Throwable $e) {
                if ($manageTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }

        // Cette méthode peut être appelée dans une transaction existante
        // (ex. création du premier compte admin). SQLite/PDO ne supporte pas
        // les transactions imbriquées, donc on ne démarre une transaction
        // que si on n'en a pas déjà une.
        $manageTransaction = !$this->db->inTransaction();
        if ($manageTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare(
                'INSERT INTO foyers (nom, kind, created_by_user_id, created_at)
                 VALUES (?, ?, ?, datetime(\'now\'))'
            )->execute([$nom, self::KIND_FAMILLE, $founderId]);
            $foyerId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                'INSERT INTO group_members (foyer_id, user_id, role, joined_at)
                 VALUES (?, ?, ?, datetime(\'now\'))'
            )->execute([$foyerId, $founderId, self::ROLE_FOUNDER]);

            $this->db->prepare('UPDATE utilisateurs SET foyer_id = ? WHERE id = ?')
                ->execute([$foyerId, $founderId]);

            if ($manageTransaction) {
                $this->db->commit();
            }

            return $foyerId;
        } catch (\Throwable $e) {
            if ($manageTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function foyerHasKindColumn(): bool
    {
        $stmt = $this->db->query(
            "SELECT 1 FROM pragma_table_info('foyers') WHERE name = 'kind' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @return int|string ID invitation ou message
     */
    public function inviteFriend(int $foyerId, int $inviterId, int $inviteeId): int|string
    {
        if (!self::isAvailable()) {
            return 'Les invitations ne sont pas disponibles.';
        }
        if ($foyerId <= 0 || $inviterId <= 0 || $inviteeId <= 0) {
            return 'Paramètres invalides.';
        }
        if ($inviterId === $inviteeId) {
            return 'Vous ne pouvez pas vous inviter vous-même.';
        }
        if (!$this->isMember($foyerId, $inviterId)) {
            return 'Vous n’êtes pas membre de ce groupe.';
        }
        $friendRepo = new FriendshipRepository();
        if ($friendRepo->isBlockedBetween($inviterId, $inviteeId)) {
            return 'Impossible d’inviter cette personne.';
        }
        if (!$friendRepo->areFriends($inviterId, $inviteeId)) {
            return 'Vous devez être amis pour inviter cette personne.';
        }
        if ($this->userHasFamilyGroup($inviteeId)) {
            return 'Cette personne appartient déjà à un groupe famille.';
        }
        if ($this->hasPendingInvitation($foyerId, $inviteeId)) {
            return 'Une invitation est déjà en attente pour cette personne.';
        }

        $this->db->prepare(
            'INSERT INTO group_invitations (foyer_id, user_id, invited_by, status, created_at)
             VALUES (?, ?, ?, \'pending\', datetime(\'now\'))'
        )->execute([$foyerId, $inviteeId, $inviterId]);

        return (int) $this->db->lastInsertId();
    }

    /** @return true|string */
    public function acceptInvitation(int $invitationId, int $userId): bool|string
    {
        $inv = $this->findInvitation($invitationId);
        if ($inv === null) {
            return 'Invitation introuvable.';
        }
        if ((int) ($inv['user_id'] ?? 0) !== $userId) {
            return 'Cette invitation ne vous concerne pas.';
        }
        if ((string) ($inv['status'] ?? '') !== 'pending') {
            return 'Cette invitation n’est plus valable.';
        }

        $existing = $this->findGroupForUser($userId);
        if ($existing !== null && !$this->isSoloPersonalFoyer($existing, $userId)) {
            return 'Vous appartenez déjà à un groupe famille.';
        }

        $foyerId = (int) ($inv['foyer_id'] ?? 0);
        $invitedBy = (int) ($inv['invited_by'] ?? 0);
        $oldPersonalFoyerId = ($existing !== null && $this->isSoloPersonalFoyer($existing, $userId))
            ? (int) ($existing['id'] ?? 0)
            : 0;

        $this->db->beginTransaction();
        try {
            if ($oldPersonalFoyerId > 0) {
                // Quitter le foyer perso : déplacer collection, détacher le compte, puis supprimer le foyer vide.
                $this->db->prepare(
                    'UPDATE bibliotheque SET foyer_id = ? WHERE foyer_id = ? AND statut = ?'
                )->execute([$foyerId, $oldPersonalFoyerId, LibraryStatut::COLLECTION]);
                $this->db->prepare('UPDATE utilisateurs SET foyer_id = ? WHERE id = ? AND foyer_id = ?')
                    ->execute([$foyerId, $userId, $oldPersonalFoyerId]);
                $this->db->prepare('DELETE FROM group_invitations WHERE foyer_id = ?')
                    ->execute([$oldPersonalFoyerId]);
                $this->db->prepare('DELETE FROM group_members WHERE foyer_id = ?')
                    ->execute([$oldPersonalFoyerId]);
                $this->db->prepare('DELETE FROM foyers WHERE id = ?')->execute([$oldPersonalFoyerId]);
            }

            $this->db->prepare(
                'UPDATE group_invitations SET status = \'accepted\', responded_at = datetime(\'now\')
                 WHERE id = ?'
            )->execute([$invitationId]);

            $this->db->prepare(
                'INSERT INTO group_members (foyer_id, user_id, role, invited_by, joined_at)
                 VALUES (?, ?, ?, ?, datetime(\'now\'))'
            )->execute([$foyerId, $userId, self::ROLE_MEMBER, $invitedBy > 0 ? $invitedBy : null]);

            $this->db->prepare('UPDATE utilisateurs SET foyer_id = ? WHERE id = ?')
                ->execute([$foyerId, $userId]);

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return true|string */
    public function declineInvitation(int $invitationId, int $userId): bool|string
    {
        $inv = $this->findInvitation($invitationId);
        if ($inv === null) {
            return 'Invitation introuvable.';
        }
        if ((int) ($inv['user_id'] ?? 0) !== $userId) {
            return 'Cette invitation ne vous concerne pas.';
        }
        if ((string) ($inv['status'] ?? '') !== 'pending') {
            return 'Cette invitation n’est plus valable.';
        }

        $this->db->prepare(
            'UPDATE group_invitations SET status = \'declined\', responded_at = datetime(\'now\')
             WHERE id = ?'
        )->execute([$invitationId]);

        return true;
    }

    /** @return true|string */
    public function leaveGroup(int $userId): bool|string
    {
        if (!self::isAvailable() || $userId <= 0) {
            return 'Compte invalide.';
        }

        $stmt = $this->db->prepare(
            'SELECT foyer_id, role FROM group_members WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $membership = $stmt->fetch();
        if ($membership === false) {
            $foyerId = (new FoyerRepository())->currentFoyerIdForUser($userId);
            if ($foyerId <= 0) {
                return 'Vous n’appartenez à aucun groupe.';
            }
            $this->db->prepare('UPDATE utilisateurs SET foyer_id = NULL WHERE id = ?')
                ->execute([$userId]);

            return true;
        }

        $foyerId = (int) ($membership['foyer_id'] ?? 0);
        $role = (string) ($membership['role'] ?? '');

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM group_members WHERE foyer_id = ? AND user_id = ?')
                ->execute([$foyerId, $userId]);
            $this->db->prepare('UPDATE utilisateurs SET foyer_id = NULL WHERE id = ?')
                ->execute([$userId]);

            if ($role === self::ROLE_FOUNDER) {
                $this->transferFounderIfNeeded($foyerId);
            }

            $this->db->commit();

            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPendingInvitationsForUser(int $userId): array
    {
        if ($userId <= 0 || !self::isAvailable()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT gi.id, gi.foyer_id, gi.created_at, gi.invited_by,
                    f.nom AS foyer_nom,
                    u.nom AS inviter_nom, u.prenom AS inviter_prenom, u.pseudo AS inviter_pseudo
             FROM group_invitations gi
             INNER JOIN foyers f ON f.id = gi.foyer_id
             INNER JOIN utilisateurs u ON u.id = gi.invited_by
             WHERE gi.user_id = ? AND gi.status = \'pending\'
             ORDER BY gi.created_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function isMember(int $foyerId, int $userId): bool
    {
        if ($foyerId <= 0 || $userId <= 0) {
            return false;
        }
        if (!self::isAvailable()) {
            return (new FoyerRepository())->currentFoyerIdForUser($userId) === $foyerId;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM group_members WHERE foyer_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$foyerId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Les deux utilisateurs appartiennent au même groupe famille (ou même foyer legacy). */
    public function shareSameGroup(int $userIdA, int $userIdB): bool
    {
        if ($userIdA <= 0 || $userIdB <= 0 || $userIdA === $userIdB) {
            return $userIdA > 0 && $userIdA === $userIdB;
        }
        if (!self::isAvailable()) {
            $foyerA = (new FoyerRepository())->currentFoyerIdForUser($userIdA);
            $foyerB = (new FoyerRepository())->currentFoyerIdForUser($userIdB);

            return $foyerA > 0 && $foyerA === $foyerB;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM group_members gm1
             INNER JOIN group_members gm2 ON gm2.foyer_id = gm1.foyer_id
             WHERE gm1.user_id = ? AND gm2.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userIdA, $userIdB]);

        return (bool) $stmt->fetchColumn();
    }

    private function hasPendingInvitation(int $foyerId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM group_invitations
             WHERE foyer_id = ? AND user_id = ? AND status = \'pending\' LIMIT 1'
        );
        $stmt->execute([$foyerId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function transferFounderIfNeeded(int $foyerId): void
    {
        $stmt = $this->db->prepare(
            'SELECT user_id FROM group_members
             WHERE foyer_id = ? AND role = ?
             ORDER BY joined_at ASC LIMIT 1'
        );
        $stmt->execute([$foyerId, self::ROLE_MEMBER]);
        $next = $stmt->fetch();
        if ($next === false) {
            return;
        }
        $newFounderId = (int) ($next['user_id'] ?? 0);
        if ($newFounderId <= 0) {
            return;
        }
        $this->db->prepare(
            'UPDATE group_members SET role = ? WHERE foyer_id = ? AND user_id = ?'
        )->execute([self::ROLE_FOUNDER, $foyerId, $newFounderId]);
    }

    /** @return array<string, mixed>|null */
    private function findInvitation(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, foyer_id, user_id, invited_by, status, created_at
             FROM group_invitations WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}
