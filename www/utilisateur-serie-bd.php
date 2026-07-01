<?php
/**
 * Tomes d’une série BD sur le profil public (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\BdSeriesMetadata;
use Moncine\BdKind;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\SeriesRepository;
use Moncine\UserProfile;
use Moncine\UserPublicProfileService;
use Moncine\View;

$viewerId = Auth::currentUserId();
if ($viewerId <= 0) {
    header('Location: /connexion.php');
    exit;
}

$targetUserId = max(0, (int) ($_GET['id'] ?? 0));
$seriesId = max(0, (int) ($_GET['series_id'] ?? 0));
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));
$sortBy = (string) ($_GET['sort'] ?? 'tome');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$listMode = $statut === LibraryStatut::WISHLIST ? 'envies' : 'collection';

$profile = new UserPublicProfileService();
$access = $profile->canViewBdSeries($viewerId, $targetUserId, $seriesId);

if ($access !== true) {
    http_response_code($seriesId <= 0 || str_contains((string) $access, 'introuvable') ? 404 : 403);
    View::render('utilisateur-serie-bd', [
        'pageTitle' => 'Série BD',
        'profileUser' => null,
        'accessDenied' => (string) $access,
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::BD,
        'pageMediaDomain' => MediaDomain::BD,
        'series' => null,
        'tomes' => [],
        'listMode' => $listMode,
        'statut' => $statut,
    ]);
    exit;
}

$user = $profile->findPublicUser($targetUserId);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
if ($user === null || $series === null) {
    http_response_code(404);
    View::render('utilisateur-serie-bd', [
        'pageTitle' => 'Série introuvable',
        'profileUser' => null,
        'accessDenied' => 'Série introuvable.',
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::BD,
        'pageMediaDomain' => MediaDomain::BD,
        'series' => null,
        'tomes' => [],
        'listMode' => $listMode,
        'statut' => $statut,
    ]);
    exit;
}

$tomes = $profile->listBdTomesForSeries(
    $targetUserId,
    $seriesId,
    $statut,
    $sortBy,
    $sortDir,
    $searchQuery
);
$displayName = UserProfile::displayName($user);
$kindLabel = BdKind::label(BdSeriesMetadata::kindFromSeries($series));

View::render('utilisateur-serie-bd', [
    'pageTitle' => (string) ($series['titre'] ?? 'Série') . ' — ' . $displayName,
    'profileUser' => $user,
    'accessDenied' => '',
    'targetUserId' => $targetUserId,
    'profileDomain' => MediaDomain::BD,
    'pageMediaDomain' => MediaDomain::BD,
    'series' => $series,
    'tomes' => $tomes,
    'listMode' => $listMode,
    'statut' => $statut,
    'kindLabel' => $kindLabel,
    'searchQuery' => $searchQuery,
    'hasSearch' => $searchQuery !== '',
    'totalCount' => count($tomes),
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'wideLayout' => true,
]);
