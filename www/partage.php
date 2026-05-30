<?php
/**
 * Liste lecture seule partagée (collection foyer ou envies personnelles).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionViewMode;
use Moncine\ContentKindFilter;
use Moncine\FoyerRepository;
use Moncine\ShareLinkFilmRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\WishlistTargetRepository;
use Moncine\UserProfile;
use Moncine\UtilisateurRepository;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link === null) {
    http_response_code(404);
    View::render('partage', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'link' => null,
        'films' => [],
        'ownerLabel' => '',
        'scopeLabel' => '',
        'rawToken' => '',
    ]);
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? ''));
$kindFilter = ContentKindFilter::normalize((string) ($_GET['kind'] ?? ''));
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? ''));

$films = (new ShareLinkFilmRepository())->findAllForLink($link, $sortBy, $sortDir, $query, $kindFilter);

$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$showWishlistTargets = $scope === ShareLinkScope::WISHLIST && WishlistTargetRepository::tableExists();
$wishlistTargetsByFilmId = [];
if ($showWishlistTargets && $films !== []) {
    $ids = array_map(static fn (array $f): int => (int) ($f['id'] ?? 0), $films);
    $wishlistTargetsByFilmId = (new WishlistTargetRepository())->mapByBibliothequeIds($ids);
}

$owner = (new UtilisateurRepository())->findById((int) ($link['user_id'] ?? 0));
$ownerLabel = $owner !== null ? UserProfile::displayName($owner) : 'Un membre Moncine';
$scopeLabel = ShareLinkScope::label($scope);
if ($scope === ShareLinkScope::COLLECTION) {
    $foyerId = (int) ($link['foyer_id'] ?? 0);
    $foyer = $foyerId > 0 ? (new FoyerRepository())->findById($foyerId) : null;
    if ($foyer !== null && trim((string) ($foyer['nom'] ?? '')) !== '') {
        $scopeLabel .= ' — ' . (string) $foyer['nom'];
    }
}

View::render('partage', [
    'layout' => false,
    'wideLayout' => true,
    'pageTitle' => $scopeLabel,
    'link' => $link,
    'films' => $films,
    'ownerLabel' => $ownerLabel,
    'scopeLabel' => $scopeLabel,
    'rawToken' => $rawToken,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'query' => $query,
    'kindFilter' => $kindFilter,
    'viewMode' => $viewMode,
    'searched' => $query !== '',
    'totalCount' => count($films),
    'showWishlistTargets' => $showWishlistTargets,
    'wishlistTargetsByFilmId' => $wishlistTargetsByFilmId,
]);
