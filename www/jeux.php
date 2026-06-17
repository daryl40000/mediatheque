<?php
/**
 * Mes jeux vidéo — liste de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionViewMode;
use Moncine\GameListFilter;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/jeux.php');

if (MediaContext::current() !== MediaDomain::JEU) {
    header('Location: ' . MediaDomain::collectionPath(MediaDomain::JEU));
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? ''));
$listFilter = GameListFilter::fromQuery($_GET);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

if (!GameRepository::isAvailable()) {
    View::render('jeux', [
        'pageTitle' => MediaContext::navLabels()['collection'],
        'games' => [],
        'totalCount' => 0,
        'query' => $query,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'viewMode' => $viewMode,
        'listFilter' => $listFilter,
        'moduleError' => 'Le module jeux n’est pas encore disponible. Rechargez la page dans quelques secondes.',
    ]);
    exit;
}

$games = $repo->listInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::COLLECTION,
    $sortBy,
    $sortDir,
    $query,
    $listFilter
);

View::render('jeux', [
    'pageTitle' => MediaContext::navLabels()['collection'],
    'games' => $games,
    'totalCount' => count($games),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'viewMode' => $viewMode,
    'listFilter' => $listFilter,
    'moduleError' => '',
]);
