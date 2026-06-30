<?php
/**
 * Mes BD / manga — liste des séries.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext('/bd.php');

if (MediaContext::current() !== MediaDomain::BD) {
    header('Location: ' . MediaDomain::collectionPath(MediaDomain::BD));
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();

if (!BdRepository::isAvailable()) {
    View::render('bd', [
        'pageTitle' => MediaContext::navLabels()['collection'],
        'seriesList' => [],
        'totalCount' => 0,
        'query' => $query,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'moduleError' => 'Le module BD n’est pas encore disponible. Rechargez la page dans quelques secondes.',
    ]);
    exit;
}

$seriesList = $repo->listSeriesInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::COLLECTION,
    $sortBy,
    $sortDir,
    $query
);

View::render('bd', [
    'pageTitle' => MediaContext::navLabels()['collection'],
    'seriesList' => $seriesList,
    'totalCount' => count($seriesList),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => '',
]);
