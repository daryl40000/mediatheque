<?php
/**
 * Enrichissement TMDB d’une œuvre du catalogue partagé.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\FilmEnricher;
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

Csrf::rejectUnlessValid($_POST, $returnUrl);

$enricher = new FilmEnricher();
$action = (string) ($_POST['action'] ?? 'enrich');

if ($action === 'tmdb') {
    $result = $enricher->correctOeuvreWithTmdbId($oeuvreId, (string) ($_POST['tmdb_id'] ?? ''));
} else {
    $result = $enricher->enrichOeuvre($oeuvreId);
}

$status = $result['ok'] ? 'ok' : ($result['not_found'] ? 'not_found' : 'error');
$params = http_build_query([
    'enrich' => $status,
    'enrich_msg' => $result['message'],
]);

$sep = str_contains($returnUrl, '?') ? '&' : '?';
header('Location: ' . $returnUrl . $sep . $params);
exit;
