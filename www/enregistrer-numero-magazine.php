<?php
/**
 * Enregistre un numéro magazine (catalogue + bibliothèque + couverture + PDF).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
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
Csrf::rejectUnlessValid($_POST, '/magazines.php');

$seriesId = (int) ($_POST['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = $seriesId > 0
    ? '/ajouter-numero-magazine.php?series_id=' . $seriesId . '&statut=' . rawurlencode($statut)
    : '/magazines.php';

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$result = $repo->createIssueWithLibrary($seriesId, [
    'numero' => (string) ($_POST['numero'] ?? ''),
    'numero_ordre' => (float) ($_POST['numero_ordre'] ?? 0),
    'date_parution' => (string) ($_POST['date_parution'] ?? ''),
    'sommaire' => (string) ($_POST['sommaire'] ?? ''),
    'pages' => (int) ($_POST['pages'] ?? 0),
    'est_hors_serie' => isset($_POST['est_hors_serie']),
    'support_physique' => (string) ($_POST['support_physique'] ?? ''),
], $statut, $userId, $foyerId);

if (!is_int($result)) {
    $params = http_build_query(['error' => (string) $result]);
    header('Location: ' . $returnUrl . '&' . $params);
    exit;
}

$issue = $repo->findIssueByBibId($result, $userId, $foyerId);
$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);

if ($oeuvreId > 0 && isset($_FILES['cover_file']) && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $binary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
    $posterUrl = (new PosterStorage())->importBinaryForOeuvre($oeuvreId, $binary);
    if ($posterUrl !== '') {
        $repo->updateIssue($result, ['poster_url' => $posterUrl], $userId, $foyerId);
    }
}

if ($oeuvreId > 0 && isset($_FILES['pdf_file']) && (int) ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $pdfResult = $repo->attachPdf(
        $oeuvreId,
        (string) $_FILES['pdf_file']['tmp_name'],
        (string) ($_FILES['pdf_file']['name'] ?? 'numero.pdf'),
        (int) ($_FILES['pdf_file']['size'] ?? 0)
    );
    if ($pdfResult !== true) {
        header('Location: ' . View::magazineIssueUrl($result) . '&error=' . rawurlencode((string) $pdfResult));
        exit;
    }
}

header('Location: ' . View::magazineIssueUrl($result) . '&added=1');
exit;
