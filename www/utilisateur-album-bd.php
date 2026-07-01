<?php
/**
 * Fiche d’un tome BD sur le profil public (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\BdPhysicalSupport;
use Moncine\BdPossession;
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
$bibId = max(0, (int) ($_GET['bib_id'] ?? 0));

$profile = new UserPublicProfileService();
$access = $profile->canViewBdTome($viewerId, $targetUserId, $bibId);

if ($access !== true) {
    http_response_code($bibId <= 0 || str_contains((string) $access, 'introuvable') ? 404 : 403);
    View::render('utilisateur-album-bd', [
        'pageTitle' => 'Tome BD',
        'profileUser' => null,
        'accessDenied' => (string) $access,
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::BD,
        'pageMediaDomain' => MediaDomain::BD,
        'tome' => null,
    ]);
    exit;
}

$user = $profile->findPublicUser($targetUserId);
$tome = $profile->findBdTomeForProfile($targetUserId, $bibId);
if ($user === null || $tome === null) {
    http_response_code(404);
    View::render('utilisateur-album-bd', [
        'pageTitle' => 'Tome introuvable',
        'profileUser' => null,
        'accessDenied' => 'Tome introuvable.',
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::BD,
        'pageMediaDomain' => MediaDomain::BD,
        'tome' => null,
    ]);
    exit;
}

$displayName = UserProfile::displayName($user);
$pageStatut = (string) ($tome['statut'] ?? \Moncine\LibraryStatut::COLLECTION);
$listMode = $pageStatut === \Moncine\LibraryStatut::WISHLIST ? 'envies' : 'collection';

View::render('utilisateur-album-bd', [
    'pageTitle' => (string) ($tome['display_titre'] ?? 'Tome') . ' — ' . $displayName,
    'profileUser' => $user,
    'accessDenied' => '',
    'targetUserId' => $targetUserId,
    'profileDomain' => MediaDomain::BD,
    'pageMediaDomain' => MediaDomain::BD,
    'tome' => $tome,
    'listMode' => $listMode,
    'possessionLabel' => BdPossession::possessionStatusLabel($tome),
    'supportLabel' => BdPhysicalSupport::label((string) ($tome['support_physique'] ?? '')),
]);
