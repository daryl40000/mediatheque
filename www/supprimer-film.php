<?php
/**
 * Supprime un film de la collection (fiche film).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmListContext;
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

$repo = new FilmRepository();
$film = $repo->findById($filmId);
$defaultList = ($film['statut'] ?? '') === LibraryStatut::WISHLIST
    ? FilmListContext::WISHLIST
    : FilmListContext::COLLECTION;
$listContext = FilmListContext::fromPost($_POST, $defaultList);
$filmUrl = $filmId > 0 ? $listContext->filmUrl($filmId) : $listContext->backUrl();

Csrf::rejectUnlessValid($_POST, $filmUrl);

if ($film === null) {
    header('Location: /films.php?bulk_error=' . rawurlencode('Film introuvable ou déjà supprimé.'));
    exit;
}

$titre = (string) ($film['titre']);
if (!$repo->deleteById($filmId)) {
    header('Location: ' . $listContext->filmUrlWithQuery($filmId, [
        'delete_error' => 'Impossible de supprimer ce film.',
    ]));
    exit;
}

$back = $listContext->backUrl();
$sep = str_contains($back, '?') ? '&' : '?';
header('Location: ' . $back . $sep . 'deleted=1&deleted_title=' . rawurlencode($titre));
exit;
