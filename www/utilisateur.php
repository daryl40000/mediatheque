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
use Moncine\MediaDomain;
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
$profileDomain = MediaDomain::normalize((string) ($_GET['domain'] ?? MediaDomain::FILM));

$profile = new UserPublicProfileService();
$access = $profile->canView($viewerId, $targetUserId);

$emptyRenderData = static function (string $accessDenied, int $httpCode = 403) use (
    $targetUserId,
    $sortBy,
    $sortDir,
    $profileDomain
): array {
    http_response_code($httpCode);

    return [
        'pageTitle' => 'Profil utilisateur',
        'profileUser' => null,
        'accessDenied' => $accessDenied,
        'isSelf' => false,
        'profileDomain' => $profileDomain,
        'pageMediaDomain' => $profileDomain,
        'stats' => [],
        'lastViewed' => [],
        'lastNoted' => [],
        'lastCollection' => [],
        'lastWishlist' => [],
        'listFilms' => [],
        'listGames' => [],
        'listMagazineSeries' => [],
        'listViewings' => [],
        'listMode' => '',
        'listTitle' => '',
        'targetUserId' => $targetUserId,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'yearFilter' => null,
        'profileDomainImplemented' => MediaDomain::isCollectionImplemented($profileDomain),
    ];
};

if ($access !== true) {
    View::render('utilisateur', $emptyRenderData((string) $access, $targetUserId <= 0 ? 404 : 403));
    exit;
}

$user = $profile->findPublicUser($targetUserId);
if ($user === null) {
    View::render('utilisateur', $emptyRenderData('Utilisateur introuvable.', 404));
    exit;
}

$isSelf = $viewerId === $targetUserId;
$displayName = UserProfile::displayName($user);
$profileNav = MediaDomain::navLabels($profileDomain);
$profileDomainImplemented = MediaDomain::isCollectionImplemented($profileDomain);
$isMagazineProfile = MediaDomain::isMagazine($profileDomain);
$isGameProfile = MediaDomain::isGame($profileDomain);

$areFriends = false;
if (!$isSelf && FriendshipRepository::isAvailable()) {
    $areFriends = (new FriendshipRepository())->areFriends($viewerId, $targetUserId);
}

$listFilms = [];
$listGames = [];
$listMagazineSeries = [];
$listViewings = [];
$listMode = '';
$listTitle = '';
$yearFilter = null;
$anneeParam = (int) ($_GET['annee'] ?? 0);
if ($anneeParam > 0) {
    $yearFilter = $anneeParam;
}

if ($profileDomainImplemented) {
    if ($liste === 'collection') {
        $listMode = 'collection';
        $listTitle = $isMagazineProfile || $isGameProfile
            ? $profileNav['collection'] . ' — ' . $displayName
            : 'Films de ' . $displayName;
        if ($isMagazineProfile) {
            $listMagazineSeries = $profile->listCollection($targetUserId, $sortBy, $sortDir, $profileDomain);
        } elseif ($isGameProfile) {
            $listGames = $profile->listCollection($targetUserId, $sortBy, $sortDir, $profileDomain);
        } else {
            $listFilms = $profile->listCollection($targetUserId, $sortBy, $sortDir, $profileDomain);
        }
    } elseif ($liste === 'envies') {
        $listMode = 'envies';
        $listTitle = $isMagazineProfile || $isGameProfile
            ? $profileNav['wishlist'] . ' — ' . $displayName
            : 'Envies de ' . $displayName;
        if ($isMagazineProfile) {
            $listMagazineSeries = $profile->listWishlist($targetUserId, $sortBy, $sortDir, $profileDomain);
        } elseif ($isGameProfile) {
            $listGames = $profile->listWishlist($targetUserId, $sortBy, $sortDir, $profileDomain);
        } else {
            $listFilms = $profile->listWishlist($targetUserId, $sortBy, $sortDir, $profileDomain);
        }
    } elseif ($liste === 'vus' && !$isMagazineProfile && !$isGameProfile) {
        $listMode = 'vus';
        $listTitle = $yearFilter !== null
            ? 'Films vus en ' . $yearFilter . ' — ' . $displayName
            : 'Films vus — ' . $displayName;
        $viewSort = in_array($sortBy, ['date', 'titre', 'note'], true) ? $sortBy : 'date';
        $listViewings = $profile->listViewingHistory($targetUserId, $viewSort, $sortDir, $yearFilter);
    }
}

$loanUi = [
    'activeLoans' => [],
    'myRequests' => [],
    'reservedByOthers' => [],
];
if ($listMode === 'collection' && !$isSelf && $areFriends && !$isMagazineProfile && !$isGameProfile) {
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
    'profileDomain' => $profileDomain,
    'profileNav' => $profileNav,
    'profileDomainImplemented' => $profileDomainImplemented,
    'pageMediaDomain' => $profileDomain,
    'stats' => $listMode === '' && $profileDomainImplemented ? $profile->getStats($targetUserId, $profileDomain) : [],
    'lastViewed' => $listMode === '' && !$isMagazineProfile && !$isGameProfile
        ? $profile->lastViewedFilms($targetUserId, 5)
        : [],
    'lastNoted' => $listMode === '' && $isGameProfile
        ? $profile->lastNotedGames($targetUserId, 5)
        : [],
    'lastCollection' => $listMode === '' && $profileDomainImplemented
        ? $profile->lastCollectionFilms($targetUserId, 5, $profileDomain)
        : [],
    'lastWishlist' => $listMode === '' && $profileDomainImplemented
        ? $profile->lastWishlistFilms($targetUserId, 5, $profileDomain)
        : [],
    'listFilms' => $listFilms,
    'listGames' => $listGames,
    'listMagazineSeries' => $listMagazineSeries,
    'listViewings' => $listViewings,
    'listMode' => $listMode,
    'listTitle' => $listTitle,
    'targetUserId' => $targetUserId,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'yearFilter' => $yearFilter,
    'wideLayout' => $listMode !== '',
]);
