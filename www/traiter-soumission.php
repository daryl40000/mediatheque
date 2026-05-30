<?php
/**
 * Traite une proposition catalogue (accepter / refuser).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\CatalogSubmission;
use Moncine\Csrf;
use Moncine\FilmManualEdit;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();
CatalogSubmission::denyUnlessAvailable();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /soumissions-catalogue.php');
    exit;
}

$submissionId = max(0, (int) ($_POST['submission_id'] ?? 0));
$reviewUrl = $submissionId > 0
    ? '/soumissions-catalogue.php?id=' . $submissionId
    : '/soumissions-catalogue.php';

Csrf::rejectUnlessValid($_POST, $reviewUrl);

$action = (string) ($_POST['action'] ?? '');
$reviewNote = trim((string) ($_POST['review_note'] ?? ''));
$adminId = UserContext::currentUserId();
$service = new CatalogSubmission();

if ($submissionId <= 0) {
    header('Location: /soumissions-catalogue.php?save_error=' . rawurlencode('Proposition invalide.'));
    exit;
}

if ($action === 'reject') {
    $result = $service->reject($submissionId, $adminId, $reviewNote);
    if ($result !== true) {
        header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode((string) $result));
        exit;
    }
    header('Location: /soumissions-catalogue.php?rejected=1');
    exit;
}

if ($action === 'approve' || $action === 'approve_enrich') {
    $parsed = FilmManualEdit::parseFromPost($_POST);
    if (!$parsed['ok']) {
        header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode($parsed['error']));
        exit;
    }

    $result = $service->approve(
        $submissionId,
        $adminId,
        $parsed['data'],
        $reviewNote,
        $action === 'approve_enrich'
    );

    if (!is_int($result)) {
        header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode((string) $result));
        exit;
    }

    $oeuvreUrl = View::oeuvreUrl($result);
    header('Location: ' . $oeuvreUrl . (str_contains($oeuvreUrl, '?') ? '&' : '?') . 'from_submission=1');
    exit;
}

header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode('Action non reconnue.'));
exit;
