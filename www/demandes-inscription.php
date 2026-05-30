<?php
/**
 * Demandes d’inscription en attente de validation (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\InscriptionRequestRepository;
use Moncine\RegistrationService;
use Moncine\RegistrationSettings;
use Moncine\View;

Auth::denyUnlessAdmin('/');

if (!RegistrationService::isAvailable()) {
    header('Location: /utilisateurs.php');
    exit;
}

$service = new RegistrationService();
$reviewId = max(0, (int) ($_GET['id'] ?? 0));
$review = null;
if ($reviewId > 0) {
    $row = (new InscriptionRequestRepository())->findById($reviewId);
    if ($row !== null && (string) ($row['status'] ?? '') === InscriptionRequestRepository::STATUS_PENDING_ADMIN) {
        $review = $row;
    }
}

View::render('demandes-inscription', [
    'pageTitle' => 'Inscriptions à valider',
    'wideLayout' => true,
    'pending' => $review === null ? $service->listPendingAdmin() : [],
    'review' => $review,
    'pendingCount' => $service->countPendingAdmin(),
    'registrationMode' => (new RegistrationSettings())->getMode(),
    'approved' => isset($_GET['approved']) && (string) $_GET['approved'] === '1',
    'rejected' => isset($_GET['rejected']) && (string) $_GET['rejected'] === '1',
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
]);
