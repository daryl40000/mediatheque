<?php
/**
 * Enregistre une lecture de livre (aujourd’hui ou date passée).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /livres.php');
    exit;
}

MediaDomainGuards::ensureLivreContext();

$bookId = (int) ($_POST['book_id'] ?? 0);
$return = (string) ($_POST['return'] ?? '');
$dateRaw = (string) ($_POST['date_vue'] ?? '');
$noteRaw = (string) ($_POST['note'] ?? '');

if ($bookId <= 0) {
    header('Location: /livres.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$bookUrl = View::livreUrl($bookId);

$redirectError = static function (string $message) use ($return, $bookUrl): void {
    if ($return === 'fiche') {
        header('Location: ' . $bookUrl . '&lu_error=' . rawurlencode($message) . '&popover=lu');
        exit;
    }
    header('Location: /livres.php?lu_error=' . rawurlencode($message));
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

$book = (new LivreRepository())->findByBibId($bookId, $userId, $foyerId);
if ($book === null || ($book['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Ce livre est introuvable ou encore dans vos envies.');
}

$parsedDate = HistoriqueRepository::parseDateVueInput($dateRaw);
if (!$parsedDate['ok']) {
    $redirectError($parsedDate['error']);
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

try {
    (new HistoriqueRepository())->recordViewing($bookId, $parsedDate['date'], $parsedNote['note']);
} catch (\Throwable $e) {
    $redirectError('Impossible d’enregistrer la lecture. Réessayez.');
}

$params = [
    'lu' => '1',
    'lu_date' => HistoriqueRepository::formatDateVue($parsedDate['date']),
];
if ($parsedNote['note'] !== null) {
    $params['lu_note'] = (string) $parsedNote['note'];
}

if ($return === 'fiche') {
    header('Location: ' . $bookUrl . '&' . http_build_query($params));
    exit;
}

header('Location: /livres.php?lu=1');
exit;
