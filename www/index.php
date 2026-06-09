<?php
/**
 * Page d'accueil — contenu selon l’onglet média actif.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\BibliothequeRepository;
use Moncine\FilmRepository;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\UserPublicProfileService;
use Moncine\View;

$mediaDomain = MediaContext::current();
$userId = Auth::currentUserId();

if (MediaDomain::isMagazine($mediaDomain)) {
    $repo = new MagazineRepository();
    $foyerId = UserContext::currentFoyerId();
    $seriesCount = $userId > 0 && MagazineRepository::isAvailable()
        ? $repo->countSeriesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
        : 0;
    $issueCount = $userId > 0 && MagazineRepository::isAvailable()
        ? $repo->countIssuesInLibrary($userId, $foyerId, LibraryStatut::COLLECTION)
        : 0;

    View::render('home-magazine', [
        'pageTitle' => 'Accueil',
        'seriesCount' => $seriesCount,
        'issueCount' => $issueCount,
        'setupDone' => isset($_GET['setup']) && (string) $_GET['setup'] === '1',
    ]);
    exit;
}

if (MediaDomain::isGame($mediaDomain)) {
    $foyerId = UserContext::currentFoyerId();
    $gameCount = $userId > 0 && GameRepository::isAvailable()
        ? (new BibliothequeRepository())->countByStatut($userId, $foyerId, LibraryStatut::COLLECTION)
        : 0;

    $lastNoted = [];
    $lastCollection = [];
    $lastWishlist = [];
    if ($userId > 0) {
        $profile = new UserPublicProfileService();
        $lastNoted = $profile->lastNotedGames($userId, 5);
        $lastCollection = $profile->lastCollectionFilms($userId, 5, MediaDomain::JEU);
        $lastWishlist = $profile->lastWishlistFilms($userId, 5, MediaDomain::JEU);
    }

    View::render('home-jeu', [
        'pageTitle' => 'Accueil',
        'gameCount' => $gameCount,
        'setupDone' => isset($_GET['setup']) && (string) $_GET['setup'] === '1',
        'lastNoted' => $lastNoted,
        'lastCollection' => $lastCollection,
        'lastWishlist' => $lastWishlist,
        'currentUserId' => $userId,
    ]);
    exit;
}

$films = new FilmRepository();

$lastViewed = [];
$lastCollection = [];
$lastWishlist = [];
if ($userId > 0) {
    $profile = new UserPublicProfileService();
    $lastViewed = $profile->lastViewedFilms($userId, 5);
    $lastCollection = $profile->lastCollectionFilms($userId, 5);
    $lastWishlist = $profile->lastWishlistFilms($userId, 5);
}

View::render('home', [
    'pageTitle' => 'Accueil',
    'filmCount' => $films->count(),
    'setupDone' => isset($_GET['setup']) && (string) $_GET['setup'] === '1',
    'lastViewed' => $lastViewed,
    'lastCollection' => $lastCollection,
    'lastWishlist' => $lastWishlist,
    'profileUrl' => $userId > 0 ? View::userProfileUrl($userId) : '',
    'currentUserId' => $userId,
]);
