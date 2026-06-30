<?php
/**
 * Passe un album BD de la wishlist à la collection du foyer.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureBdContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd-envies.php');
    exit;
}

$albumId = (int) ($_POST['album_id'] ?? 0);
$return = (string) ($_POST['return'] ?? 'fiche');

if ($albumId <= 0) {
    header('Location: /bd-envies.php');
    exit;
}

$redirectUrl = $return === 'envies'
    ? View::bdWishlistUrl(
        (string) ($_POST['q'] ?? ''),
        (string) ($_POST['sort'] ?? 'titre'),
        (string) ($_POST['dir'] ?? 'asc')
    )
    : View::bdUrl($albumId);

Csrf::rejectUnlessValid($_POST, $redirectUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();

if (!$repo->promoteToCollection($albumId, $userId, $foyerId)) {
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $sep . 'promote_error=' . rawurlencode('Impossible d’ajouter cet album à votre collection.'));
    exit;
}

$sep = str_contains($redirectUrl, '?') ? '&' : '?';
header('Location: ' . $redirectUrl . $sep . 'promoted=1');
exit;
