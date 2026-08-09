<?php
/**
 * Page statistiques de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionStats;
use Moncine\BdRepository;
use Moncine\GameCollectionStats;
use Moncine\LibraryStatut;
use Moncine\LivreCollectionStats;
use Moncine\MagazinePeriodStats;
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

    $periodFrom = (int) ($_GET['period_from'] ?? 0);
    $periodTo = (int) ($_GET['period_to'] ?? 0);
    $periodStatsService = MagazinePeriodStats::isAvailable() ? new MagazinePeriodStats() : null;
    $periodStats = $periodStatsService !== null
        ? $periodStatsService->getPeriodDashboard(
            $periodFrom > 0 ? $periodFrom : null,
            $periodTo > 0 ? $periodTo : null
        )
        : [
            'active' => false,
            'from_year' => 0,
            'to_year' => 0,
            'year_choices' => [],
            'games_most' => [],
            'games_least' => [],
            'series_most_tests' => [],
            'series_most_previews' => [],
        ];

    // Recherche : mois précis + catégorie de magazine (Jeux vidéo, Cinéma…).
    $monthBrowseYear = (int) ($_GET['month_year'] ?? 0);
    $monthBrowseMonth = (int) ($_GET['month_num'] ?? 0);
    $monthBrowseCategory = trim((string) ($_GET['month_series_category'] ?? ''));
    $monthBrowseStats = $periodStatsService !== null
        ? $periodStatsService->getMonthCategorySubjects(
            $monthBrowseYear > 0 ? $monthBrowseYear : null,
            $monthBrowseMonth > 0 ? $monthBrowseMonth : null,
            $monthBrowseCategory !== '' ? $monthBrowseCategory : null
        )
        : [
            'active' => false,
            'year' => 0,
            'month' => 0,
            'series_category_key' => '',
            'series_category_label' => '',
            'month_label' => '',
            'total' => 0,
            'groups' => [],
            'year_choices' => [],
            'month_choices' => [],
            'series_category_choices' => [],
        ];

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
        'periodStats' => $periodStats,
        'monthBrowseStats' => $monthBrowseStats,
        'wideLayout' => true,
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

if (MediaDomain::isBd(MediaContext::current())) {
    MediaDomainGuards::ensureBdContext('/statistiques.php');
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $repo = new BdRepository();

    View::render('statistiques-bd', [
        'pageTitle' => MediaContext::navLabels()['stats'],
        'seriesCount' => BdRepository::isAvailable()
            ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0,
        'tomeCount' => BdRepository::isAvailable()
            ? $repo->countTomesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
            : 0,
        'wishlistSeriesCount' => BdRepository::isAvailable()
            ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::WISHLIST)
            : 0,
    ]);
    exit;
}

if (MediaDomain::isLivre(MediaContext::current())) {
    MediaDomainGuards::ensureLivreContext('/statistiques.php');
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();

    View::render('statistiques-livres', [
        'pageTitle' => MediaContext::navLabels()['stats'],
        'stats' => (new LivreCollectionStats())->getDashboard($userId, $foyerId),
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
