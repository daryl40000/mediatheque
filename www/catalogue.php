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
$sortBy = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? $_POST['dir'] ?? 'asc');
$page = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
$mediaDomain = MediaDomain::normalizeCatalogFilter((string) ($_GET['media'] ?? $_POST['media'] ?? ''));

$catalogueRedirectUrl = static function (
    string $search,
    string $sortBy,
    string $sortDir,
    int $page,
    string $mediaDomain,
    array $extra = []
): string {
    $params = array_merge(array_filter([
        'q' => $search !== '' ? $search : null,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'page' => $page > 1 ? (string) $page : null,
        'media' => $mediaDomain !== '' ? $mediaDomain : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''), $extra);

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return $query === '' ? '/catalogue.php' : '/catalogue.php?' . $query;
};

$cataloguePageAfterDelete = static function (int $page, int $remainingCount, int $perPage): int {
    $totalPages = max(1, (int) ceil(max(0, $remainingCount) / max(1, $perPage)));

    return max(1, min($page, $totalPages));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectBase = $catalogueRedirectUrl($search, $sortBy, $sortDir, $page, $mediaDomain);

    Csrf::rejectUnlessValid($_POST, $redirectBase);

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete_oeuvre') {
        $oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
        $result = $admin->deleteOeuvre($oeuvreId);
        if ($result !== true) {
            header('Location: ' . $catalogueRedirectUrl(
                $search,
                $sortBy,
                $sortDir,
                $page,
                $mediaDomain,
                ['delete_error' => (string) $result]
            ) . '#catalog-list-nav');
            exit;
        }

        $remaining = $admin->countOeuvres($search, $mediaDomain);
        $targetPage = $cataloguePageAfterDelete($page, $remaining, CatalogAdmin::perPage());
        header('Location: ' . $catalogueRedirectUrl(
            $search,
            $sortBy,
            $sortDir,
            $targetPage,
            $mediaDomain,
            ['deleted_count' => '1']
        ) . '#catalog-list-nav');
        exit;
    }

    if ($action === 'delete_oeuvres_bulk') {
        $oeuvreIds = [];
        foreach ((array) ($_POST['oeuvre_ids'] ?? []) as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $oeuvreIds[] = $id;
            }
        }
        $oeuvreIds = array_values(array_unique($oeuvreIds));

        if ($oeuvreIds === []) {
            header('Location: ' . $catalogueRedirectUrl(
                $search,
                $sortBy,
                $sortDir,
                $page,
                $mediaDomain,
                ['delete_error' => 'Sélectionnez au moins une fiche à supprimer.']
            ) . '#catalog-list-nav');
            exit;
        }

        $bulk = $admin->deleteOeuvres($oeuvreIds);
        $remaining = $admin->countOeuvres($search, $mediaDomain);
        $targetPage = $cataloguePageAfterDelete($page, $remaining, CatalogAdmin::perPage());

        $params = ['deleted_count' => (string) ($bulk['deleted'] ?? 0)];
        if (($bulk['errors'] ?? []) !== []) {
            $params['delete_error'] = implode(' ', array_slice($bulk['errors'], 0, 3));
        }

        header('Location: ' . $catalogueRedirectUrl(
            $search,
            $sortBy,
            $sortDir,
            $targetPage,
            $mediaDomain,
            $params
        ) . '#catalog-list-nav');
        exit;
    }

    header('Location: ' . $catalogueRedirectUrl(
        $search,
        $sortBy,
        $sortDir,
        $page,
        $mediaDomain,
        ['delete_error' => 'Action non reconnue.']
    ) . '#catalog-list-nav');
    exit;
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
    'pageMediaDomain' => $mediaDomain !== '' ? $mediaDomain : MediaDomain::FILM,
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
    'deletedCount' => max(0, (int) ($_GET['deleted_count'] ?? 0)),
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'deleteError' => trim((string) ($_GET['delete_error'] ?? '')),
    'hasTmdbKey' => \Moncine\FilmEnricher::canEnrich(),
    'gameModuleAvailable' => GameRepository::isAvailable(),
    'knownGenres' => GameRepository::isAvailable() ? (new GameRepository())->listKnownGenres() : [],
]);
