<?php
/**
 * Enregistre une nouvelle série magazine ou ajoute une série catalogue à la bibliothèque.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MagazineSeriesTag;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ajouter-serie-magazine.php');
    exit;
}

MediaDomainGuards::ensureMagazineContext('/ajouter-serie-magazine.php');
Csrf::rejectUnlessValid($_POST, '/ajouter-serie-magazine.php');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$action = (string) ($_POST['action'] ?? 'create');

if ($action === 'from_catalog') {
    $seriesId = (int) ($_POST['catalog_series_id'] ?? 0);
    if ($seriesId <= 0) {
        $params = http_build_query(['error' => 'Choisissez une revue dans la liste du catalogue.']);
        header('Location: /ajouter-serie-magazine.php?' . $params);
        exit;
    }

    $series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
    if ($series === null) {
        $params = http_build_query(['error' => 'Série catalogue introuvable.']);
        header('Location: /ajouter-serie-magazine.php?' . $params);
        exit;
    }

    if (!MagazineRepository::isAvailable()) {
        $params = http_build_query(['error' => 'Module magazines non disponible.']);
        header('Location: /ajouter-serie-magazine.php?' . $params);
        exit;
    }

    $register = (new MagazineRepository())->registerSeriesInLibrary(
        $seriesId,
        LibraryStatut::COLLECTION,
        $userId,
        $foyerId
    );
    if ($register !== true) {
        $params = http_build_query(['error' => (string) $register]);
        header('Location: /ajouter-serie-magazine.php?' . $params);
        exit;
    }

    $attached = (new MagazineRepository())->attachCatalogIssuesToCollection($seriesId, $userId, $foyerId);

    $redirect = View::magazineSeriesUrl($seriesId) . '&added_series=1';
    if ($attached > 0) {
        $redirect .= '&linked_issues=' . $attached;
    }
    header('Location: ' . $redirect);
    exit;
}

$result = (new SeriesRepository())->create([
    'titre' => (string) ($_POST['titre'] ?? ''),
    'publication_type' => (string) ($_POST['publication_type'] ?? ''),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'issn' => (string) ($_POST['issn'] ?? ''),
    'langue' => (string) ($_POST['langue'] ?? ''),
    'pays' => (string) ($_POST['pays'] ?? ''),
    'date_debut' => (string) ($_POST['date_debut'] ?? ''),
    'date_fin' => (string) ($_POST['date_fin'] ?? ''),
    'notes' => (string) ($_POST['notes'] ?? ''),
    'tags' => MagazineSeriesTag::normalizeFromPost($_POST['tags'] ?? ''),
], MediaDomain::MAGAZINE);

if (!is_int($result)) {
    $params = http_build_query(['error' => (string) $result, 'titre' => (string) ($_POST['titre'] ?? '')]);
    header('Location: /ajouter-serie-magazine.php?' . $params);
    exit;
}

$magRepo = new MagazineRepository();
if (MagazineRepository::isAvailable()) {
    $magRepo->registerSeriesInLibrary($result, LibraryStatut::COLLECTION, $userId, $foyerId);
}

if (
    isset($_FILES['cover_file'])
    && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
) {
    $binary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
    $posterUrl = (new PosterStorage())->importBinaryForSeries($result, $binary);
    if ($posterUrl !== '') {
        (new SeriesRepository())->update($result, ['poster_url' => $posterUrl]);
    }
}

header('Location: ' . View::magazineSeriesUrl($result));
exit;
