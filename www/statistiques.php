<?php
/**
 * Page statistiques de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionStats;
use Moncine\GameCollectionStats;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();

if (MediaDomain::isMagazine(MediaContext::current())) {
    MediaDomainGuards::ensureMagazineContext('/statistiques.php');
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $repo = new MagazineRepository();
    $pdfStats = MagazineRepository::isAvailable()
        ? $repo->collectionPdfStats($userId, $foyerId)
        : ['count' => 0, 'total_bytes' => 0];

    View::render('statistiques-magazines', [
        'pageTitle' => MediaContext::navLabels()['stats'],
        'seriesCount' => MagazineRepository::isAvailable()
            ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0,
        'issueCount' => MagazineRepository::isAvailable()
            ? $repo->countIssuesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0,
        'wishlistCount' => MagazineRepository::isAvailable()
            ? $repo->countIssuesInLibrary($userId, $foyerId, LibraryStatut::WISHLIST)
            : 0,
        'pdfCount' => (int) ($pdfStats['count'] ?? 0),
        'pdfStorageLabel' => MagazineRepository::formatPdfStorageGigabytes((int) ($pdfStats['total_bytes'] ?? 0)),
    ]);
    exit;
}

if (MediaDomain::isGame(MediaContext::current())) {
    MediaDomainGuards::ensureGameContext('/statistiques.php');
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();

    View::render('statistiques-jeux', [
        'pageTitle' => MediaContext::navLabels()['stats'],
        'stats' => (new GameCollectionStats())->getDashboard($userId, $foyerId),
        'wideLayout' => true,
    ]);
    exit;
}

$stats = (new CollectionStats())->getDashboard();

View::render('statistiques', [
    'pageTitle' => MediaContext::navLabels()['stats'],
    'stats' => $stats,
    'wideLayout' => true,
]);
