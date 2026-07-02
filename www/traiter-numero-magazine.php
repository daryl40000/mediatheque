<?php
/**
 * Met à jour ou supprime un numéro magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\FormCheckbox;
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

$bibId = (int) ($_POST['bib_id'] ?? 0);
$returnUrl = View::magazineIssueUrl($bibId);
UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'pdf_file' => 'PDF',
    'cover_file' => 'Couverture',
]);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'wishlist') {
    $issueBefore = $repo->findIssueByBibId($bibId, $userId, $foyerId);
    $seriesId = (int) ($issueBefore['series_id'] ?? $_POST['series_id'] ?? 0);
    $result = $repo->addIssueToWishlist($bibId, $userId, $foyerId);
    if ($result !== true) {
        $redirect = $bibId > 0 ? View::magazineIssueUrl($bibId) : View::magazineSeriesUrl($seriesId);
        header('Location: ' . $redirect . '&error=' . rawurlencode((string) $result));
        exit;
    }
    $possession = MagazineRepository::normalizePossessionFilter((string) ($_POST['possession'] ?? 'all'));
    $redirectExtra = ['statut' => LibraryStatut::COLLECTION, 'wishlist' => '1'];
    if ($possession !== MagazineRepository::POSSESSION_ALL) {
        $redirectExtra['possession'] = $possession;
    }
    $redirect = $seriesId > 0
        ? View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', $redirectExtra)
        : '/magazines.php?wishlist=1';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $issueBefore = $repo->findIssueByBibId($bibId, $userId, $foyerId);
    $seriesId = (int) ($issueBefore['series_id'] ?? $_POST['series_id'] ?? 0);
    $returnStatut = LibraryStatut::normalize(
        (string) ($_POST['return_statut'] ?? $issueBefore['statut'] ?? LibraryStatut::COLLECTION)
    );
    $possession = MagazineRepository::normalizePossessionFilter((string) ($_POST['possession'] ?? 'all'));
    $result = $repo->deleteFromLibrary($bibId, $userId, $foyerId);
    if ($result !== true) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result));
        exit;
    }
    if ($seriesId > 0) {
        $redirectExtra = ['statut' => $returnStatut];
        if ($returnStatut === LibraryStatut::COLLECTION && $possession !== MagazineRepository::POSSESSION_ALL) {
            $redirectExtra['possession'] = $possession;
        }
        $redirect = View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', $redirectExtra);
    } elseif ($returnStatut === LibraryStatut::WISHLIST) {
        $redirect = '/magazines-envies.php';
    } else {
        $redirect = '/magazines.php';
    }
    header('Location: ' . $redirect . '&deleted=1');
    exit;
}

$issue = $repo->findIssueByBibId($bibId, $userId, $foyerId);
if ($issue === null) {
    header('Location: ' . $returnUrl . '&error=' . rawurlencode('Numéro introuvable.'));
    exit;
}

$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);

if ($action === 'pdf_only') {
    if ($oeuvreId <= 0) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Numéro invalide.'));
        exit;
    }

    if (!UploadLimits::phpAllowsPdfUpload()) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode(strip_tags(UploadLimits::phpLimitsWarning())));
        exit;
    }

    if (!isset($_FILES['pdf_file']) || (int) ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadError = (int) ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_NO_FILE && $uploadError !== UPLOAD_ERR_OK) {
            header('Location: ' . $returnUrl . '&error=' . rawurlencode(UploadLimits::fileUploadErrorMessage($uploadError, 'PDF')));
            exit;
        }
        if (UploadLimits::postBodyWasDiscarded() || ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > UploadLimits::currentPostMaxBytes())) {
            header('Location: ' . $returnUrl . '&error=' . rawurlencode(UploadLimits::postTooLargeMessage()));
            exit;
        }
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Sélectionnez un fichier PDF.'));
        exit;
    }

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

    header('Location: ' . View::magazineIssueUrl(
        $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
    ) . '&saved=1&pdf=1');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

$data = [
    'numero' => (string) ($_POST['numero'] ?? $issue['numero'] ?? ''),
    'numero_ordre' => (float) ($_POST['numero_ordre'] ?? $issue['numero_ordre'] ?? 0),
    'date_parution' => (string) ($_POST['date_parution'] ?? $issue['date_parution'] ?? ''),
    'sommaire' => (string) ($_POST['sommaire'] ?? $issue['sommaire'] ?? ''),
    'pages' => (int) ($_POST['pages'] ?? $issue['pages'] ?? 0),
    'est_hors_serie' => FormCheckbox::isChecked($_POST, 'est_hors_serie'),
    'support_papier' => isset($_POST['support_papier']),
];

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
    header('Location: ' . View::magazineIssueUrl(
        $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
    ) . '&saved=1&pdf=1');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

header('Location: ' . View::magazineIssueUrl(
    $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
) . '&saved=1');
exit;
