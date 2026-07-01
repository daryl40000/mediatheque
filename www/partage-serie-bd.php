<?php
/**
 * Tomes d’une série BD partagée (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdSeriesMetadata;
use Moncine\CollectionViewMode;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\SeriesRepository;
use Moncine\ShareLinkBdRepository;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\UserProfile;
use Moncine\UtilisateurRepository;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$seriesId = max(0, (int) ($_GET['series_id'] ?? 0));
$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::BD) {
    header('Location: ' . ShareLinkService::collectionUrl($rawToken, [], MediaDomain::FILM));
    exit;
}

$shareRepo = new ShareLinkBdRepository();

if ($link === null || $seriesId <= 0 || !$shareRepo->seriesVisibleForLink($link, $seriesId)) {
    http_response_code(404);
    View::render('partage-serie-bd', [
        'layout' => false,
        'pageTitle' => 'Série introuvable',
        'link' => null,
        'series' => null,
        'tomes' => [],
        'rawToken' => '',
        'listUrl' => '/partage-bd.php',
        'scopeLabel' => '',
    ]);
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? 'tome');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$viewMode = CollectionViewMode::normalizeBdSeries((string) ($_GET['view'] ?? ''));
$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$statut = $scope === ShareLinkScope::WISHLIST ? LibraryStatut::WISHLIST : LibraryStatut::COLLECTION;

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
$tomes = $shareRepo->listTomesForSeriesForLink($link, $seriesId, $sortBy, $sortDir, $searchQuery);

$listContext = ShareLinkService::collectionQueryParams($searchQuery, $sortBy, $sortDir, '', $viewMode);
$listUrl = ShareLinkService::listBackUrl($rawToken, $listContext, MediaDomain::BD);

$owner = (new UtilisateurRepository())->findById((int) ($link['user_id'] ?? 0));
$ownerLabel = $owner !== null ? UserProfile::displayName($owner) : 'Un membre Moncine';
$scopeLabel = ShareLinkScope::label($scope, MediaDomain::BD);
$kindLabel = $series !== null ? BdKind::label(BdSeriesMetadata::kindFromSeries($series)) : '';

View::render('partage-serie-bd', [
    'layout' => false,
    'wideLayout' => true,
    'pageTitle' => ($series['titre'] ?? 'Série') . ' — ' . $scopeLabel,
    'link' => $link,
    'series' => $series,
    'tomes' => $tomes,
    'rawToken' => $rawToken,
    'listUrl' => $listUrl,
    'scopeLabel' => $scopeLabel,
    'ownerLabel' => $ownerLabel,
    'statut' => $statut,
    'kindLabel' => $kindLabel,
    'searchQuery' => $searchQuery,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'viewMode' => $viewMode,
    'listContext' => $listContext,
    'totalCount' => count($tomes),
]);
