<?php
/**
 * Mes envies BD — séries souhaitées.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext('/bd-envies.php');

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();

$seriesList = BdRepository::isAvailable()
    ? $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::WISHLIST, $sortBy, $sortDir, $query)
    : [];

View::render('bd-envies', [
    'pageTitle' => MediaContext::navLabels()['wishlist'],
    'seriesList' => $seriesList,
    'totalCount' => count($seriesList),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => BdRepository::isAvailable() ? '' : 'Le module BD n’est pas encore disponible.',
]);
