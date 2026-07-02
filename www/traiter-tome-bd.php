<?php
/**
 * Met à jour un tome BD (catalogue + bibliothèque).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext();

$bibId = (int) ($_POST['album_id'] ?? 0);
$seriesId = (int) ($_POST['series_id'] ?? 0);
$returnUrl = $bibId > 0 ? View::bdUrl($bibId) : View::bdSeriesUrl($seriesId);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();

$result = $repo->updateTome($bibId, [
    'titre' => (string) ($_POST['titre'] ?? ''),
    'annee' => (int) ($_POST['annee'] ?? 0),
    'synopsis' => (string) ($_POST['synopsis'] ?? ''),
    'tome_numero' => (int) ($_POST['tome_numero'] ?? 0),
    'tome_ordre' => (float) ($_POST['tome_ordre'] ?? 0),
    'tome_label' => (string) ($_POST['tome_label'] ?? ''),
    'est_hors_serie' => !empty($_POST['est_hors_serie']),
    'scenariste' => (string) ($_POST['scenariste'] ?? ''),
    'dessinateur' => (string) ($_POST['dessinateur'] ?? ''),
    'editeur' => (string) ($_POST['editeur'] ?? ''),
    'genre' => (string) ($_POST['genre'] ?? ''),
    'support_possede' => !empty($_POST['support_possede']),
    'support_physique' => (string) ($_POST['support_physique'] ?? ''),
], $userId, $foyerId);

if ($result !== true) {
    header('Location: ' . $returnUrl . '&edit_error=' . rawurlencode((string) $result));
    exit;
}

$album = $repo->findByBibId($bibId, $userId, $foyerId);
$oeuvreId = (int) ($album['oeuvre_id'] ?? 0);

if ($oeuvreId > 0) {
    $uploadedBinary = null;
    if (
        isset($_FILES['cover_file'])
        && (int) ($_FILES['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $uploadedBinary = (string) file_get_contents((string) $_FILES['cover_file']['tmp_name']);
        if ($uploadedBinary !== '') {
            $beforePoster = trim((string) ($album['poster_url'] ?? ''));
            $repo->savePoster($oeuvreId, '', $uploadedBinary);
            $after = $repo->findByBibId($bibId, $userId, $foyerId);
            $afterPoster = trim((string) ($after['poster_url'] ?? ''));
            if ($afterPoster === '' || $afterPoster === $beforePoster) {
                header('Location: ' . $returnUrl . '&edit_error=' . rawurlencode(
                    'Couverture non enregistrée (format ou taille invalide).'
                ));
                exit;
            }
        }
    } else {
        $repo->savePoster($oeuvreId, (string) ($_POST['poster_url'] ?? ''), null);
    }
}

header('Location: ' . $returnUrl . '&saved=1');
exit;
