<?php
/**
 * Annuler une demande de prêt (côté demandeur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\LoanRequestRepository;

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

$requestId = (int) ($_POST['request_id'] ?? 0);
$result = (new LoanRequestRepository())->cancelRequest($requestId, $userId);

$sep = str_contains($returnTo, '?') ? '&' : '?';
if ($result === true) {
    header('Location: ' . $returnTo . $sep . 'pret=annule');
} else {
    header('Location: ' . $returnTo . $sep . 'pret_erreur=' . rawurlencode((string) $result));
}
exit;

