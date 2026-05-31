<?php
/**
 * Enregistre une nouvelle série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
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
