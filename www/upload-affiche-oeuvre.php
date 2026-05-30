<?php
/**
 * Enregistre une affiche catalogue depuis un fichier image (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\UploadLimits;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$catalogSearch = trim((string) ($_POST['catalog_q'] ?? ''));
$catalogSort = (string) ($_POST['catalog_sort'] ?? 'titre');
$catalogDir = (string) ($_POST['catalog_dir'] ?? 'asc');
$catalogPage = max(1, (int) ($_POST['catalog_page'] ?? 1));

$returnUrl = $oeuvreId > 0
    ? View::oeuvreUrl($oeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage)
    : View::catalogueUrl($catalogSearch, $catalogSort, $catalogDir, $catalogPage);

$sep = str_contains($returnUrl, '?') ? '&' : '?';

if ($oeuvreId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

Csrf::rejectUnlessValid($_POST, $returnUrl);

if (UploadLimits::postBodyWasDiscarded()) {
    $params = http_build_query(['poster_error' => UploadLimits::postTooLargeMessage()]);
    header('Location: ' . $returnUrl . $sep . $params);
    exit;
}

$uploadError = (int) ($_FILES['poster_file']['error'] ?? UPLOAD_ERR_NO_FILE);
if (!isset($_FILES['poster_file']) || $uploadError !== UPLOAD_ERR_OK) {
    $message = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image trop volumineuse pour PHP ('
            . UploadLimits::uploadMaxFilesizeLabel() . ' max).',
        UPLOAD_ERR_NO_FILE => 'Aucune image sélectionnée.',
        default => 'Erreur lors de l’envoi du fichier.',
    };
    $params = http_build_query(['poster_error' => $message]);
    header('Location: ' . $returnUrl . $sep . $params);
    exit;
}

$tmpPath = (string) ($_FILES['poster_file']['tmp_name'] ?? '');
$fileSize = (int) ($_FILES['poster_file']['size'] ?? 0);

$result = (new CatalogAdmin())->uploadPosterFile($oeuvreId, $tmpPath, $fileSize);
if ($result !== true) {
    $params = http_build_query(['poster_error' => (string) $result]);
    header('Location: ' . $returnUrl . $sep . $params);
    exit;
}

header('Location: ' . $returnUrl . $sep . 'poster_uploaded=1');
exit;
