<?php
/**
 * Mes prêts (phase 8) : demandes reçues (à accepter/refuser), réservations, prêts en cours.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\LoanRepository;
use Moncine\LoanRequestRepository;
use Moncine\NotificationService;
use Moncine\View;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$error = '';
$success = '';

$requests = new LoanRequestRepository();
$loans = new LoanRepository();
$notifications = new NotificationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && LoanRequestRepository::tableExists()) {
    Csrf::rejectUnlessValid($_POST, '/mes-prets.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'accept') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $result = $requests->acceptRequest($requestId, $userId);
        $success = $result === true ? 'Demande acceptée : exemplaire réservé.' : '';
        $error = $result === true ? '' : (string) $result;
        if ($result === true && NotificationService::isAvailable()) {
            $stmt = Moncine\Database::getInstance()->prepare(
                'SELECT lr.requester_user_id, ' . Moncine\LoanCatalog::notificationSelect() . '
                 FROM loan_requests lr
                 INNER JOIN bibliotheque b ON b.id = lr.bibliotheque_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE lr.id = ? AND lr.owner_user_id = ? LIMIT 1'
            );
            $stmt->execute([$requestId, $userId]);
            $row = $stmt->fetch();
            $requesterId = (int) ($row['requester_user_id'] ?? 0);
            $titre = (string) ($row['titre'] ?? '');
            $mediaDomain = (string) ($row['media_domain'] ?? '');
            if ($requesterId > 0) {
                $notifications->notifyLoanAccepted($requesterId, $userId, $titre, $mediaDomain);
            }
        }
    } elseif ($action === 'decline') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $result = $requests->declineRequest($requestId, $userId);
        $success = $result === true ? 'Demande refusée.' : '';
        $error = $result === true ? '' : (string) $result;
        if ($result === true && NotificationService::isAvailable()) {
            $stmt = Moncine\Database::getInstance()->prepare(
                'SELECT lr.requester_user_id, ' . Moncine\LoanCatalog::notificationSelect() . '
                 FROM loan_requests lr
                 INNER JOIN bibliotheque b ON b.id = lr.bibliotheque_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE lr.id = ? AND lr.owner_user_id = ? LIMIT 1'
            );
            $stmt->execute([$requestId, $userId]);
            $row = $stmt->fetch();
            $requesterId = (int) ($row['requester_user_id'] ?? 0);
            $titre = (string) ($row['titre'] ?? '');
            $mediaDomain = (string) ($row['media_domain'] ?? '');
            if ($requesterId > 0) {
                $notifications->notifyLoanDeclined($requesterId, $userId, $titre, $mediaDomain);
            }
        }
    } elseif ($action === 'lend') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $dueAt = isset($_POST['due_at']) ? (string) $_POST['due_at'] : null;
        $result = $loans->createLoanFromAcceptedRequest($requestId, $userId, $dueAt);
        if (is_int($result)) {
            $success = 'Prêt enregistré.';
            if (NotificationService::isAvailable()) {
                $stmt = Moncine\Database::getInstance()->prepare(
                    'SELECT lr.requester_user_id, ' . Moncine\LoanCatalog::notificationSelect() . '
                     FROM loan_requests lr
                     INNER JOIN bibliotheque b ON b.id = lr.bibliotheque_id
                     INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                     WHERE lr.id = ? AND lr.owner_user_id = ? LIMIT 1'
                );
                $stmt->execute([$requestId, $userId]);
                $row = $stmt->fetch();
                $requesterId = (int) ($row['requester_user_id'] ?? 0);
                $titre = (string) ($row['titre'] ?? '');
                $mediaDomain = (string) ($row['media_domain'] ?? '');
                if ($requesterId > 0) {
                    $notifications->notifyLoanLent($requesterId, $userId, $titre, $mediaDomain);
                }
            }
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'return') {
        $loanId = (int) ($_POST['loan_id'] ?? 0);
        $borrowerId = 0;
        $titre = '';
        $mediaDomain = '';
        if (NotificationService::isAvailable() && $loanId > 0) {
            $stmt = Moncine\Database::getInstance()->prepare(
                'SELECT l.borrower_user_id, ' . Moncine\LoanCatalog::notificationSelect() . '
                 FROM loans l
                 INNER JOIN bibliotheque b ON b.id = l.bibliotheque_id
                 INNER JOIN oeuvres o ON o.id = b.oeuvre_id
                 WHERE l.id = ? AND l.lender_user_id = ? LIMIT 1'
            );
            $stmt->execute([$loanId, $userId]);
            $row = $stmt->fetch();
            $borrowerId = (int) ($row['borrower_user_id'] ?? 0);
            $titre = (string) ($row['titre'] ?? '');
            $mediaDomain = (string) ($row['media_domain'] ?? '');
        }
        $result = $loans->markReturned($loanId, $userId);
        $success = $result === true ? 'Retour enregistré.' : '';
        $error = $result === true ? '' : (string) $result;
        if ($result === true && NotificationService::isAvailable() && $borrowerId > 0) {
            $notifications->notifyLoanReturned($borrowerId, $userId, $titre, $mediaDomain);
        }
    }
}

View::render('mes-prets', [
    'pageTitle' => 'Mes prêts',
    'error' => $error,
    'success' => $success,
    'pendingRequests' => LoanRequestRepository::tableExists() ? $requests->listPendingForOwner($userId) : [],
    'reservedRequests' => LoanRequestRepository::tableExists() ? $requests->listReservedForOwner($userId) : [],
    'activeLoans' => LoanRepository::tableExists() ? $loans->listActiveLoansForOwner($userId) : [],
]);

