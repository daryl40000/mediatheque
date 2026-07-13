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
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /magazines.php');
    exit;
}

@set_time_limit(600);

MediaDomainGuards::ensureMagazineContext();

$seriesId = (int) ($_POST['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));
$returnUrl = $seriesId > 0
    ? '/ajouter-numero-magazine.php?series_id=' . $seriesId . '&statut=' . rawurlencode($statut)
    : '/magazines.php';

Csrf::rejectUnlessValid($_POST, $returnUrl);

UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'pdf_file' => 'PDF',
    'cover_file' => 'Couverture',
]);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();
$oeuvreIdFromCatalog = max(0, (int) ($_POST['oeuvre_id'] ?? 0));
$supportPapier = isset($_POST['support_papier']);

if ($oeuvreIdFromCatalog > 0) {
    $catalogIssue = $repo->findCatalogIssueByOeuvreId($oeuvreIdFromCatalog);
    if ($catalogIssue === null || (int) ($catalogIssue['series_id'] ?? 0) !== $seriesId) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Numéro catalogue invalide pour cette série.'));
        exit;
    }

    $result = $repo->addFromCatalogOeuvre($oeuvreIdFromCatalog, $statut, $userId, $foyerId);
} else {
    $numero = trim((string) ($_POST['numero'] ?? ''));
    $horsSerie = isset($_POST['est_hors_serie']);
    if ($numero !== '' && $repo->findCatalogIssueBySeriesNumero($seriesId, $numero, $horsSerie) !== null) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode(
            'Ce numéro existe déjà au catalogue — sélectionnez-le dans la liste de suggestions.'
        ));
        exit;
    }

    $result = $repo->createIssueWithLibrary($seriesId, [
        'numero' => (string) ($_POST['numero'] ?? ''),
        'numero_ordre' => (float) ($_POST['numero_ordre'] ?? 0),
        'date_parution' => (string) ($_POST['date_parution'] ?? ''),
        'sommaire' => (string) ($_POST['sommaire'] ?? ''),
        'pages' => (int) ($_POST['pages'] ?? 0),
        'est_hors_serie' => isset($_POST['est_hors_serie']),
        'support_papier' => $supportPapier,
    ], $statut, $userId, $foyerId);
}

if (!is_int($result)) {
    $params = http_build_query(['error' => (string) $result]);
    header('Location: ' . $returnUrl . '&' . $params);
    exit;
}

if ($supportPapier && $oeuvreIdFromCatalog > 0) {
    $repo->updateIssue($result, ['support_papier' => true], $userId, $foyerId);
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
    if (!UploadLimits::phpAllowsPdfUpload()) {
        header('Location: ' . View::magazineIssueUrl($result) . '&error=' . rawurlencode(strip_tags(UploadLimits::phpLimitsWarning())));
        exit;
    }

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
    header('Location: ' . View::magazineIssueUrl($result) . '&added=1&pdf=1');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

header('Location: ' . View::magazineIssueUrl($result) . '&added=1');
exit;
