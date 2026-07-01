<?php
/**
 * Fiche tome BD lecture seule pour un visiteur (lien de partage).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPhysicalSupport;
use Moncine\BdPossession;
use Moncine\CollectionViewMode;
use Moncine\MediaDomain;
use Moncine\ShareLinkBdRepository;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$bibId = (int) ($_GET['id'] ?? 0);

$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::BD) {
    header('Location: ' . ShareLinkService::filmUrl($rawToken, $bibId));
    exit;
}

if ($link === null) {
    http_response_code(404);
    View::render('partage-album-bd', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'tome' => null,
        'rawToken' => '',
        'listUrl' => '/partage-bd.php',
        'scopeLabel' => '',
    ]);
    exit;
}

$tome = (new ShareLinkBdRepository())->findByBibIdForLink($link, $bibId);
$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$scopeLabel = ShareLinkScope::label($scope, MediaDomain::BD);

$listContext = ShareLinkService::collectionQueryParams(
    trim((string) ($_GET['q'] ?? '')),
    (string) ($_GET['sort'] ?? 'titre'),
    (string) ($_GET['dir'] ?? 'asc'),
    '',
    CollectionViewMode::normalizeBdSeries((string) ($_GET['view'] ?? ''))
);
$listUrl = ShareLinkService::listBackUrl($rawToken, $listContext, MediaDomain::BD);
$seriesId = $tome !== null ? (int) ($tome['series_id'] ?? 0) : 0;
$seriesUrl = $seriesId > 0
    ? ShareLinkService::bdSeriesUrl($rawToken, $seriesId, $listContext)
    : $listUrl;

if ($tome === null) {
    http_response_code(404);
}

View::render('partage-album-bd', [
    'layout' => false,
    'pageTitle' => $tome !== null
        ? (string) ($tome['display_titre'] ?? $tome['titre'] ?? 'Tome')
        : 'Tome introuvable',
    'tome' => $tome,
    'rawToken' => $rawToken,
    'listUrl' => $listUrl,
    'seriesUrl' => $seriesUrl,
    'scopeLabel' => $scopeLabel,
    'possessionLabel' => $tome !== null ? BdPossession::possessionStatusLabel($tome) : '',
    'supportLabel' => $tome !== null
        ? BdPhysicalSupport::label((string) ($tome['support_physique'] ?? ''))
        : '',
]);
