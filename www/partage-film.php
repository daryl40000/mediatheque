<?php
/**
 * Fiche film lecture seule pour un visiteur (lien de partage).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\OeuvreEanRepository;
use Moncine\ShareLinkFilmRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\WishlistTargetRepository;
use Moncine\SupportPhysique;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$filmId = (int) ($_GET['id'] ?? 0);

$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link === null) {
    http_response_code(404);
    View::render('partage-film', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'film' => null,
        'rawToken' => '',
        'listUrl' => '/partage.php',
        'scopeLabel' => '',
        'catalogEan' => null,
    ]);
    exit;
}

$film = (new ShareLinkFilmRepository())->findByIdForLink($link, $filmId);
$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$scopeLabel = ShareLinkScope::label($scope);

$wishlistTargets = [];
if (
    $film !== null
    && $scope === ShareLinkScope::WISHLIST
    && WishlistTargetRepository::tableExists()
) {
    $wishlistTargets = (new WishlistTargetRepository())->listForBibliothequeId($filmId);
}

$listContext = ShareLinkService::collectionQueryParams(
    trim((string) ($_GET['q'] ?? '')),
    (string) ($_GET['sort'] ?? 'titre'),
    (string) ($_GET['dir'] ?? 'asc'),
    (string) ($_GET['kind'] ?? ''),
    (string) ($_GET['view'] ?? '')
);
$listUrl = ShareLinkService::listBackUrl($rawToken, $listContext);

$catalogEan = null;
if ($film !== null && OeuvreEanRepository::tableExists()) {
    $oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
    $support = (string) ($film['support_physique'] ?? '');
    if ($oeuvreId > 0) {
        $eanRow = (new OeuvreEanRepository())->findForOeuvreAndSupport($oeuvreId, $support);
        if ($eanRow !== null) {
            $catalogEan = (string) ($eanRow['ean'] ?? '');
        }
    }
}

if ($film === null) {
    http_response_code(404);
}

View::render('partage-film', [
    'layout' => false,
    'pageTitle' => $film !== null ? (string) ($film['titre'] ?? 'Film') : 'Film introuvable',
    'film' => $film,
    'rawToken' => $rawToken,
    'listUrl' => $listUrl,
    'scopeLabel' => $scopeLabel,
    'catalogEan' => $catalogEan,
    'supportLabel' => $film !== null ? SupportPhysique::label((string) ($film['support_physique'] ?? '')) : '',
    'scope' => $scope,
    'wishlistTargets' => $wishlistTargets,
]);
