<?php
/**
 * Mes jeux vidéo — liste de la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CollectionViewMode;
use Moncine\Csrf;
use Moncine\GameFranchiseRepository;
use Moncine\GameListFilter;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/jeux.php');

if (MediaContext::current() !== MediaDomain::JEU) {
    header('Location: ' . MediaDomain::collectionPath(MediaDomain::JEU));
    exit;
}

$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? $_POST['dir'] ?? 'asc');
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? $_POST['view'] ?? ''));
$listFilter = GameListFilter::fromQuery(array_merge($_GET, $_POST));

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();
$franchiseRepo = new GameFranchiseRepository();

/**
 * @param array<string, int|string> $params
 */
function moncine_jeux_bulk_redirect(string $redirectUrl, array $params): never
{
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $sep . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectUrl = View::gamesCollectionUrl($query, $sortBy, $sortDir, $viewMode, $listFilter);
    Csrf::rejectUnlessValid($_POST, $redirectUrl);

    $gameIds = GameFranchiseRepository::parseBulkGameIds($_POST);
    $action = (string) ($_POST['action'] ?? '');

    if ($gameIds === []) {
        moncine_jeux_bulk_redirect($redirectUrl, [
            'bulk_error' => 'Sélectionnez au moins un jeu.',
        ]);
    }

    if ($action === 'assign_franchise') {
        if (!GameFranchiseRepository::isAvailable()) {
            moncine_jeux_bulk_redirect($redirectUrl, [
                'bulk_error' => 'Module sagas indisponible.',
            ]);
        }

        $franchiseNew = trim((string) ($_POST['franchise_new'] ?? ''));
        $franchiseExisting = trim((string) ($_POST['franchise_existing'] ?? ''));
        $franchiseName = $franchiseNew !== '' ? $franchiseNew : $franchiseExisting;

        if ($franchiseName === '') {
            moncine_jeux_bulk_redirect($redirectUrl, [
                'bulk_error' => 'Choisissez une saga existante ou saisissez un nouveau nom.',
            ]);
        }

        $updated = $franchiseRepo->assignGamesToFranchise($gameIds, $franchiseName, $foyerId);
        moncine_jeux_bulk_redirect($redirectUrl, [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' jeu' . ($updated > 1 ? 'x' : '') . ' ajouté' . ($updated > 1 ? 's' : '')
                . ' à la saga « ' . $franchiseName . ' ».',
            'franchise_name' => $franchiseName,
        ]);
    }

    moncine_jeux_bulk_redirect($redirectUrl, [
        'bulk_error' => 'Action inconnue.',
    ]);
}

if (!GameRepository::isAvailable()) {
    View::render('jeux', [
        'pageTitle' => MediaContext::navLabels()['collection'],
        'games' => [],
        'totalCount' => 0,
        'query' => $query,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'viewMode' => $viewMode,
        'listFilter' => $listFilter,
        'existingFranchises' => [],
        'knownSagas' => [],
        'moduleError' => 'Le module jeux n’est pas encore disponible. Rechargez la page dans quelques secondes.',
    ]);
    exit;
}

$games = $repo->listInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::COLLECTION,
    $sortBy,
    $sortDir,
    $query,
    $listFilter
);

$existingFranchises = GameFranchiseRepository::isAvailable()
    ? $franchiseRepo->distinctFranchises($foyerId)
    : [];
$knownSagas = GameFranchiseRepository::isAvailable()
    ? $franchiseRepo->listKnownSagas()
    : [];

View::render('jeux', [
    'pageTitle' => MediaContext::navLabels()['collection'],
    'games' => $games,
    'totalCount' => count($games),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'viewMode' => $viewMode,
    'listFilter' => $listFilter,
    'existingFranchises' => $existingFranchises,
    'knownSagas' => $knownSagas,
    'moduleError' => '',
]);
