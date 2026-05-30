<?php
/**
 * File d’attente des propositions catalogue (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogSubmission;
use Moncine\FilmEnricher;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();
CatalogSubmission::denyUnlessAvailable();

$service = new CatalogSubmission();
$reviewId = max(0, (int) ($_GET['id'] ?? 0));
$review = $reviewId > 0 ? $service->findForAdmin($reviewId) : null;
if ($review !== null && (string) ($review['status'] ?? '') !== 'pending') {
    $review = null;
}

View::render('soumissions-catalogue', [
    'pageTitle' => 'Soumissions catalogue',
    'wideLayout' => true,
    'pending' => $review === null ? $service->listPendingForAdmin() : [],
    'review' => $review,
    'pendingCount' => $service->countPending(),
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'approved' => isset($_GET['approved']) && (string) $_GET['approved'] === '1',
    'rejected' => isset($_GET['rejected']) && (string) $_GET['rejected'] === '1',
    'hasTmdbKey' => FilmEnricher::canEnrich(),
]);
