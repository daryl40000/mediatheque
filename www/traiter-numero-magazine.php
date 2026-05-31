<?php
/**
 * Met à jour ou supprime un numéro magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /magazines.php');
    exit;
}

MediaDomainGuards::ensureMagazineContext();

$bibId = (int) ($_POST['bib_id'] ?? 0);
$returnUrl = View::magazineIssueUrl($bibId);
Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'delete') {
    $issueBefore = $repo->findIssueByBibId($bibId, $userId, $foyerId);
    $seriesId = (int) ($issueBefore['series_id'] ?? $_POST['series_id'] ?? 0);
    $result = $repo->deleteFromLibrary($bibId, $userId, $foyerId);
    if ($result !== true) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
        exit;
    }
    header('Location: ' . ($seriesId > 0 ? View::magazineSeriesUrl($seriesId) : '/magazines.php') . '&deleted=1');
    exit;
}

$data = [
    'numero' => (string) ($_POST['numero'] ?? ''),
    'numero_ordre' => (float) ($_POST['numero_ordre'] ?? 0),
    'date_parution' => (string) ($_POST['date_parution'] ?? ''),
    'sommaire' => (string) ($_POST['sommaire'] ?? ''),
    'pages' => (int) ($_POST['pages'] ?? 0),
    'est_hors_serie' => isset($_POST['est_hors_serie']),
    'support_physique' => (string) ($_POST['support_physique'] ?? ''),
];

$issue = $repo->findIssueByBibId($bibId, $userId, $foyerId);
$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);

if ($oeuvreId > 0 && isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $binary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
    $posterUrl = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
    if ($posterUrl !== '') {
        $data['poster_url'] = $posterUrl;
    }
}

$result = $repo->updateIssue($bibId, $data, $userId, $foyerId);
if ($result !== true) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
    exit;
}

if ($oeuvreId > 0 && isset($_FILES['pdf_file']) && (int) ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $pdfResult = $repo->attachPdf(
        $oeuvreId,
        (string) $_FILES['pdf_file']['tmp_name'],
        (string) ($_FILES['pdf_file']['name'] ?? 'numero.pdf'),
        (int) ($_FILES['pdf_file']['size'] ?? 0)
    );
    if ($pdfResult !== true) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $pdfResult));
        exit;
    }
}

header('Location: ' . $returnUrl . '&saved=1');
exit;
