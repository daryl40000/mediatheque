<?php
/**
 * Passe un jeu de la wishlist à la collection du foyer.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureGameContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux-envies.php');
    exit;
}

$gameId = (int) ($_POST['game_id'] ?? 0);
$return = (string) ($_POST['return'] ?? 'fiche');

if ($gameId <= 0) {
    header('Location: /jeux-envies.php');
    exit;
}

$redirectUrl = $return === 'envies'
    ? View::gamesWishlistUrl(
        (string) ($_POST['q'] ?? ''),
        (string) ($_POST['sort'] ?? 'titre'),
        (string) ($_POST['dir'] ?? 'asc')
    )
    : View::gameUrl($gameId);

Csrf::rejectUnlessValid($_POST, $redirectUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

if (!$repo->promoteToCollection($gameId, $userId, $foyerId)) {
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    if ($return === 'envies') {
        header('Location: ' . $redirectUrl . $sep . 'promote_error=' . rawurlencode('Impossible d’ajouter ce jeu à votre collection.'));
    } else {
        header('Location: /jeu.php?id=' . $gameId . '&promote_error=' . rawurlencode('Impossible d’ajouter ce jeu à votre collection.'));
    }
    exit;
}

if ($return === 'envies') {
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $sep . 'promoted=1');
    exit;
}

header('Location: /jeu.php?id=' . $gameId . '&promoted=1');
exit;
