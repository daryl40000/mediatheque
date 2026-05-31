<?php
/**
 * Mes magazines — liste des séries (PC Jeux, Joystick…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines.php');

if (!MagazineRepository::isAvailable()) {
    View::render('magazines', [
        'pageTitle' => \Moncine\MediaContext::navLabels()['collection'],
        'seriesList' => [],
        'totalCount' => 0,
        'query' => '',
        'sortBy' => 'titre',
        'sortDir' => 'asc',
        'moduleError' => 'Le module magazines n’est pas encore disponible. Rechargez la page dans quelques secondes.',
    ]);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$seriesList = $repo->listSeriesInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::COLLECTION,
    $sortBy,
    $sortDir,
    $query
);

View::render('magazines', [
    'pageTitle' => \Moncine\MediaContext::navLabels()['collection'],
    'seriesList' => $seriesList,
    'totalCount' => count($seriesList),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => '',
]);
