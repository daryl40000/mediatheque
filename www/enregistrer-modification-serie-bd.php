<?php
/**
 * Enregistre la modification d’une série BD (texte + couverture).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdSeriesMetadata;
use Moncine\Csrf;
use Moncine\MediaDomainGuards;
use Moncine\PosterStorage;
use Moncine\SeriesRepository;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext('/bd.php');
Csrf::rejectUnlessValid($_POST, '/bd.php');

$seriesId = (int) ($_POST['series_id'] ?? 0);
$redirectBase = '/modifier-serie-bd.php?series_id=' . $seriesId;

$repo = new SeriesRepository();
$series = $repo->findById($seriesId);
if ($series === null) {
    header('Location: /bd.php');
    exit;
}

$result = $repo->update($seriesId, [
    'titre' => (string) ($_POST['titre'] ?? ''),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'notes' => (string) ($_POST['notes'] ?? ''),
    'tags' => BdSeriesMetadata::kindForStorage((string) ($_POST['kind'] ?? '')),
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
