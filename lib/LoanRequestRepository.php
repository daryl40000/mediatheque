<?php
/**
 * Demandes de prêt (phase 8) : un ami sollicite un prêt, le propriétaire accepte (réserve),
 * puis valide le prêt le jour J (création d'une ligne loans).
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LoanRequestRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted'; // réservé
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_LENT = 'lent';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'loan_requests' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * Crée une demande de prêt pour une entrée de collection appartenant à ownerUserId.
     *
     * @return int|string ID de demande ou message d'erreur
     */
    public function requestLoan(int $bibliothequeId, int $requesterUserId, int $ownerUserId, string $note = ''): int|string
    {
        if (!self::tableExists()) {
            return 'Les prêts ne sont pas disponibles (migration en attente).';
        }
        if ($bibliothequeId <= 0 || $requesterUserId <= 0 || $ownerUserId <= 0) {
            return 'Paramètres invalides.';
        }
        if ($requesterUserId === $ownerUserId) {
            return 'Vous ne pouvez pas demander un prêt à vous-même.';
        }

        $friendRepo = new FriendshipRepository();
        if (!FriendshipRepository::isAvailable() || !$friendRepo->areFriends($requesterUserId, $ownerUserId)) {
            return 'Vous devez être amis pour demander un prêt.';
        }
        if ($friendRepo->isBlockedBetween($requesterUserId, $ownerUserId)) {
            return 'Action impossible.';
        }

        // Vérifie que l'entrée existe, est en collection, et appartient bien au propriétaire (user_id sur bibliotheque).
        $stmt = $this->db->prepare(
            'SELECT id, user_id, statut FROM bibliotheque WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$bibliothequeId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return 'Film introuvable.';
        }
        if ((string) ($row['statut'] ?? '') !== LibraryStatut::COLLECTION) {
            return 'Ce film ne fait pas partie de la collection.';
        }
        if ((int) ($row['user_id'] ?? 0) !== $ownerUserId) {
            return 'Ce film n’appartient pas à cet utilisateur.';
        }

        // Refuse si déjà prêté (un prêt en cours existe pour cette entrée).
        if (LoanRepository::tableExists()) {
            $activeLoan = $this->db->prepare(
                'SELECT 1 FROM loans WHERE bibliotheque_id = ? AND returned_at IS NULL LIMIT 1'
            );
            $activeLoan->execute([$bibliothequeId]);
            if ($activeLoan->fetchColumn()) {
                return 'Ce film est déjà prêté.';
            }
        }

        // Refuse si déjà réservé pour quelqu'un (demande acceptée).
        $reserved = $this->db->prepare(
            'SELECT 1 FROM loan_requests WHERE bibliotheque_id = ? AND status = ? LIMIT 1'
        );
        $reserved->execute([$bibliothequeId, self::STATUS_ACCEPTED]);
        if ($reserved->fetchColumn()) {
            return 'Ce film est déjà réservé pour un autre prêt.';
        }

        $note = trim($note);
        if (mb_strlen($note, 'UTF-8') > 240) {
            $note = mb_substr($note, 0, 240, 'UTF-8');
        }

        try {
            $this->db->prepare(
                'INSERT INTO loan_requests (bibliotheque_id, owner_user_id, requester_user_id, status, requested_at, note)
                 VALUES (?, ?, ?, ?, datetime(\'now\'), ?)'
            )->execute([$bibliothequeId, $ownerUserId, $requesterUserId, self::STATUS_PENDING, $note]);
        } catch (\Throwable $e) {
            // Index unique : demande déjà en cours pour cet utilisateur.
            return 'Une demande est déjà en cours pour ce film.';
        }

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listPendingForOwner(int $ownerUserId): array
    {
        if ($ownerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT lr.id AS request_id, lr.bibliotheque_id, lr.status, lr.requested_at, lr.note,
                    u.id AS requester_id, u.nom AS requester_nom, u.prenom AS requester_prenom, u.pseudo AS requester_pseudo,
                    ' . CatalogSchema::selectFilmRow() . '
             FROM loan_requests lr
             INNER JOIN utilisateurs u ON u.id = lr.requester_user_id
             INNER JOIN ' . CatalogSchema::JOIN . '
             WHERE lr.owner_user_id = ?
               AND lr.status = ?
               AND b.id = lr.bibliotheque_id
             ORDER BY lr.requested_at DESC, lr.id DESC'
        );
        $stmt->execute([$ownerUserId, self::STATUS_PENDING]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listReservedForOwner(int $ownerUserId): array
    {
        if ($ownerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT lr.id AS request_id, lr.bibliotheque_id, lr.status, lr.requested_at, lr.responded_at, lr.note,
                    u.id AS requester_id, u.nom AS requester_nom, u.prenom AS requester_prenom, u.pseudo AS requester_pseudo,
                    ' . CatalogSchema::selectFilmRow() . '
             FROM loan_requests lr
             INNER JOIN utilisateurs u ON u.id = lr.requester_user_id
             INNER JOIN ' . CatalogSchema::JOIN . '
             WHERE lr.owner_user_id = ?
               AND lr.status = ?
               AND b.id = lr.bibliotheque_id
             ORDER BY lr.responded_at DESC, lr.id DESC'
        );
        $stmt->execute([$ownerUserId, self::STATUS_ACCEPTED]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Statuts des demandes du viewer pour la collection d'un owner (par bibliotheque_id).
     *
     * @return array<int, array{request_id: int, status: string}>
     */
    public function mapActiveRequestsForViewer(int $ownerUserId, int $viewerUserId): array
    {
        if ($ownerUserId <= 0 || $viewerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, status
             FROM loan_requests
             WHERE owner_user_id = ?
               AND requester_user_id = ?
               AND status IN (?, ?)'
        );
        $stmt->execute([
            $ownerUserId,
            $viewerUserId,
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
        ]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bibId = (int) ($row['bibliotheque_id'] ?? 0);
            if ($bibId <= 0) {
                continue;
            }
            $out[$bibId] = [
                'request_id' => (int) ($row['id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Films réservés (status=accepted) mais pour quelqu'un d'autre.
     *
     * @return array<int, bool> map[bibliotheque_id]=true
     */
    public function mapReservedByOthers(int $ownerUserId, int $viewerUserId): array
    {
        if ($ownerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $sql = 'SELECT bibliotheque_id FROM loan_requests
                WHERE owner_user_id = ?
                  AND status = ?';
        $params = [$ownerUserId, self::STATUS_ACCEPTED];
        if ($viewerUserId > 0) {
            $sql .= ' AND requester_user_id != ?';
            $params[] = $viewerUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bibId = (int) ($row['bibliotheque_id'] ?? 0);
            if ($bibId > 0) {
                $out[$bibId] = true;
            }
        }

        return $out;
    }

    /**
     * @return true|string
     */
    public function acceptRequest(int $requestId, int $ownerUserId): bool|string
    {
        $row = $this->findForOwner($requestId, $ownerUserId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return 'Cette demande n’est plus en attente.';
        }

        $bibliothequeId = (int) ($row['bibliotheque_id'] ?? 0);
        if ($bibliothequeId <= 0) {
            return 'Demande invalide.';
        }

        // Un seul réservé par film à la fois.
        $reserved = $this->db->prepare(
            'SELECT 1 FROM loan_requests WHERE bibliotheque_id = ? AND status = ? LIMIT 1'
        );
        $reserved->execute([$bibliothequeId, self::STATUS_ACCEPTED]);
        if ($reserved->fetchColumn()) {
            return 'Ce film est déjà réservé.';
        }

        $this->db->prepare(
            'UPDATE loan_requests
             SET status = ?, responded_at = datetime(\'now\')
             WHERE id = ? AND owner_user_id = ? AND status = ?'
        )->execute([self::STATUS_ACCEPTED, $requestId, $ownerUserId, self::STATUS_PENDING]);

        return true;
    }

    /**
     * @return true|string
     */
    public function declineRequest(int $requestId, int $ownerUserId): bool|string
    {
        $row = $this->findForOwner($requestId, $ownerUserId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_PENDING) {
            return 'Cette demande n’est plus en attente.';
        }

        $this->db->prepare(
            'UPDATE loan_requests
             SET status = ?, responded_at = datetime(\'now\')
             WHERE id = ? AND owner_user_id = ? AND status = ?'
        )->execute([self::STATUS_DECLINED, $requestId, $ownerUserId, self::STATUS_PENDING]);

        return true;
    }

    /**
     * @return true|string
     */
    public function cancelRequest(int $requestId, int $requesterUserId): bool|string
    {
        $row = $this->findForRequester($requestId, $requesterUserId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_ACCEPTED], true)) {
            return 'Cette demande ne peut plus être annulée.';
        }

        $this->db->prepare(
            'UPDATE loan_requests
             SET status = ?, responded_at = datetime(\'now\')
             WHERE id = ? AND requester_user_id = ? AND status IN (?, ?)'
        )->execute([self::STATUS_CANCELED, $requestId, $requesterUserId, self::STATUS_PENDING, self::STATUS_ACCEPTED]);

        return true;
    }

    /**
     * Appelé après création du prêt (loans). Marque la demande comme "lent" et lie loan_id.
     *
     * @return true|string
     */
    public function markLent(int $requestId, int $ownerUserId, int $loanId): bool|string
    {
        $row = $this->findForOwner($requestId, $ownerUserId);
        if ($row === null) {
            return 'Demande introuvable.';
        }
        if ((string) ($row['status'] ?? '') !== self::STATUS_ACCEPTED) {
            return 'Cette demande n’est pas réservée.';
        }
        if ($loanId <= 0) {
            return 'Prêt invalide.';
        }

        $this->db->prepare(
            'UPDATE loan_requests
             SET status = ?, lent_at = datetime(\'now\'), loan_id = ?
             WHERE id = ? AND owner_user_id = ? AND status = ?'
        )->execute([self::STATUS_LENT, $loanId, $requestId, $ownerUserId, self::STATUS_ACCEPTED]);

        return true;
    }

    /** @return array<string, mixed>|null */
    private function findForOwner(int $requestId, int $ownerUserId): ?array
    {
        if ($requestId <= 0 || $ownerUserId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, owner_user_id, requester_user_id, status, requested_at, responded_at, note, loan_id
             FROM loan_requests WHERE id = ? AND owner_user_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId, $ownerUserId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function findForRequester(int $requestId, int $requesterUserId): ?array
    {
        if ($requestId <= 0 || $requesterUserId <= 0 || !self::tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, owner_user_id, requester_user_id, status, requested_at, responded_at, note, loan_id
             FROM loan_requests WHERE id = ? AND requester_user_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId, $requesterUserId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}

