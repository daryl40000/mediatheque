<?php
/**
 * Liste des films « Mes envies » (wishlist) — personnelle ou agrégée du groupe.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::renderCollectionPageOrExit();

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\GroupWishlistRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\SupportPhysique;
use Moncine\UserContext;
use Moncine\View;
use Moncine\WishlistScope;
use Moncine\WishlistTargetRepository;

if (MediaDomain::isMagazine(MediaContext::current())) {
    header('Location: ' . MediaDomain::wishlistPath(MediaDomain::MAGAZINE));
    exit;
}

if (!(new FilmRepository())->usesCatalogModel()) {
    header('Location: /films.php');
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? $_POST['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$scope = WishlistScope::normalize((string) ($_GET['scope'] ?? $_POST['scope'] ?? WishlistScope::MINE));

$foyerId = UserContext::currentFoyerId();
$groupWishlist = new GroupWishlistRepository();
$canShowGroup = $groupWishlist->canShowGroupView($foyerId);
if ($scope === WishlistScope::GROUP && !$canShowGroup) {
    $scope = WishlistScope::MINE;
}

if ($scope === WishlistScope::GROUP && !isset($_GET['sort']) && !isset($_POST['sort'])) {
    $sortBy = 'votes';
    $sortDir = 'desc';
}

$repo = new FilmRepository();
$userId = UserContext::currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectUrl = View::wishlistUrl($query, $sortBy, $sortDir, $scope);
    Csrf::rejectUnlessValid($_POST, $redirectUrl);

    $action = (string) ($_POST['action'] ?? 'promote');

    if ($action === 'vote') {
        $oeuvreId = max(0, (int) ($_POST['oeuvre_id'] ?? 0));
        if ($oeuvreId <= 0) {
            header('Location: ' . $redirectUrl . '&vote_error=' . rawurlencode('Œuvre invalide.'));
            exit;
        }
        $result = $repo->addFromCatalogOeuvre($oeuvreId, LibraryStatut::WISHLIST);
        if (!is_int($result)) {
            header('Location: ' . $redirectUrl . '&vote_error=' . rawurlencode((string) $result));
            exit;
        }
        header('Location: ' . $redirectUrl . '&vote_ok=1');
        exit;
    }

    $filmId = (int) ($_POST['film_id'] ?? 0);
    $supportRaw = (string) ($_POST['support_physique'] ?? '');
    $supportKey = SupportPhysique::normalize($supportRaw);
    $targetId = (int) ($_POST['wishlist_target_id'] ?? 0);
    $wishlistTargetId = $targetId > 0 ? $targetId : null;

    if ($filmId <= 0) {
        header('Location: ' . $redirectUrl . '&promote_error=' . rawurlencode('Film invalide.'));
        exit;
    }

    if (!$repo->promoteToCollection($filmId, $supportKey, '', $wishlistTargetId)) {
        header('Location: ' . $redirectUrl . '&promote_error=' . rawurlencode('Impossible d’ajouter ce film à vos films.'));
        exit;
    }

    $film = $repo->findById($filmId);
    $titre = $film !== null ? (string) ($film['titre'] ?? '') : '';
    $params = ['promoted' => '1'];
    if ($titre !== '') {
        $params['promoted_title'] = $titre;
    }
    header('Location: /films.php?' . http_build_query($params));
    exit;
}

$isGroupScope = $scope === WishlistScope::GROUP;
if ($isGroupScope) {
    $films = $groupWishlist->findAggregated($foyerId, $userId, $sortBy, $sortDir, $query);
    $totalCount = $groupWishlist->countDistinctOeuvres($foyerId);
} else {
    $films = $repo->findAllWishlist($sortBy, $sortDir, $query);
    $totalCount = $repo->countWishlist();
}

$group = $canShowGroup ? (new \Moncine\FamilyGroupService())->findGroupForUser($userId) : null;

$wishlistTargetsByFilmId = [];
if (!$isGroupScope && WishlistTargetRepository::tableExists() && $films !== []) {
    $ids = array_map(static fn (array $f): int => (int) ($f['id'] ?? 0), $films);
    $wishlistTargetsByFilmId = (new WishlistTargetRepository())->mapByBibliothequeIds($ids);
}

View::render('souhaits', [
    'pageTitle' => LibraryStatut::label(LibraryStatut::WISHLIST),
    'films' => $films,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'query' => $query,
    'searched' => $query !== '',
    'totalCount' => $totalCount,
    'scope' => $scope,
    'canShowGroup' => $canShowGroup,
    'groupName' => (string) ($group['nom'] ?? ''),
    'isGroupScope' => $isGroupScope,
    'wishlistTargetsByFilmId' => $wishlistTargetsByFilmId,
]);
