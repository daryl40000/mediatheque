<?php
/**
 * Fiche d’un album BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SocialRessentiService;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext();

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();

$album = $bibId > 0 ? $repo->findByBibId($bibId, $userId, $foyerId) : null;

if ($album === null) {
    http_response_code(404);
    View::render('album-bd', [
        'pageTitle' => 'Album introuvable',
        'album' => null,
        'saved' => false,
        'albumId' => 0,
        'isWishlist' => false,
        'listBackUrl' => '/bd.php',
    ]);
    exit;
}

$isWishlist = ($album['statut'] ?? '') === LibraryStatut::WISHLIST;
$seriesId = (int) ($album['series_id'] ?? 0);
$listBackUrl = $seriesId > 0
    ? View::bdSeriesUrl($seriesId, 'tome', 'asc', ['statut' => $isWishlist ? LibraryStatut::WISHLIST : LibraryStatut::COLLECTION])
    : ($isWishlist ? '/bd-envies.php' : '/bd.php');

$historique = new HistoriqueRepository();
$monRessenti = $isWishlist ? null : $historique->getBestRessentiScore($bibId);
$readHistory = $isWishlist ? [] : $historique->findViewingsByFilm($bibId);
$everRead = $isWishlist ? false : $historique->wasEverSeen($bibId);

$oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
$socialRessentis = !$isWishlist && $oeuvreId > 0
    ? (new SocialRessentiService())->listAroundOeuvre(
        $oeuvreId,
        MediaDomain::BD,
        $userId,
        $foyerId
    )
    : ['foyer' => [], 'friends' => []];

View::render('album-bd', [
    'pageTitle' => (string) ($album['display_titre'] ?? 'Album'),
    'album' => $album,
    'saved' => isset($_GET['saved']),
    'albumId' => $bibId,
    'isWishlist' => $isWishlist,
    'listBackUrl' => $listBackUrl,
    'monRessenti' => $monRessenti,
    'socialRessentis' => $socialRessentis,
    'readHistory' => $readHistory,
    'everRead' => $everRead,
    'supportChoices' => BdPhysicalSupport::choices(),
    'knownGenres' => BdRepository::isAvailable() ? $repo->listKnownGenres() : [],
    'editError' => (string) ($_GET['edit_error'] ?? ''),
]);
