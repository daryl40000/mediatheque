<?php
/**
 * Fiche d’une œuvre du catalogue partagé (administration).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\MediaDomain;
use Moncine\OeuvreEanRepository;
use Moncine\TmdbConfig;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_GET['id'] ?? 0);
$admin = new CatalogAdmin();
$detail = $oeuvreId > 0 ? $admin->findOeuvreDetail($oeuvreId) : null;

$catalogListContext = CatalogListContext::fromQuery($_GET);
$catalogSearch = $catalogListContext->search();
$catalogSort = $catalogListContext->sortBy();
$catalogDir = $catalogListContext->sortDir();
$catalogPage = $catalogListContext->page();
$catalogueBackUrl = $catalogListContext->backUrl();

if ($detail === null) {
    View::render('oeuvre', [
        'pageTitle' => 'Œuvre introuvable',
        'oeuvre' => null,
        'library' => null,
        'libraryCount' => 0,
        'catalogueBackUrl' => $catalogueBackUrl,
    ]);
    exit;
}

$oeuvre = $detail['oeuvre'];

$domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
if ($domain === MediaDomain::JEU) {
    header('Location: ' . View::oeuvreJeuUrl($oeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage));
    exit;
}
if ($domain === MediaDomain::MAGAZINE) {
    header('Location: ' . View::oeuvreMagazineUrl($oeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage));
    exit;
}

$enrichStatus = null;
$enrichMessage = '';
if (isset($_GET['enrich'])) {
    $enrichStatus = match ((string) $_GET['enrich']) {
        'ok' => 'ok',
        'not_found' => 'not_found',
        default => 'error',
    };
    $enrichMessage = (string) ($_GET['enrich_msg'] ?? '');
    $refreshed = $admin->findOeuvreDetail($oeuvreId);
    if ($refreshed !== null) {
        $oeuvre = $refreshed['oeuvre'];
        $detail = $refreshed;
    }
}

$saveError = (string) ($_GET['save_error'] ?? '');
$posterUploadError = (string) ($_GET['poster_error'] ?? '');
$editOpen = isset($_GET['edit']) || $saveError !== '';
$posterUploadOpen = $posterUploadError !== '';

if (isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1') {
    $refreshed = $admin->findOeuvreDetail($oeuvreId);
    if ($refreshed !== null) {
        $oeuvre = $refreshed['oeuvre'];
        $detail = $refreshed;
    }
}

$oeuvreNav = $admin->getOeuvreNavigation($oeuvreId, $catalogSearch, $catalogSort, $catalogDir);
$oeuvreEans = (new OeuvreEanRepository())->listForOeuvre($oeuvreId);

$mergeMessage = '';
$mergeError = '';
if (isset($_GET['merge_ok']) && (string) $_GET['merge_ok'] === '1') {
    $removedId = (int) ($_GET['merge_removed'] ?? 0);
    $mergeMessage = $removedId > 0
        ? 'Fusion réussie : la fiche n°' . $removedId . ' a été intégrée dans celle-ci.'
        : 'Fusion réussie.';
}
if (isset($_GET['merge_error'])) {
    $mergeError = trim((string) $_GET['merge_error']);
}

View::render('oeuvre', [
    'pageTitle' => (string) ($oeuvre['titre'] ?? 'Œuvre catalogue'),
    'catalogListContext' => $catalogListContext,
    'oeuvreNav' => $oeuvreNav,
    'oeuvre' => $oeuvre,
    'oeuvreEans' => $oeuvreEans,
    'library' => $detail['library'],
    'libraryCount' => (int) $detail['library_count'],
    'catalogueBackUrl' => $catalogueBackUrl,
    'catalogSearch' => $catalogSearch,
    'catalogSort' => $catalogSort,
    'catalogDir' => $catalogDir,
    'catalogPage' => $catalogPage,
    'oeuvreId' => $oeuvreId,
    'saved' => isset($_GET['saved']) && (string) $_GET['saved'] === '1',
    'saveError' => $saveError,
    'posterUploadError' => $posterUploadError,
    'posterUploaded' => isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1',
    'editOpen' => $editOpen,
    'posterUploadOpen' => $posterUploadOpen,
    'hasTmdbKey' => TmdbConfig::hasApiKey(),
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
    'returnPage' => 'oeuvre',
    'currentTmdbId' => (int) ($oeuvre['tmdb_id'] ?? 0),
    'currentTmdbMediaType' => (string) ($oeuvre['tmdb_media_type'] ?? ''),
    'currentTmdbTvKind' => (string) ($oeuvre['tmdb_tv_kind'] ?? ''),
    'updated' => isset($_GET['updated']) && (string) $_GET['updated'] === '1',
    'mergeMessage' => $mergeMessage,
    'mergeError' => $mergeError,
]);
