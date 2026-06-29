<?php
/**
 * Traite une proposition catalogue (accepter / refuser).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogSubmission;
use Moncine\Csrf;
use Moncine\FilmManualEdit;
use Moncine\GameManualEdit;
use Moncine\GameRepository;
use Moncine\MediaDomain;
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
    $reviewRow = $service->findForAdmin($submissionId);
    $domain = is_array($reviewRow)
        ? (string) ($reviewRow['submission_domain'] ?? MediaDomain::FILM)
        : MediaDomain::FILM;
    $isGame = MediaDomain::isGame($domain);

    if ($isGame) {
        if (!GameRepository::isAvailable()) {
            header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode('Module jeux non disponible.'));
            exit;
        }
        $parsed = GameManualEdit::parseFromPost($_POST, true);
    } else {
        $parsed = FilmManualEdit::parseFromPost($_POST);
        if ($parsed['ok']) {
            $parsed['data']['submission_domain'] = MediaDomain::FILM;
        }
    }

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

    if ($isGame) {
        header('Location: /oeuvre-jeu.php?id=' . $result . '&from_submission=1');
        exit;
    }

    $oeuvreUrl = View::oeuvreUrl($result);
    header('Location: ' . $oeuvreUrl . (str_contains($oeuvreUrl, '?') ? '&' : '?') . 'from_submission=1');
    exit;
}

header('Location: ' . $reviewUrl . '&save_error=' . rawurlencode('Action non reconnue.'));
exit;
