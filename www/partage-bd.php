<?php
/**
 * Liste lecture seule partagée — BD / manga (séries).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\FoyerRepository;
use Moncine\MediaDomain;
use Moncine\ShareLinkBdRepository;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\UserProfile;
use Moncine\UtilisateurRepository;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::BD) {
    $domain = ShareLinkRepository::mediaDomainFromRow($link);
    header('Location: ' . ShareLinkService::collectionUrl(
        $rawToken,
        [],
        $domain === MediaDomain::JEU ? MediaDomain::JEU : MediaDomain::FILM
    ));
    exit;
}

if ($link === null) {
    http_response_code(404);
    View::render('partage-bd', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'link' => null,
        'seriesList' => [],
        'ownerLabel' => '',
        'scopeLabel' => '',
        'rawToken' => '',
    ]);
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? ''));

$seriesList = (new ShareLinkBdRepository())->listSeriesForLink($link, $sortBy, $sortDir, $query);

$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$owner = (new UtilisateurRepository())->findById((int) ($link['user_id'] ?? 0));
$ownerLabel = $owner !== null ? UserProfile::displayName($owner) : 'Un membre Moncine';
$scopeLabel = ShareLinkScope::label($scope, MediaDomain::BD);
if ($scope === ShareLinkScope::COLLECTION) {
    $foyerId = (int) ($link['foyer_id'] ?? 0);
    $foyer = $foyerId > 0 ? (new FoyerRepository())->findById($foyerId) : null;
    if ($foyer !== null && trim((string) ($foyer['nom'] ?? '')) !== '') {
        $scopeLabel .= ' — ' . (string) $foyer['nom'];
    }
}

View::render('partage-bd', [
    'layout' => false,
    'wideLayout' => true,
    'pageTitle' => $scopeLabel,
    'link' => $link,
    'seriesList' => $seriesList,
    'ownerLabel' => $ownerLabel,
    'scopeLabel' => $scopeLabel,
    'rawToken' => $rawToken,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'query' => $query,
    'totalCount' => count($seriesList),
]);
