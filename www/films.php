<?php
/**
 * Liste de tous les films de la collection (+ actions de masse).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::renderCollectionPageOrExit();

use Moncine\CollectionViewMode;
use Moncine\FilmCollectionPagination;
use Moncine\MediaContext;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\Csrf;
use Moncine\Exception\ValidationException;
use Moncine\FilmEnricher;
use Moncine\FilmRepository;
use Moncine\Service\FilmBulkActionService;
use Moncine\View;

if (MediaDomain::isMagazine(MediaContext::current())) {
    header('Location: ' . MediaDomain::collectionPath(MediaDomain::MAGAZINE));
    exit;
}

$sortBy = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? $_POST['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$kindFilter = \Moncine\ContentKindFilter::normalize((string) ($_GET['kind'] ?? $_POST['kind'] ?? ''));
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? $_POST['view'] ?? ''));
$requestedPage = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));

$repo = new FilmRepository();

/**
 * @param array<string, int|string> $params
 */
function moncine_films_bulk_redirect(string $redirectUrl, array $params): never
{
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $sep . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectUrl = View::filmsCollectionUrl($query, $sortBy, $sortDir, $kindFilter, $viewMode, $requestedPage);
    Csrf::rejectUnlessValid($_POST, $redirectUrl);

    $filmIds = FilmRepository::parseBulkFilmIds($_POST);
    $action = (string) ($_POST['action'] ?? '');

    try {
        $params = (new FilmBulkActionService())->handleBulkAction($action, $filmIds, $_POST);
        moncine_films_bulk_redirect($redirectUrl, $params);
    } catch (ValidationException $e) {
        moncine_films_bulk_redirect($redirectUrl, [
            'bulk_error' => $e->getMessage(),
        ]);
    }
}

$totalCount = $repo->count();
$listTotal = $repo->countCollectionFiltered($query, $kindFilter);
$pagination = FilmCollectionPagination::resolve($requestedPage, $listTotal, $viewMode);
$page = $pagination['page'];
$perPage = $pagination['perPage'];
$totalPages = $pagination['totalPages'];
$offset = $pagination['offset'];

$films = $repo->findAll(
    $sortBy,
    $sortDir,
    $query,
    $kindFilter,
    CollectionViewMode::isShelf($viewMode) ? null : $perPage,
    $offset
);
$existingSagas = $repo->distinctSagas();

View::render('films', [
    'pageTitle' => 'Mes films',
    'films' => $films,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'query' => $query,
    'kindFilter' => $kindFilter,
    'viewMode' => $viewMode,
    'searched' => $query !== '',
    'totalCount' => $totalCount,
    'listTotal' => $listTotal,
    'page' => $page,
    'totalPages' => $totalPages,
    'perPage' => $perPage,
    'existingSagas' => $existingSagas,
    'hasTmdbKey' => FilmEnricher::canEnrich(),
    'canManageCatalog' => UserContext::canManageCatalog(),
]);
