<?php
/**
 * Ajout / suppression d’une version recherchée sur une envie (support + EAN).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\LibraryStatut;
use Moncine\SupportPhysique;
use Moncine\UserContext;
use Moncine\WishlistTargetRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /souhaits.php');
    exit;
}

$filmId = max(0, (int) ($_POST['film_id'] ?? 0));
$redirect = '/film.php?id=' . $filmId;
if ($filmId <= 0) {
    header('Location: /souhaits.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, $redirect);

$repo = new FilmRepository();
$film = $repo->findById($filmId);
if ($film === null || ($film['statut'] ?? '') !== LibraryStatut::WISHLIST) {
    header('Location: ' . $redirect . '&wish_target_error=' . rawurlencode('Ce film n’est pas dans vos envies.'));
    exit;
}

if (!WishlistTargetRepository::tableExists()) {
    header('Location: ' . $redirect . '&wish_target_error=' . rawurlencode('Fonctionnalité non disponible (migration en attente).'));
    exit;
}

$targets = new WishlistTargetRepository();
$action = (string) ($_POST['action'] ?? 'add');
$oeuvreId = (int) ($film['oeuvre_id'] ?? 0);

if ($action === 'delete') {
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $result = $targets->delete($targetId, $filmId);
    $param = $result === true ? 'wish_target_deleted=1' : 'wish_target_error=' . rawurlencode((string) $result);
    header('Location: ' . $redirect . '&' . $param);
    exit;
}

if ($action === 'from_catalog') {
    $oeuvreEanId = (int) ($_POST['oeuvre_ean_id'] ?? 0);
    $result = $targets->addFromCatalogEan($filmId, $oeuvreEanId, $oeuvreId);
    $param = is_int($result) ? 'wish_target_added=1' : 'wish_target_error=' . rawurlencode((string) $result);
    header('Location: ' . $redirect . '&' . $param);
    exit;
}

$support = SupportPhysique::normalize((string) ($_POST['support_physique'] ?? ''));
$ean = (string) ($_POST['ean'] ?? '');
$label = trim((string) ($_POST['label'] ?? ''));

$result = $targets->add($filmId, $support, $ean, $label);
$param = is_int($result) ? 'wish_target_added=1' : 'wish_target_error=' . rawurlencode((string) $result);
header('Location: ' . $redirect . '&' . $param);
exit;
