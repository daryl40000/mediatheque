<?php
/**
 * Fiche catalogue — album BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessCatalogAvailable();

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
    View::render('oeuvre-bd', [
        'pageTitle' => 'Album introuvable',
        'album' => null,
        'library' => null,
        'libraryBibId' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
        'catalogSearch' => $catalogSearch,
        'catalogSort' => $catalogSort,
        'catalogDir' => $catalogDir,
        'catalogPage' => $catalogPage,
        'oeuvreId' => $oeuvreId,
    ]);
    exit;
}

$oeuvre = $detail['oeuvre'];
$domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
if ($domain !== MediaDomain::BD) {
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

$repo = new BdRepository();
$album = BdRepository::isAvailable() ? $repo->findCatalogByOeuvreId($oeuvreId) : null;

$library = $detail['library'];
$libraryBibId = null;
if ($library !== null) {
    $libraryBibId = $repo->findLibraryBibIdForCatalogOeuvre(
        $oeuvreId,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    );
}

$oeuvreNav = CatalogAdmin::canAccess()
    ? $admin->getOeuvreNavigation($oeuvreId, $catalogSearch, $catalogSort, $catalogDir)
    : null;

View::render('oeuvre-bd', [
    'pageTitle' => (string) ($album['display_titre'] ?? $oeuvre['titre'] ?? 'Album BD'),
    'album' => $album,
    'oeuvre' => $oeuvre,
    'library' => $library,
    'libraryBibId' => $libraryBibId,
    'libraryCount' => (int) ($detail['library_count'] ?? 0),
    'catalogListContext' => $catalogListContext,
    'oeuvreNav' => $oeuvreNav,
    'catalogueBackUrl' => $catalogueBackUrl,
    'catalogSearch' => $catalogSearch,
    'catalogSort' => $catalogSort,
    'catalogDir' => $catalogDir,
    'catalogPage' => $catalogPage,
    'oeuvreId' => $oeuvreId,
    'kindChoices' => BdKind::choices(),
    'supportChoices' => BdPhysicalSupport::choices(),
]);
