<?php
/**
 * Approuve ou refuse une demande d’inscription (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\Csrf;
use Moncine\RegistrationService;
use Moncine\UserContext;

Auth::denyUnlessAdmin('/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /demandes-inscription.php');
    exit;
}

$requestId = max(0, (int) ($_POST['request_id'] ?? 0));
$reviewUrl = $requestId > 0
    ? '/demandes-inscription.php?id=' . $requestId
    : '/demandes-inscription.php';

Csrf::rejectUnlessValid($_POST, $reviewUrl);

$action = (string) ($_POST['action'] ?? '');
$reviewNote = trim((string) ($_POST['review_note'] ?? ''));
$adminId = UserContext::currentUserId();
$service = new RegistrationService();

if ($requestId <= 0) {
    header('Location: /demandes-inscription.php?save_error=' . rawurlencode('Demande invalide.'));
    exit;
}

if ($action === 'reject') {
    $result = $service->reject($requestId, $adminId, $reviewNote);
    if ($result !== true) {
        header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode((string) $result));
        exit;
    }
    header('Location: /demandes-inscription.php?rejected=1');
    exit;
}

if ($action === 'approve') {
    $result = $service->approve($requestId, $adminId, $reviewNote);
    if ($result !== true) {
        header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode((string) $result));
        exit;
    }
    header('Location: /demandes-inscription.php?approved=1');
    exit;
}

header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode('Action non reconnue.'));
exit;
