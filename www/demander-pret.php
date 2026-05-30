<?php
/**
 * Demander un prêt d'un film appartenant à un ami.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\LoanRequestRepository;
use Moncine\NotificationService;

$userId = Auth::currentUserId();
if ($userId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$returnTo = trim((string) ($_POST['return_to'] ?? '/mes-amis.php'));
if ($returnTo === '' || !str_starts_with($returnTo, '/') || str_contains($returnTo, '//')) {
    $returnTo = '/mes-amis.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !LoanRequestRepository::tableExists()) {
    header('Location: ' . $returnTo);
    exit;
}

Csrf::rejectUnlessValid($_POST, $returnTo);

$bibliothequeId = (int) ($_POST['bibliotheque_id'] ?? 0);
$ownerUserId = (int) ($_POST['owner_user_id'] ?? 0);
$note = (string) ($_POST['note'] ?? '');

$result = (new LoanRequestRepository())->requestLoan($bibliothequeId, $userId, $ownerUserId, $note);

$sep = str_contains($returnTo, '?') ? '&' : '?';
if (is_int($result)) {
    // Notification côté propriétaire
    if (NotificationService::isAvailable()) {
        $stmt = Moncine\Database::getInstance()->prepare(
            'SELECT o.titre
             FROM bibliotheque b INNER JOIN oeuvres o ON o.id = b.oeuvre_id
             WHERE b.id = ? LIMIT 1'
        );
        $stmt->execute([$bibliothequeId]);
        $titre = (string) ($stmt->fetchColumn() ?: '');
        (new NotificationService())->notifyLoanRequested($ownerUserId, $userId, $titre);
    }
    header('Location: ' . $returnTo . $sep . 'pret=demande');
} else {
    header('Location: ' . $returnTo . $sep . 'pret_erreur=' . rawurlencode((string) $result));
}
exit;

