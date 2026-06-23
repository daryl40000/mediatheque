<?php
/**
 * Fiche jeu lecture seule pour un visiteur (lien de partage).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomain;
use Moncine\ShareLinkGameRepository;
use Moncine\ShareLinkRepository;
use Moncine\ShareLinkScope;
use Moncine\ShareLinkService;
use Moncine\View;

$rawToken = trim((string) ($_GET['t'] ?? ''));
$gameId = (int) ($_GET['id'] ?? 0);

$service = new ShareLinkService();
$link = $rawToken !== '' ? $service->resolve($rawToken) : null;

if ($link !== null && ShareLinkRepository::mediaDomainFromRow($link) !== MediaDomain::JEU) {
    header('Location: ' . ShareLinkService::filmUrl($rawToken, $gameId));
    exit;
}

if ($link === null) {
    http_response_code(404);
    View::render('partage-jeu', [
        'layout' => false,
        'pageTitle' => 'Lien invalide',
        'game' => null,
        'rawToken' => '',
        'listUrl' => '/partage-jeux.php',
        'scopeLabel' => '',
    ]);
    exit;
}

$game = (new ShareLinkGameRepository())->findByIdForLink($link, $gameId);
$scope = ShareLinkScope::normalize((string) ($link['scope'] ?? ''));
$scopeLabel = ShareLinkScope::label($scope, MediaDomain::JEU);

$listContext = ShareLinkService::collectionQueryParams(
    trim((string) ($_GET['q'] ?? '')),
    (string) ($_GET['sort'] ?? 'titre'),
    (string) ($_GET['dir'] ?? 'asc'),
    '',
    (string) ($_GET['view'] ?? '')
);
$listUrl = ShareLinkService::listBackUrl($rawToken, $listContext, MediaDomain::JEU);

if ($game === null) {
    http_response_code(404);
}

View::render('partage-jeu', [
    'layout' => false,
    'pageTitle' => $game !== null
        ? (string) ($game['display_titre'] ?? $game['titre'] ?? 'Jeu')
        : 'Jeu introuvable',
    'game' => $game,
    'rawToken' => $rawToken,
    'listUrl' => $listUrl,
    'scopeLabel' => $scopeLabel,
    'scope' => $scope,
]);
