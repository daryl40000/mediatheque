<?php
/**
 * Enregistre les modifications manuelles d’une fiche film.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmListContext;
use Moncine\FilmManualEdit;
use Moncine\FilmRepository;
use Moncine\LibraryStatut;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /films.php');
    exit;
}

$filmId = (int) ($_POST['film_id'] ?? 0);
if ($filmId <= 0) {
    header('Location: /films.php');
    exit;
}

$film = (new FilmRepository())->findById($filmId);
$defaultList = ($film['statut'] ?? '') === LibraryStatut::WISHLIST
    ? FilmListContext::WISHLIST
    : FilmListContext::COLLECTION;
$listContext = FilmListContext::fromPost($_POST, $defaultList);
$filmBaseUrl = $listContext->filmUrl($filmId);

Csrf::rejectUnlessValid($_POST, $filmBaseUrl . '&edit=1');

$parsed = FilmManualEdit::parseExemplaireFromPost($_POST);
if (!$parsed['ok']) {
    header('Location: ' . $listContext->filmUrlWithQuery($filmId, [
        'save_error' => $parsed['error'],
        'edit' => '1',
    ]));
    exit;
}

$result = (new FilmRepository())->updateManual($filmId, $parsed['data']);
if ($result !== true) {
    header('Location: ' . $listContext->filmUrlWithQuery($filmId, [
        'save_error' => (string) $result,
        'edit' => '1',
    ]));
    exit;
}

header('Location: ' . $listContext->filmUrlWithQuery($filmId, ['saved' => '1']));
exit;
