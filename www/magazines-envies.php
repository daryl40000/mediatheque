<?php
/**
 * Mes envies magazines — séries avec numéros souhaités.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaContext;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines-envies.php');

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$seriesList = MagazineRepository::isAvailable()
    ? $repo->listSeriesInLibrary($userId, $foyerId, LibraryStatut::WISHLIST, $sortBy, $sortDir, $query)
    : [];

View::render('magazines-envies', [
    'pageTitle' => MediaContext::navLabels()['wishlist'],
    'seriesList' => $seriesList,
    'totalCount' => count($seriesList),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'moduleError' => MagazineRepository::isAvailable() ? '' : 'Le module magazines n’est pas encore disponible. Rechargez la page.',
]);
