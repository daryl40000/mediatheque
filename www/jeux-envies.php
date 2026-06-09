<?php
/**
 * Mes envies jeux vidéo.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/jeux-envies.php');

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

if (!GameRepository::isAvailable()) {
    View::render('jeux-envies', [
        'pageTitle' => MediaContext::navLabels()['wishlist'],
        'games' => [],
        'totalCount' => 0,
        'query' => $query,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'moduleError' => 'Le module jeux n’est pas encore disponible.',
    ]);
    exit;
}

$games = $repo->listInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::WISHLIST,
    $sortBy,
    $sortDir,
    $query
);

View::render('jeux-envies', [
    'pageTitle' => MediaContext::navLabels()['wishlist'],
    'games' => $games,
    'totalCount' => count($games),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => '',
]);
