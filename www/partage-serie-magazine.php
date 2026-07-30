<?php
/**
 * Numéros d’une série magazine partagée (lecture seule).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomain;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\ShareLinkMagazineRepository;
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

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::MAGAZINE) {
    header('Location: ' . ShareLinkService::collectionUrl($rawToken, [], MediaDomain::FILM));
    exit;
}

$shareRepo = new ShareLinkMagazineRepository();
$listUrl = ShareLinkService::collectionUrl($rawToken, [], MediaDomain::MAGAZINE);

if ($link === null || $seriesId <= 0 || !$shareRepo->seriesVisibleForLink($link, $seriesId)) {
    http_response_code(404);
    View::render('partage-serie-magazine', [
        'layout' => false,
        'pageTitle' => 'Série introuvable',
        'link' => null,
        'series' => null,
        'issues' => [],
        'rawToken' => '',
        'listUrl' => '/partage-magazines.php',
        'scopeLabel' => '',
    ]);
    exit;
}

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
$sortBy = (string) ($_GET['sort'] ?? 'numero_ordre');
$sortDir = (string) ($_GET['dir'] ?? 'desc');
$query = trim((string) ($_GET['q'] ?? ''));
$issues = $shareRepo->listIssuesForSeriesForLink($link, $seriesId, $sortBy, $sortDir, $query);

$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$owner = (new UtilisateurRepository())->findById((int) ($link['user_id'] ?? 0));
$ownerLabel = $owner !== null ? UserProfile::displayName($owner) : 'Un membre Moncine';

View::render('partage-serie-magazine', [
    'layout' => false,
    'wideLayout' => true,
    'pageTitle' => (string) ($series['titre'] ?? 'Série'),
    'link' => $link,
    'series' => $series,
    'issues' => $issues,
    'rawToken' => $rawToken,
    'listUrl' => $listUrl,
    'ownerLabel' => $ownerLabel,
    'scopeLabel' => ShareLinkScope::label($scope, MediaDomain::MAGAZINE),
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
    'totalCount' => count($issues),
    'statut' => $scope === ShareLinkScope::WISHLIST
        ? \Moncine\LibraryStatut::WISHLIST
        : \Moncine\LibraryStatut::COLLECTION,
]);
