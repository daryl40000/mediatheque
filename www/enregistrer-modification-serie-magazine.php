<?php
/**
 * Enregistre la modification d’une série magazine (texte + logo).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\MagazineSeriesCategory;
use Moncine\MagazineSeriesTag;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\SeriesRepository;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /magazines.php');
    exit;
}

MediaDomainGuards::ensureMagazineContext('/magazines.php');
Csrf::rejectUnlessValid($_POST, '/magazines.php');

$seriesId = (int) ($_POST['series_id'] ?? 0);
$redirectBase = '/modifier-serie-magazine.php?series_id=' . $seriesId;

$repo = new SeriesRepository();
$series = $repo->findById($seriesId);
if ($series === null) {
    header('Location: /magazines.php');
    exit;
}

$result = $repo->update($seriesId, [
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
    'categories' => MagazineSeriesCategory::normalizeFromPost($_POST['categories'] ?? ''),
]);

if ($result !== true) {
    $params = http_build_query(['error' => (string) $result]);
    header('Location: ' . $redirectBase . '&' . $params);
    exit;
}

if (
    isset($_FILES['cover_file'])
    && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
) {
    $binary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
    $posterUrl = (new PosterStorage())->importBinaryForSeries($seriesId, $binary);
    if ($posterUrl !== '') {
        $repo->update($seriesId, ['poster_url' => $posterUrl]);
    }
}

header('Location: ' . $redirectBase . '&saved=1');
exit;
