<?php
/**
 * Supprime un album de la bibliothèque.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureBdContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

$albumId = (int) ($_POST['album_id'] ?? 0);
if ($albumId <= 0) {
    header('Location: /bd.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();
$album = $repo->findByBibId($albumId, $userId, $foyerId);
$isWishlist = ($album['statut'] ?? '') === LibraryStatut::WISHLIST;
$backUrl = $isWishlist ? '/bd-envies.php' : '/bd.php';
$albumUrl = View::bdUrl($albumId);

Csrf::rejectUnlessValid($_POST, $albumUrl);

if ($album === null) {
    header('Location: ' . $backUrl . '?delete_error=' . rawurlencode('Album introuvable ou déjà supprimé.'));
    exit;
}

$titre = (string) ($album['display_titre'] ?? '');
if (!$repo->deleteById($albumId, $userId, $foyerId)) {
    header('Location: ' . $albumUrl . '?delete_error=' . rawurlencode('Impossible de supprimer cet album.'));
    exit;
}

$sep = str_contains($backUrl, '?') ? '&' : '?';
header('Location: ' . $backUrl . $sep . 'deleted=1&deleted_title=' . rawurlencode($titre));
exit;
