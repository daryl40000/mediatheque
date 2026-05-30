<?php
/**
 * Page d'accueil Moncine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\FilmRepository;
use Moncine\UserPublicProfileService;
use Moncine\View;

$films = new FilmRepository();
$userId = Auth::currentUserId();

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
