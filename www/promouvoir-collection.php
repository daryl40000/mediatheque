<?php
/**
 * Passe un film de la wishlist à la collection (depuis la fiche film).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\SupportPhysique;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /souhaits.php');
    exit;
}

$filmId = (int) ($_POST['film_id'] ?? 0);
if ($filmId <= 0) {
    header('Location: /souhaits.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/film.php?id=' . $filmId);

$repo = new FilmRepository();
$supportRaw = (string) ($_POST['support_physique'] ?? '');
$supportKey = SupportPhysique::normalize($supportRaw);
$targetId = (int) ($_POST['wishlist_target_id'] ?? 0);
$wishlistTargetId = $targetId > 0 ? $targetId : null;

if (!$repo->promoteToCollection($filmId, $supportKey, '', $wishlistTargetId)) {
    header('Location: /film.php?id=' . $filmId . '&promote_error=' . rawurlencode('Impossible d’ajouter à vos films.'));
    exit;
}

header('Location: /film.php?id=' . $filmId . '&promoted=1');
exit;
