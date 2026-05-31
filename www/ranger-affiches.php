<?php
/**
 * Télécharge les affiches TMDB (HTTPS) vers MONCINE_DATA/posters/ par lots.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\PosterStorage;
use Moncine\View;

$storage = new PosterStorage();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/ranger-affiches.php');

    $result = $storage->migrateRemoteBatch(15);
    $message = sprintf(
        '%d affiche(s) enregistrée(s) localement, %d échec(s).',
        $result['downloaded'],
        $result['failed']
    );
    if ($result['remaining'] > 0) {
        $message .= ' Il en reste ' . $result['remaining'] . ' — relancez le bouton.';
    } else {
        $message .= ' Toutes les affiches distantes ont été copiées.';
    }
    $errors = $result['errors'];
}

View::render('ranger-affiches', [
    'pageTitle' => 'Affiches locales',
    'message' => $message,
    'errors' => $errors,
    'remoteCount' => $storage->countRemotePosters(),
    'localCount' => $storage->countLocalPosters(),
]);
