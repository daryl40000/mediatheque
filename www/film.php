<?php
/**
 * Fiche détaillée d’un film de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\DetailLibraryState;
use Moncine\FilmListContext;
use Moncine\FilmRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\OeuvreEanRepository;
use Moncine\SocialRessentiService;
use Moncine\TmdbConfig;
use Moncine\WishlistTargetRepository;
use Moncine\UserContext;
use Moncine\View;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /films.php');
    exit;
}

$repo = new FilmRepository();
$film = $repo->findById($id);
if ($film === null) {
    http_response_code(404);
    View::render('film', [
        'pageTitle' => 'Film introuvable',
        'film' => null,
        'derniereVue' => null,
    ]);
    exit;
}

$historique = new HistoriqueRepository();
$lastViewing = $historique->getLastViewing($id);
$derniereVue = $lastViewing['date_vue'] ?? null;
$monRessenti = $historique->getBestRessentiScore($id);
$viewings = $historique->findViewingsByFilm($id);

$oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
$socialRessentis = $oeuvreId > 0
    ? (new SocialRessentiService())->listAroundOeuvre(
        $oeuvreId,
        MediaDomain::FILM,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    )
    : ['foyer' => [], 'friends' => []];

$enrichStatus = null;
$enrichMessage = '';
if (isset($_GET['enrich'])) {
    $enrichStatus = match ((string) $_GET['enrich']) {
        'ok' => 'ok',
        'not_found' => 'not_found',
        default => 'error',
    };
    $enrichMessage = (string) ($_GET['enrich_msg'] ?? '');
    $refreshed = $repo->findById($id);
    if ($refreshed !== null) {
        $film = $refreshed;
    }
}

$saveError = (string) ($_GET['save_error'] ?? '');
$allowedPopovers = ['note', 'edit', 'vu'];
$popoverOpen = '';
if (!empty($_GET['note_error'])) {
    $popoverOpen = 'note';
} elseif (!empty($_GET['vu_error'])) {
    $popoverOpen = 'vu';
} elseif (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
} elseif (isset($_GET['edit']) || $saveError !== '') {
    $popoverOpen = 'edit';
}
$editOpen = $popoverOpen === 'edit';

$defaultList = ($film['statut'] ?? '') === LibraryStatut::WISHLIST
    ? FilmListContext::WISHLIST
    : FilmListContext::COLLECTION;
$filmListContext = FilmListContext::fromQuery($_GET, $defaultList);
$filmNav = $repo->getFilmNavigation($id, $filmListContext);

$catalogEanSuggestion = null;
$wishlistTargets = [];
$catalogEansForOeuvre = [];
$isWishlistFilm = ($film['statut'] ?? '') === LibraryStatut::WISHLIST;

if (OeuvreEanRepository::tableExists()) {
    if ($oeuvreId > 0) {
        $eanRow = (new OeuvreEanRepository())->findForOeuvreAndSupport(
            $oeuvreId,
            (string) ($film['support_physique'] ?? '')
        );
        if ($eanRow !== null) {
            $catalogEanSuggestion = (string) ($eanRow['ean'] ?? '');
        }
        if ($isWishlistFilm) {
            $catalogEansForOeuvre = (new OeuvreEanRepository())->listForOeuvre($oeuvreId);
        }
    }
}

if ($isWishlistFilm && WishlistTargetRepository::tableExists()) {
    $wishlistTargets = (new WishlistTargetRepository())->listForBibliothequeId($id);
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$canManageCatalog = UserContext::canManageCatalog();
$sagaFilms = [];
$sagaName = trim((string) ($film['saga'] ?? ''));
if ($sagaName !== '' && $repo->usesCatalogModel() && $oeuvreId > 0) {
    $sagaFilms = $repo->listCatalogBySaga($sagaName, $oeuvreId);
    foreach ($sagaFilms as $index => $row) {
        $childOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
        if ($childOeuvreId <= 0) {
            continue;
        }
        $sagaFilms[$index] = array_merge(
            $sagaFilms[$index],
            DetailLibraryState::forOeuvre(
                $childOeuvreId,
                $userId,
                $foyerId,
                static fn (int $bibId): string => '/film.php?id=' . $bibId,
                $canManageCatalog ? View::oeuvreUrl($childOeuvreId) : null,
            ),
        );
    }
}

View::render('film', [
    'pageTitle' => (string) $film['titre'],
    'film' => $film,
    'derniereVue' => $derniereVue,
    'monRessenti' => $monRessenti,
    'socialRessentis' => $socialRessentis,
    'viewings' => $viewings,
    'saved' => isset($_GET['saved']),
    'saveError' => $saveError,
    'editOpen' => $editOpen,
    'everSeen' => $historique->wasEverSeen($id),
    'hasTmdbKey' => TmdbConfig::hasApiKey(),
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
    'returnPage' => 'film',
    'currentTmdbId' => (int) ($film['tmdb_id'] ?? 0),
    'currentTmdbMediaType' => (string) ($film['tmdb_media_type'] ?? ''),
    'currentTmdbTvKind' => (string) ($film['tmdb_tv_kind'] ?? ''),
    'filmId' => $id,
    'isWishlist' => $isWishlistFilm,
    'popoverOpen' => $popoverOpen,
    'sagaFilms' => $sagaFilms,
    'sagaSuggestions' => $repo->distinctSagas(),
    'canManageCatalog' => $canManageCatalog,
    'showTmdbEnrich' => UserContext::canManageCatalog(),
    'filmListContext' => $filmListContext,
    'filmNav' => $filmNav,
    'listBackUrl' => $filmListContext->backUrl(),
    'catalogEanSuggestion' => $catalogEanSuggestion,
    'wishlistTargets' => $wishlistTargets,
    'catalogEansForOeuvre' => $catalogEansForOeuvre,
]);
