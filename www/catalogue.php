<?php
/**
 * Administration du catalogue partagé (œuvres Moncine).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\MediaDomain;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$admin = new CatalogAdmin();
$search = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$page = max(1, (int) ($_GET['page'] ?? 1));
$mediaDomain = MediaDomain::normalizeCatalogFilter((string) ($_GET['media'] ?? $_POST['media'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectBase = '/catalogue.php?' . http_build_query(array_filter([
        'q' => $search !== '' ? $search : null,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'page' => $page > 1 ? (string) $page : null,
        'media' => $mediaDomain !== '' ? $mediaDomain : null,
    ]), '', '&', PHP_QUERY_RFC3986);

    Csrf::rejectUnlessValid($_POST, $redirectBase);

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete_oeuvre') {
        $oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
        $result = $admin->deleteOeuvre($oeuvreId);
        if ($result !== true) {
            header('Location: ' . $redirectBase . '&delete_error=' . rawurlencode((string) $result));
            exit;
        }
        header('Location: ' . $redirectBase . '&deleted=1');
        exit;
    }
}

$totalCount = $admin->countOeuvres($search, $mediaDomain);
$perPage = CatalogAdmin::perPage();
$totalPages = max(1, (int) ceil($totalCount / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$oeuvres = $admin->listOeuvres($search, $sortBy, $sortDir, $page, $mediaDomain);

View::render('catalogue', [
    'pageTitle' => 'Catalogue Moncine',
    'oeuvres' => $oeuvres,
    'search' => $search,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'page' => $page,
    'mediaDomain' => $mediaDomain,
    'totalPages' => $totalPages,
    'totalCount' => $totalCount,
    'perPage' => $perPage,
    'added' => isset($_GET['added']) && (string) $_GET['added'] === '1',
    'deleted' => isset($_GET['deleted']) && (string) $_GET['deleted'] === '1',
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'deleteError' => trim((string) ($_GET['delete_error'] ?? '')),
    'hasTmdbKey' => \Moncine\FilmEnricher::canEnrich(),
    'gameModuleAvailable' => GameRepository::isAvailable(),
    'knownGenres' => GameRepository::isAvailable() ? (new GameRepository())->listKnownGenres() : [],
]);
