<?php
/**
 * Met à jour ou supprime un numéro magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MagazineIssueSupplementRepository;
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
Csrf::rejectUnlessValid($_POST, $returnUrl);
UploadLimits::guardPostWithFiles($_POST, $returnUrl, [
    'pdf_file' => 'PDF',
    'cover_file' => 'Couverture',
    'supplement_pdf_file' => 'PDF supplément',
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

if ($action === 'remove_pdf') {
    if ($oeuvreId <= 0) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Numéro invalide.'));
        exit;
    }

    $pdfResult = $repo->detachPdf($oeuvreId);
    if ($pdfResult !== true) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $pdfResult) . '&pdf=1');
        exit;
    }

    header('Location: ' . View::magazineIssueUrl(
        $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
    ) . '&pdf_removed=1');
    exit;
}

if ($action === 'add_supplement') {
    if ($oeuvreId <= 0) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Numéro invalide.') . '&popover=pdf');
        exit;
    }

    if (!MagazineIssueSupplementRepository::isAvailable()) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Suppléments non disponibles (migration requise).') . '&popover=pdf');
        exit;
    }

    if (!UploadLimits::phpAllowsPdfUpload()) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode(strip_tags(UploadLimits::phpLimitsWarning())) . '&popover=pdf');
        exit;
    }

    $files = $_FILES['supplement_pdf_file'] ?? null;
    if (!is_array($files) || !isset($files['error'])) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Sélectionnez au moins un PDF.') . '&popover=pdf');
        exit;
    }

    // Un seul fichier ou plusieurs (name="supplement_pdf_file[]").
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [$files['name'] ?? ''];
    $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];

    $label = trim((string) ($_POST['supplement_label'] ?? ''));
    $supplementRepo = new MagazineIssueSupplementRepository();
    $added = 0;
    $lastError = '';

    foreach ($errors as $index => $uploadError) {
        $uploadError = (int) $uploadError;
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $lastError = UploadLimits::fileUploadErrorMessage($uploadError, 'PDF supplément');
            continue;
        }

        $result = $supplementRepo->attachUploadedPdf(
            $oeuvreId,
            (string) ($tmpNames[$index] ?? ''),
            (string) ($names[$index] ?? 'supplement.pdf'),
            (int) ($sizes[$index] ?? 0),
            $added === 0 ? $label : ($label !== '' ? $label : '')
        );
        if ($result !== true) {
            $lastError = (string) $result;
            continue;
        }
        $added++;
    }

    if ($added === 0) {
        $message = $lastError !== '' ? $lastError : 'Sélectionnez au moins un PDF.';
        header('Location: ' . $returnUrl . '&error=' . rawurlencode($message) . '&popover=pdf');
        exit;
    }

    header('Location: ' . View::magazineIssueUrl(
        $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
    ) . '&saved=1&supplement=1&popover=pdf');
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

if ($action === 'remove_supplement') {
    $supplementId = (int) ($_POST['supplement_id'] ?? 0);
    if ($oeuvreId <= 0 || $supplementId <= 0) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode('Supplément invalide.') . '&popover=pdf');
        exit;
    }

    $result = (new MagazineIssueSupplementRepository())->deleteById($supplementId, $oeuvreId);
    if ($result !== true) {
        header('Location: ' . $returnUrl . '&error=' . rawurlencode((string) $result) . '&popover=pdf');
        exit;
    }

    header('Location: ' . View::magazineIssueUrl(
        $repo->resolveIssueBibIdForRedirect($oeuvreId, $userId, $foyerId, $bibId)
    ) . '&supplement_removed=1&popover=pdf');
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
