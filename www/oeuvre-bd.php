<?php
/**
 * Fiche catalogue — album BD / manga (administration).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\MediaDomain;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_GET['id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_GET);
$catalogueBackUrl = $catalogListContext->backUrl();

$admin = new CatalogAdmin();
$detail = $oeuvreId > 0 ? $admin->findOeuvreDetail($oeuvreId) : null;

if ($detail === null) {
    View::render('oeuvre-bd', [
        'pageTitle' => 'Album introuvable',
        'album' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
    ]);
    exit;
}

$oeuvre = $detail['oeuvre'];
$domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
if ($domain !== MediaDomain::BD) {
    header('Location: ' . View::catalogOeuvreDetailUrl(
        $oeuvreId,
        $domain,
        $catalogListContext->search(),
        $catalogListContext->sortBy(),
        $catalogListContext->sortDir(),
        $catalogListContext->page()
    ));
    exit;
}

$repo = new BdRepository();
$album = BdRepository::isAvailable() ? $repo->findCatalogByOeuvreId($oeuvreId) : null;

View::render('oeuvre-bd', [
    'pageTitle' => (string) ($album['display_titre'] ?? $oeuvre['titre'] ?? 'Album BD'),
    'album' => $album,
    'oeuvre' => $oeuvre,
    'libraryCount' => (int) ($detail['library_count'] ?? 0),
    'catalogListContext' => $catalogListContext,
    'catalogueBackUrl' => $catalogueBackUrl,
    'kindChoices' => BdKind::choices(),
    'supportChoices' => BdPhysicalSupport::choices(),
]);
