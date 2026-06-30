<?php
/**
 * Liste lecture seule partagée — jeux vidéo (collection foyer ou envies personnelles).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionViewMode;
use Moncine\FoyerRepository;
use Moncine\GameListFilter;
use Moncine\MediaDomain;
use Moncine\ShareLinkGameRepository;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\UserProfile;
use Moncine\UtilisateurRepository;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::JEU) {
    header('Location: ' . ShareLinkService::collectionUrl($rawToken, [], MediaDomain::FILM));
    exit;
}

if ($link === null) {
    http_response_code(404);
    View::render('partage-jeux', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'link' => null,
        'games' => [],
        'ownerLabel' => '',
        'scopeLabel' => '',
        'rawToken' => '',
    ]);
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? ''));
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? ''));
$listFilter = GameListFilter::fromQuery($_GET);

$games = (new ShareLinkGameRepository())->findAllForLink($link, $sortBy, $sortDir, $query, $listFilter);

$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$owner = (new UtilisateurRepository())->findById((int) ($link['user_id'] ?? 0));
$ownerLabel = $owner !== null ? UserProfile::displayName($owner) : 'Un membre Moncine';
$scopeLabel = ShareLinkScope::label($scope, MediaDomain::JEU);
if ($scope === ShareLinkScope::COLLECTION) {
    $foyerId = (int) ($link['foyer_id'] ?? 0);
    $foyer = $foyerId > 0 ? (new FoyerRepository())->findById($foyerId) : null;
    if ($foyer !== null && trim((string) ($foyer['nom'] ?? '')) !== '') {
        $scopeLabel .= ' — ' . (string) $foyer['nom'];
    }
}

View::render('partage-jeux', [
    'layout' => false,
    'wideLayout' => true,
    'pageTitle' => $scopeLabel,
    'link' => $link,
    'games' => $games,
    'ownerLabel' => $ownerLabel,
    'scopeLabel' => $scopeLabel,
    'rawToken' => $rawToken,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'query' => $query,
    'viewMode' => $viewMode,
    'listFilter' => $listFilter,
    'searched' => $query !== '' || $listFilter->isActive(),
    'totalCount' => count($games),
]);
