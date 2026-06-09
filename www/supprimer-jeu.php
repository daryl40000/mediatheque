<?php
/**
 * Supprime un jeu de la bibliothèque (collection ou envies).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureGameContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

$gameId = (int) ($_POST['game_id'] ?? 0);
if ($gameId <= 0) {
    header('Location: /jeux.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();
$game = $repo->findByBibId($gameId, $userId, $foyerId);
$isWishlist = ($game['statut'] ?? '') === LibraryStatut::WISHLIST;
$backUrl = $isWishlist ? '/jeux-envies.php' : '/jeux.php';
$gameUrl = View::gameUrl($gameId);

Csrf::rejectUnlessValid($_POST, $gameUrl);

if ($game === null) {
    header('Location: ' . $backUrl . '?delete_error=' . rawurlencode('Jeu introuvable ou déjà supprimé.'));
    exit;
}

$titre = (string) ($game['titre'] ?? '');
if (!$repo->deleteById($gameId, $userId, $foyerId)) {
    header('Location: ' . $gameUrl . '?delete_error=' . rawurlencode('Impossible de supprimer ce jeu.'));
    exit;
}

$sep = str_contains($backUrl, '?') ? '&' : '?';
header('Location: ' . $backUrl . $sep . 'deleted=1&deleted_title=' . rawurlencode($titre));
exit;
