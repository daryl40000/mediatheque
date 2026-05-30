<?php
/**
 * Enregistre les modifications manuelles d’une œuvre catalogue.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\FilmManualEdit;
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

if ($oeuvreId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

$sep = str_contains($returnUrl, '?') ? '&' : '?';
$editUrl = $returnUrl . $sep . 'edit=1';

Csrf::rejectUnlessValid($_POST, $editUrl);

$parsed = FilmManualEdit::parseFromPost($_POST);
if (!$parsed['ok']) {
    $params = http_build_query([
        'save_error' => $parsed['error'],
        'edit' => '1',
    ]);
    header('Location: ' . $editUrl . '&' . $params);
    exit;
}

$result = (new CatalogAdmin())->updateOeuvreManual($oeuvreId, $parsed['data']);
if ($result !== true) {
    $params = http_build_query([
        'save_error' => (string) $result,
        'edit' => '1',
    ]);
    header('Location: ' . $editUrl . '&' . $params);
    exit;
}

header('Location: ' . $returnUrl . $sep . 'saved=1');
exit;
