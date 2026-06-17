<?php
/**
 * Fiche catalogue — numéro de magazine (administration).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_GET['id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_GET);
$catalogSearch = $catalogListContext->search();
$catalogSort = $catalogListContext->sortBy();
$catalogDir = $catalogListContext->sortDir();
$catalogPage = $catalogListContext->page();
$catalogueBackUrl = $catalogListContext->backUrl();

$admin = new CatalogAdmin();
$detail = $oeuvreId > 0 ? $admin->findOeuvreDetail($oeuvreId) : null;

if ($detail === null) {
    View::render('oeuvre-magazine', [
        'pageTitle' => 'Numéro introuvable',
        'issue' => null,
        'library' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
        'dateLabel' => '—',
    ]);
    exit;
}

$oeuvre = $detail['oeuvre'];
$domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
if ($domain !== MediaDomain::MAGAZINE) {
    header('Location: ' . View::catalogOeuvreDetailUrl(
        $oeuvreId,
        $domain,
        $catalogSearch,
        $catalogSort,
        $catalogDir,
        $catalogPage
    ));
    exit;
}

$repo = new MagazineRepository();
$issue = MagazineRepository::isAvailable() ? $repo->findCatalogIssueByOeuvreId($oeuvreId) : null;

if ($issue === null) {
    View::render('oeuvre-magazine', [
        'pageTitle' => 'Numéro introuvable',
        'issue' => null,
        'library' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
        'dateLabel' => '—',
    ]);
    exit;
}

$saveError = (string) ($_GET['save_error'] ?? '');
$posterUploadError = (string) ($_GET['poster_error'] ?? '');
$editOpen = isset($_GET['edit']) || $saveError !== '';
$posterUploadOpen = $posterUploadError !== '';

if (isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1') {
    $refreshed = $repo->findCatalogIssueByOeuvreId($oeuvreId);
    if ($refreshed !== null) {
        $issue = $refreshed;
    }
}

$oeuvreNav = $admin->getOeuvreNavigation($oeuvreId, $catalogSearch, $catalogSort, $catalogDir);
$library = $detail['library'];
$libraryBibId = null;
if ($library !== null) {
    $libraryBibId = $repo->findLibraryBibIdForCatalogOeuvre(
        $oeuvreId,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    );
}

$dateLabel = PublicationType::formatParutionDate(
    (string) ($issue['publication_type'] ?? ''),
    (string) ($issue['date_parution'] ?? '')
);

View::render('oeuvre-magazine', [
    'pageTitle' => (string) ($issue['titre'] ?? 'Numéro catalogue'),
    'catalogListContext' => $catalogListContext,
    'oeuvreNav' => $oeuvreNav,
    'issue' => $issue,
    'library' => $library,
    'libraryBibId' => $libraryBibId,
    'libraryCount' => (int) $detail['library_count'],
    'catalogueBackUrl' => $catalogueBackUrl,
    'catalogSearch' => $catalogSearch,
    'catalogSort' => $catalogSort,
    'catalogDir' => $catalogDir,
    'catalogPage' => $catalogPage,
    'oeuvreId' => $oeuvreId,
    'dateLabel' => $dateLabel,
    'saved' => isset($_GET['saved']) && (string) $_GET['saved'] === '1',
    'saveError' => $saveError,
    'posterUploadError' => $posterUploadError,
    'posterUploaded' => isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1',
    'editOpen' => $editOpen,
    'posterUploadOpen' => $posterUploadOpen,
]);
