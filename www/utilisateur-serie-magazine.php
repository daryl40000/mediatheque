<?php
/**
 * Numéros d’une série magazine sur le profil public d’un utilisateur (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\PublicationType;
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
$sortBy = (string) ($_GET['sort'] ?? 'numero_ordre');
$sortDir = (string) ($_GET['dir'] ?? 'desc');
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$possessionFilter = MagazineRepository::normalizePossessionFilter((string) ($_GET['possession'] ?? 'all'));
$listMode = $statut === LibraryStatut::WISHLIST ? 'envies' : 'collection';

$profile = new UserPublicProfileService();
$access = $profile->canViewMagazineSeries($viewerId, $targetUserId, $seriesId);

if ($access !== true) {
    http_response_code($seriesId <= 0 || str_contains((string) $access, 'introuvable') ? 404 : 403);
    View::render('utilisateur-serie-magazine', [
        'pageTitle' => 'Série magazine',
        'profileUser' => null,
        'accessDenied' => (string) $access,
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::MAGAZINE,
        'pageMediaDomain' => MediaDomain::MAGAZINE,
        'series' => null,
        'issues' => [],
        'listMode' => $listMode,
        'statut' => $statut,
    ]);
    exit;
}

$user = $profile->findPublicUser($targetUserId);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
if ($user === null || $series === null) {
    http_response_code(404);
    View::render('utilisateur-serie-magazine', [
        'pageTitle' => 'Série introuvable',
        'profileUser' => null,
        'accessDenied' => 'Série introuvable.',
        'targetUserId' => $targetUserId,
        'profileDomain' => MediaDomain::MAGAZINE,
        'pageMediaDomain' => MediaDomain::MAGAZINE,
        'series' => null,
        'issues' => [],
        'listMode' => $listMode,
        'statut' => $statut,
    ]);
    exit;
}

$hasSearch = $searchQuery !== '';
$perPage = MagazineRepository::ISSUES_PER_PAGE;
$listTotal = $profile->countMagazineIssuesForSeries(
    $targetUserId,
    $seriesId,
    $statut,
    $searchQuery,
    $possessionFilter
);
$totalPages = max(1, (int) ceil($listTotal / $perPage));
$page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;

$issues = $profile->listMagazineIssuesForSeries(
    $targetUserId,
    $seriesId,
    $statut,
    $sortBy,
    $sortDir,
    $searchQuery,
    $possessionFilter,
    $perPage,
    $offset
);
$totalAllIssues = $profile->countMagazineIssuesForSeries($targetUserId, $seriesId, $statut, $searchQuery);
$displayName = UserProfile::displayName($user);

View::render('utilisateur-serie-magazine', [
    'pageTitle' => (string) ($series['titre'] ?? 'Série') . ' — ' . $displayName,
    'profileUser' => $user,
    'accessDenied' => '',
    'targetUserId' => $targetUserId,
    'profileDomain' => MediaDomain::MAGAZINE,
    'pageMediaDomain' => MediaDomain::MAGAZINE,
    'series' => $series,
    'issues' => $issues,
    'listMode' => $listMode,
    'statut' => $statut,
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
    'searchQuery' => $searchQuery,
    'hasSearch' => $hasSearch,
    'totalAllIssues' => $totalAllIssues,
    'filteredCount' => $listTotal,
    'possessionFilter' => $possessionFilter,
    'totalWithPossessionFilter' => $possessionFilter !== MagazineRepository::POSSESSION_ALL ? $listTotal : $totalAllIssues,
    'page' => $page,
    'totalPages' => $totalPages,
    'perPage' => $perPage,
    'listTotal' => $listTotal,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'wideLayout' => true,
]);
