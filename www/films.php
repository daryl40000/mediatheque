<?php
/**
 * Liste de tous les films de la collection (+ actions de masse).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::renderCollectionPageOrExit();

use Moncine\CatalogAdmin;
use Moncine\CollectionViewMode;
use Moncine\UserContext;
use Moncine\Csrf;
use Moncine\FilmEnricher;
use Moncine\FilmRepository;
use Moncine\SupportPhysique;
use Moncine\View;

$sortBy = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? $_POST['dir'] ?? 'asc');
$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$kindFilter = \Moncine\ContentKindFilter::normalize((string) ($_GET['kind'] ?? $_POST['kind'] ?? ''));
$viewMode = CollectionViewMode::normalize((string) ($_GET['view'] ?? $_POST['view'] ?? ''));

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
    $redirectUrl = View::filmsCollectionUrl($query, $sortBy, $sortDir, $kindFilter, $viewMode);
    Csrf::rejectUnlessValid($_POST, $redirectUrl);

    $filmIds = FilmRepository::parseBulkFilmIds($_POST);
    $action = (string) ($_POST['action'] ?? '');

    if ($filmIds === []) {
        moncine_films_bulk_redirect($redirectUrl, [
            'bulk_error' => 'Sélectionnez au moins un film.',
        ]);
    }

    if ($action === 'assign_saga') {
        $sagaNew = trim((string) ($_POST['saga_new'] ?? ''));
        $sagaExisting = trim((string) ($_POST['saga_existing'] ?? ''));
        $sagaName = $sagaNew !== '' ? $sagaNew : $sagaExisting;
        $startOrder = max(1, (int) ($_POST['saga_ordre_start'] ?? 1));

        if ($sagaName === '') {
            moncine_films_bulk_redirect($redirectUrl, [
                'bulk_error' => 'Choisissez une saga existante ou saisissez un nouveau nom.',
            ]);
        }

        $updated = $repo->assignFilmsToSaga($filmIds, $sagaName, $startOrder);
        moncine_films_bulk_redirect($redirectUrl, [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' film' . ($updated > 1 ? 's' : '') . ' ajouté' . ($updated > 1 ? 's' : '')
                . ' à la saga « ' . $sagaName . ' ».',
            'saga_name' => $sagaName,
        ]);
    }

    if ($action === 'set_support') {
        $supportRaw = (string) ($_POST['bulk_support_physique'] ?? '');
        $supportKey = SupportPhysique::normalize($supportRaw);
        if ($supportRaw !== '' && $supportKey === '') {
            moncine_films_bulk_redirect($redirectUrl, [
                'bulk_error' => 'Support invalide. Choisissez DVD, Blu-ray ou Blu-ray 4K.',
            ]);
        }

        $updated = $repo->updateFilmsSupportPhysique($filmIds, $supportKey);
        $label = $supportKey !== ''
            ? SupportPhysique::label($supportKey)
            : 'Non renseigné';
        moncine_films_bulk_redirect($redirectUrl, [
            'bulk_ok' => $updated,
            'bulk_msg' => $updated . ' film' . ($updated > 1 ? 's' : '') . ' : support « ' . $label . ' ».',
        ]);
    }

    if ($action === 'enrich_tmdb') {
        if (!CatalogAdmin::canAccess()) {
            moncine_films_bulk_redirect($redirectUrl, [
                'bulk_error' => 'L’enrichissement TMDB est réservé à l’administrateur du catalogue.',
            ]);
        }

        $result = (new FilmEnricher())->enrichSelectedByTmdbId($filmIds);

        if ($result['errors'] !== [] && $result['updated'] === 0 && $result['skipped_no_tmdb'] === 0) {
            moncine_films_bulk_redirect($redirectUrl, [
                'bulk_error' => $result['errors'][0],
            ]);
        }

        $params = [
            'bulk_ok' => $result['updated'],
            'bulk_msg' => FilmEnricher::bulkTmdbSummaryMessage($result),
        ];
        if ($result['errors'] !== [] && $result['failed'] > 0) {
            $params['bulk_detail'] = implode(' | ', array_slice($result['errors'], 0, 5));
        }
        moncine_films_bulk_redirect($redirectUrl, $params);
    }

    if ($action === 'delete_films') {
        $deleted = $repo->deleteFilms($filmIds);
        moncine_films_bulk_redirect($redirectUrl, [
            'bulk_ok' => $deleted,
            'bulk_msg' => $deleted . ' film' . ($deleted > 1 ? 's' : '') . ' supprimé' . ($deleted > 1 ? 's' : '')
                . ' de vos films.',
        ]);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$films = $repo->findAll($sortBy, $sortDir, $query, $kindFilter);
$totalCount = $repo->count();
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
    'existingSagas' => $existingSagas,
    'hasTmdbKey' => FilmEnricher::canEnrich(),
    'canManageCatalog' => UserContext::canManageCatalog(),
]);
