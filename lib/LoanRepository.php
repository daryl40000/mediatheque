<?php
/**
 * Prêts effectifs (phase 8) : création et retour.
 */

declare(strict_types=1);

namespace Moncine;

use PDO;

final class LoanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function tableExists(): bool
    {
        $stmt = Database::getInstance()->query(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'loans' LIMIT 1"
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * Crée un prêt effectif (depuis une demande acceptée).
     *
     * @return int|string ID du prêt ou message d'erreur
     */
    public function createLoanFromAcceptedRequest(int $requestId, int $ownerUserId, ?string $dueAt = null): int|string
    {
        if (!self::tableExists() || !LoanRequestRepository::tableExists()) {
            return 'Les prêts ne sont pas disponibles (migration en attente).';
        }
        if ($requestId <= 0 || $ownerUserId <= 0) {
            return 'Paramètres invalides.';
        }

        $reqRepo = new LoanRequestRepository();
        // Accès via une lecture directe pour récupérer borrower + bibliotheque.
        $stmt = $this->db->prepare(
            'SELECT id, bibliotheque_id, owner_user_id, requester_user_id, status
             FROM loan_requests WHERE id = ? AND owner_user_id = ? LIMIT 1'
        );
        $stmt->execute([$requestId, $ownerUserId]);
        $req = $stmt->fetch();
        if ($req === false) {
            return 'Demande introuvable.';
        }
        if ((string) ($req['status'] ?? '') !== LoanRequestRepository::STATUS_ACCEPTED) {
            return 'La demande n’est pas réservée.';
        }

        $bibliothequeId = (int) ($req['bibliotheque_id'] ?? 0);
        $borrowerUserId = (int) ($req['requester_user_id'] ?? 0);
        if ($bibliothequeId <= 0 || $borrowerUserId <= 0) {
            return 'Demande invalide.';
        }

        // Refuse si déjà prêté.
        $activeLoan = $this->db->prepare(
            'SELECT 1 FROM loans WHERE bibliotheque_id = ? AND returned_at IS NULL LIMIT 1'
        );
        $activeLoan->execute([$bibliothequeId]);
        if ($activeLoan->fetchColumn()) {
            return 'Cet exemplaire est déjà prêté.';
        }

        // Valide due_at (ISO yyyy-mm-dd), optionnel.
        $dueAt = $dueAt !== null ? trim($dueAt) : null;
        if ($dueAt !== null && $dueAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueAt)) {
            return 'Date de retour prévue invalide.';
        }
        if ($dueAt === '') {
            $dueAt = null;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO loans (bibliotheque_id, lender_user_id, borrower_user_id, borrower_name, loaned_at, due_at, returned_at, note, created_at)
                 VALUES (?, ?, ?, \'\', date(\'now\'), ?, NULL, \'\', datetime(\'now\'))'
            )->execute([$bibliothequeId, $ownerUserId, $borrowerUserId, $dueAt]);
            $loanId = (int) $this->db->lastInsertId();

            $mark = $reqRepo->markLent($requestId, $ownerUserId, $loanId);
            if ($mark !== true) {
                throw new \RuntimeException((string) $mark);
            }

            $this->db->commit();

            return $loanId;
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
    public function markReturned(int $loanId, int $ownerUserId): bool|string
    {
        if (!self::tableExists()) {
            return 'Les prêts ne sont pas disponibles.';
        }
        if ($loanId <= 0 || $ownerUserId <= 0) {
            return 'Paramètres invalides.';
        }
        $stmt = $this->db->prepare(
            'UPDATE loans SET returned_at = date(\'now\')
             WHERE id = ? AND lender_user_id = ? AND returned_at IS NULL'
        );
        $stmt->execute([$loanId, $ownerUserId]);

        return $stmt->rowCount() > 0 ? true : 'Prêt introuvable ou déjà rendu.';
    }

    /** @return list<array<string, mixed>> */
    public function listActiveLoansForOwner(int $ownerUserId): array
    {
        if ($ownerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT l.id AS loan_id, l.loaned_at, l.due_at, l.returned_at,
                    u.id AS borrower_id, u.nom AS borrower_nom, u.prenom AS borrower_prenom, u.pseudo AS borrower_pseudo,
                    ' . LoanCatalog::selectLoanRow() . '
             FROM loans l
             INNER JOIN utilisateurs u ON u.id = l.borrower_user_id
             INNER JOIN ' . CatalogSchema::JOIN . LoanCatalog::joinExtras() . '
             WHERE l.lender_user_id = ?
               AND l.returned_at IS NULL
               AND b.id = l.bibliotheque_id
             ORDER BY l.loaned_at DESC, l.id DESC'
        );
        $stmt->execute([$ownerUserId]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Films actuellement prêtés par un owner (par bibliotheque_id).
     *
     * @return array<int, bool> map[bibliotheque_id]=true
     */
    public function mapActiveLoansByBibliothequeId(int $ownerUserId): array
    {
        if ($ownerUserId <= 0 || !self::tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT bibliotheque_id FROM loans
             WHERE lender_user_id = ? AND returned_at IS NULL'
        );
        $stmt->execute([$ownerUserId]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $bibId = (int) ($row['bibliotheque_id'] ?? 0);
            if ($bibId > 0) {
                $out[$bibId] = true;
            }
        }

        return $out;
    }
}

