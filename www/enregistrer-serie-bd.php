<?php
/**
 * Enregistre une série BD (catalogue + bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdRepository;
use Moncine\BdSeriesMetadata;
use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ajouter-serie-bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext('/ajouter-serie-bd.php');
Csrf::rejectUnlessValid($_POST, '/ajouter-serie-bd.php');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$action = (string) ($_POST['action'] ?? 'create');
$statut = LibraryStatut::normalize((string) ($_POST['statut'] ?? LibraryStatut::COLLECTION));

if ($action === 'from_catalog') {
    $seriesId = (int) ($_POST['catalog_series_id'] ?? 0);
    if ($seriesId <= 0) {
        header('Location: /ajouter-serie-bd.php?error=' . rawurlencode('Choisissez une série dans le catalogue.'));
        exit;
    }

    $series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
    if ($series === null) {
        header('Location: /ajouter-serie-bd.php?error=' . rawurlencode('Série catalogue introuvable.'));
        exit;
    }

    $repo = new BdRepository();
    if (!BdRepository::isAvailable()) {
        header('Location: /ajouter-serie-bd.php?error=' . rawurlencode('Module BD non disponible.'));
        exit;
    }

    $register = $repo->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
    if ($register !== true) {
        header('Location: /ajouter-serie-bd.php?error=' . rawurlencode((string) $register));
        exit;
    }

    $attached = $statut === LibraryStatut::COLLECTION
        ? $repo->attachCatalogTomesToCollection($seriesId, $userId, $foyerId)
        : 0;
    $redirect = ($statut === LibraryStatut::WISHLIST
        ? View::bdSeriesUrl($seriesId, 'tome', 'asc', ['statut' => LibraryStatut::WISHLIST])
        : View::bdSeriesUrl($seriesId)) . '&added_series=1';
    if ($attached > 0) {
        $redirect .= '&linked_tomes=' . $attached;
    }
    header('Location: ' . $redirect);
    exit;
}

$result = (new SeriesRepository())->create([
    'titre' => (string) ($_POST['titre'] ?? ''),
    'publication_type' => 'irregulier',
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'notes' => (string) ($_POST['notes'] ?? ''),
    'tags' => BdSeriesMetadata::kindForStorage((string) ($_POST['kind'] ?? BdKind::BD)),
], MediaDomain::BD);

if (!is_int($result)) {
    header('Location: /ajouter-serie-bd.php?error=' . rawurlencode((string) $result)
        . '&titre=' . rawurlencode((string) ($_POST['titre'] ?? '')));
    exit;
}

$repo = new BdRepository();
if (BdRepository::isAvailable()) {
    $repo->registerSeriesInLibrary($result, $statut, $userId, $foyerId);
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

header('Location: ' . ($statut === LibraryStatut::WISHLIST
    ? View::bdSeriesUrl($result, 'tome', 'asc', ['statut' => LibraryStatut::WISHLIST])
    : View::bdSeriesUrl($result)));
exit;
