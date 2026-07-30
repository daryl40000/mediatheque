<?php
/**
 * Passe un livre des envies vers la collection.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureLivreContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres-envies.php');
    exit;
}

$bookId = (int) ($_POST['book_id'] ?? 0);
$return = (string) ($_POST['return'] ?? 'fiche');

if ($bookId <= 0) {
    header('Location: /livres-envies.php');
    exit;
}

$redirectUrl = $return === 'envies'
    ? View::livresWishlistUrl(
        (string) ($_POST['q'] ?? ''),
        (string) ($_POST['sort'] ?? 'titre'),
        (string) ($_POST['dir'] ?? 'asc')
    )
    : View::livreUrl($bookId);

Csrf::rejectUnlessValid($_POST, $redirectUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new LivreRepository();

if (!$repo->promoteToCollection($bookId, $userId, $foyerId)) {
    $sep = str_contains($redirectUrl, '?') ? '&' : '?';
    header('Location: ' . $redirectUrl . $sep . 'promote_error=' . rawurlencode(
        'Impossible d’ajouter ce livre à votre collection.'
    ));
    exit;
}

$sep = str_contains($redirectUrl, '?') ? '&' : '?';
header('Location: ' . $redirectUrl . $sep . 'promoted=1');
exit;
