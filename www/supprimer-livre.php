<?php
/**
 * Retire un livre de la bibliothèque (collection ou envies).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureLivreContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres.php');
    exit;
}

$bookId = (int) ($_POST['book_id'] ?? 0);
if ($bookId <= 0) {
    header('Location: /livres.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();
$book = $repo->findByBibId($bookId, $userId, $foyerId);
$isWishlist = ($book['statut'] ?? '') === LibraryStatut::WISHLIST;
$backUrl = $isWishlist ? '/livres-envies.php' : '/livres.php';
$bookUrl = View::livreUrl($bookId);

Csrf::rejectUnlessValid($_POST, $bookUrl);

if ($book === null) {
    header('Location: ' . $backUrl . '?delete_error=' . rawurlencode('Livre introuvable ou déjà supprimé.'));
    exit;
}

$titre = (string) ($book['titre'] ?? '');
if (!$repo->deleteFromLibrary($bookId, $userId, $foyerId)) {
    header('Location: ' . $bookUrl . '&delete_error=' . rawurlencode('Impossible de supprimer ce livre.'));
    exit;
}

$sep = str_contains($backUrl, '?') ? '&' : '?';
header('Location: ' . $backUrl . $sep . 'deleted=1&deleted_title=' . rawurlencode($titre));
exit;
