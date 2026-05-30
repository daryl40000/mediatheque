<?php
/**
 * Fiche détaillée d’un film de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\FilmListContext;
use Moncine\FilmRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\OeuvreEanRepository;
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
$noteSur10 = $historique->getNoteSur10($id);
$noteFoyerMoyenne = $historique->getFoyerAverageNote($id);
$viewings = $historique->findViewingsByFilm($id);

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
$editOpen = isset($_GET['edit']) || $saveError !== '';

$defaultList = ($film['statut'] ?? '') === LibraryStatut::WISHLIST
    ? FilmListContext::WISHLIST
    : FilmListContext::COLLECTION;
$filmListContext = FilmListContext::fromQuery($_GET, $defaultList);
$filmNav = $repo->getFilmNavigation($id, $filmListContext);

$catalogEanSuggestion = null;
$wishlistTargets = [];
$catalogEansForOeuvre = [];
$oeuvreId = (int) ($film['oeuvre_id'] ?? 0);
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

View::render('film', [
    'pageTitle' => (string) $film['titre'],
    'film' => $film,
    'derniereVue' => $derniereVue,
    'noteSur10' => $noteSur10,
    'noteFoyerMoyenne' => $noteFoyerMoyenne,
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
    'sagaSuggestions' => $repo->distinctSagas(),
    'canManageCatalog' => UserContext::canManageCatalog(),
    'showTmdbEnrich' => UserContext::canManageCatalog(),
    'filmListContext' => $filmListContext,
    'filmNav' => $filmNav,
    'listBackUrl' => $filmListContext->backUrl(),
    'catalogEanSuggestion' => $catalogEanSuggestion,
    'wishlistTargets' => $wishlistTargets,
    'catalogEansForOeuvre' => $catalogEansForOeuvre,
]);
