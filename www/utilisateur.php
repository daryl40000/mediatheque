<?php
/**
 * Profil public d’un utilisateur (amis ou membres du groupe).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\FriendshipRepository;
use Moncine\LoanRepository;
use Moncine\LoanRequestRepository;
use Moncine\UserProfile;
use Moncine\UserPublicProfileService;
use Moncine\View;

$viewerId = Auth::currentUserId();
if ($viewerId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$targetUserId = max(0, (int) ($_GET['id'] ?? 0));
$liste = (string) ($_GET['liste'] ?? '');
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$profile = new UserPublicProfileService();
$access = $profile->canView($viewerId, $targetUserId);

if ($access !== true) {
    http_response_code($targetUserId <= 0 ? 404 : 403);
    View::render('utilisateur', [
        'pageTitle' => 'Profil utilisateur',
        'profileUser' => null,
        'accessDenied' => (string) $access,
        'isSelf' => false,
        'stats' => [],
        'lastViewed' => [],
        'lastCollection' => [],
        'lastWishlist' => [],
        'listFilms' => [],
        'listViewings' => [],
        'listMode' => '',
        'listTitle' => '',
        'targetUserId' => $targetUserId,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'yearFilter' => null,
    ]);
    exit;
}

$user = $profile->findPublicUser($targetUserId);
if ($user === null) {
    http_response_code(404);
    View::render('utilisateur', [
        'pageTitle' => 'Profil introuvable',
        'profileUser' => null,
        'accessDenied' => 'Utilisateur introuvable.',
        'isSelf' => false,
        'stats' => [],
        'lastViewed' => [],
        'lastCollection' => [],
        'lastWishlist' => [],
        'listFilms' => [],
        'listViewings' => [],
        'listMode' => '',
        'listTitle' => '',
        'targetUserId' => $targetUserId,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'yearFilter' => null,
    ]);
    exit;
}

$isSelf = $viewerId === $targetUserId;
$displayName = UserProfile::displayName($user);

$areFriends = false;
if (!$isSelf && FriendshipRepository::isAvailable()) {
    $areFriends = (new FriendshipRepository())->areFriends($viewerId, $targetUserId);
}

$listFilms = [];
$listViewings = [];
$listMode = '';
$listTitle = '';
$yearFilter = null;
$anneeParam = (int) ($_GET['annee'] ?? 0);
if ($anneeParam > 0) {
    $yearFilter = $anneeParam;
}

if ($liste === 'collection') {
    $listMode = 'collection';
    $listTitle = 'Films de ' . $displayName;
    $listFilms = $profile->listCollection($targetUserId, $sortBy, $sortDir);
} elseif ($liste === 'envies') {
    $listMode = 'envies';
    $listTitle = 'Envies de ' . $displayName;
    $listFilms = $profile->listWishlist($targetUserId, $sortBy, $sortDir);
} elseif ($liste === 'vus') {
    $listMode = 'vus';
    $listTitle = $yearFilter !== null
        ? 'Films vus en ' . $yearFilter . ' — ' . $displayName
        : 'Films vus — ' . $displayName;
    $viewSort = in_array($sortBy, ['date', 'titre', 'note'], true) ? $sortBy : 'date';
    $listViewings = $profile->listViewingHistory($targetUserId, $viewSort, $sortDir, $yearFilter);
}

$loanUi = [
    'activeLoans' => [],
    'myRequests' => [],
    'reservedByOthers' => [],
];
if ($listMode === 'collection' && !$isSelf && $areFriends) {
    if (LoanRepository::tableExists()) {
        $loanUi['activeLoans'] = (new LoanRepository())->mapActiveLoansByBibliothequeId($targetUserId);
    }
    if (LoanRequestRepository::tableExists()) {
        $reqRepo = new LoanRequestRepository();
        $loanUi['myRequests'] = $reqRepo->mapActiveRequestsForViewer($targetUserId, $viewerId);
        $loanUi['reservedByOthers'] = $reqRepo->mapReservedByOthers($targetUserId, $viewerId);
    }
}

View::render('utilisateur', [
    'pageTitle' => $listTitle !== '' ? $listTitle : $displayName,
    'profileUser' => $user,
    'accessDenied' => '',
    'isSelf' => $isSelf,
    'viewerId' => $viewerId,
    'areFriends' => $areFriends,
    'loanUi' => $loanUi,
    'stats' => $listMode === '' ? $profile->getStats($targetUserId) : [],
    'lastViewed' => $listMode === '' ? $profile->lastViewedFilms($targetUserId, 5) : [],
    'lastCollection' => $listMode === '' ? $profile->lastCollectionFilms($targetUserId, 5) : [],
    'lastWishlist' => $listMode === '' ? $profile->lastWishlistFilms($targetUserId, 5) : [],
    'listFilms' => $listFilms,
    'listViewings' => $listViewings,
    'listMode' => $listMode,
    'listTitle' => $listTitle,
    'targetUserId' => $targetUserId,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'yearFilter' => $yearFilter,
    'wideLayout' => $listMode !== '',
]);
