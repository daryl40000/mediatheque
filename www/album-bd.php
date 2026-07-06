<?php
/**
 * Fiche d’un album BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPhysicalSupport;
use Moncine\BdRepository;
use Moncine\BdSeriesContext;
use Moncine\BibliothequeRepository;
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

$allowedPopovers = ['note', 'edit', 'lu'];
$popoverOpen = '';
$editError = (string) ($_GET['edit_error'] ?? '');
if (!empty($_GET['note_error'])) {
    $popoverOpen = 'note';
} elseif (!empty($_GET['lu_error'])) {
    $popoverOpen = 'lu';
} elseif (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
} elseif ($editError !== '' || (isset($_GET['saved']) && $editError !== '')) {
    $popoverOpen = 'edit';
}

$oeuvreId = (int) ($album['oeuvre_id'] ?? 0);
$socialRessentis = !$isWishlist && $oeuvreId > 0
    ? (new SocialRessentiService())->listAroundOeuvre(
        $oeuvreId,
        MediaDomain::BD,
        $userId,
        $foyerId
    )
    : ['foyer' => [], 'friends' => []];

$bdSeriesNeighbors = [];
if ($seriesId > 0 && $oeuvreId > 0) {
    $bdSeriesNeighbors = BdSeriesContext::neighborStrip(
        $repo,
        $seriesId,
        $oeuvreId,
        $userId,
        $foyerId,
    );
}

$inWishlist = false;
if ($oeuvreId > 0) {
    $inWishlist = (new BibliothequeRepository())->findByOeuvreId(
        $oeuvreId,
        $userId,
        $foyerId,
        LibraryStatut::WISHLIST,
    ) !== null;
}

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
    'popoverOpen' => $popoverOpen,
    'supportChoices' => BdPhysicalSupport::choices(),
    'knownGenres' => BdRepository::isAvailable() ? $repo->listKnownGenres() : [],
    'editError' => $editError,
    'bdSeriesNeighbors' => $bdSeriesNeighbors,
    'seriesTitre' => trim((string) ($album['series_titre'] ?? '')),
    'inWishlist' => $inWishlist,
]);
