<?php
/**
 * Fiche livre (exemplaire bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\LivreCategory;
use Moncine\LivreGameLink;
use Moncine\LivreRepository;
use Moncine\LivreSagaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SocialRessentiService;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext('/livre.php');

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();
$book = $bibId > 0 && LivreRepository::isAvailable()
    ? $repo->findByBibId($bibId, $userId, $foyerId)
    : null;

if ($book === null) {
    View::render('livre', [
        'pageTitle' => 'Livre introuvable',
        'book' => null,
        'bookId' => $bibId,
        'isWishlist' => false,
        'linkedGames' => [],
        'listBackUrl' => '/livres.php',
    ]);
    exit;
}

$isWishlist = ($book['statut'] ?? '') === LibraryStatut::WISHLIST;
$listBackUrl = $isWishlist ? '/livres-envies.php' : '/livres.php';

$linkedGames = [];
if (LivreGameLink::isAvailable()) {
    $linkedGames = (new LivreGameLink())->listGamesForBook((int) ($book['oeuvre_id'] ?? 0));
}

$historique = new HistoriqueRepository();
$monRessenti = $isWishlist ? null : $historique->getBestRessentiScore($bibId);
$readHistory = $isWishlist ? [] : $historique->findViewingsByFilm($bibId);
$everRead = $isWishlist ? false : $historique->wasEverSeen($bibId);

$readAtLabel = '';
if ($readHistory !== []) {
    $readAtLabel = HistoriqueRepository::formatDateVue((string) ($readHistory[0]['date_vue'] ?? ''));
}

$allowedPopovers = ['note', 'edit', 'lu'];
$popoverOpen = '';
$editError = (string) ($_GET['edit_error'] ?? '');
if ($editError === '' && isset($_GET['popover']) && (string) $_GET['popover'] === 'edit' && !empty($_GET['error'])) {
    $editError = (string) $_GET['error'];
}
if (!empty($_GET['note_error'])) {
    $popoverOpen = 'note';
} elseif (!empty($_GET['lu_error'])) {
    $popoverOpen = 'lu';
} elseif (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
} elseif ($editError !== '') {
    $popoverOpen = 'edit';
}

$oeuvreId = (int) ($book['oeuvre_id'] ?? 0);
$socialRessentis = !$isWishlist && $oeuvreId > 0
    ? (new SocialRessentiService())->listAroundOeuvre(
        $oeuvreId,
        MediaDomain::LIVRE,
        $userId,
        $foyerId
    )
    : ['foyer' => [], 'friends' => []];

$sagaTitre = trim((string) ($book['saga'] ?? ''));
$livreSagaNeighbors = [];
if ($sagaTitre !== '' && $oeuvreId > 0) {
    $livreSagaNeighbors = LivreSagaContext::neighborStrip(
        $repo,
        $sagaTitre,
        $oeuvreId,
        $userId,
        $foyerId,
    );
}

View::render('livre', [
    'pageTitle' => (string) ($book['titre'] ?? 'Livre'),
    'book' => $book,
    'bookId' => $bibId,
    'isWishlist' => $isWishlist,
    'linkedGames' => $linkedGames,
    'listBackUrl' => $listBackUrl,
    'monRessenti' => $monRessenti,
    'socialRessentis' => $socialRessentis,
    'readHistory' => $readHistory,
    'everRead' => $everRead,
    'readAtLabel' => $readAtLabel,
    'popoverOpen' => $popoverOpen,
    'editError' => $editError,
    'knownCategories' => LivreCategory::suggestionLabels(),
    'sagaSuggestions' => $repo->listKnownSagas(),
    'livreSagaNeighbors' => $livreSagaNeighbors,
    'sagaTitre' => $sagaTitre,
]);
