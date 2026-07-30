<?php
/**
 * Enregistre la note personnelle (ressenti) sur un livre.
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
$noteRaw = (string) ($_POST['note'] ?? '');

if ($bookId <= 0) {
    header('Location: /livres.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$bookUrl = View::livreUrl($bookId);

$redirectError = static function (string $message) use ($bookUrl): void {
    header('Location: ' . $bookUrl . '&note_error=' . rawurlencode($message) . '&popover=note');
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

$book = (new LivreRepository())->findByBibId($bookId, $userId, $foyerId);
if ($book === null || ($book['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Ce livre est introuvable ou encore dans vos envies.');
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

if ($parsedNote['note'] === null) {
    $redirectError('Choisissez un ressenti parmi les cinq proposés.');
}

try {
    (new HistoriqueRepository())->setPersonalNote($bookId, $parsedNote['note']);
} catch (\Throwable $e) {
    $redirectError('Impossible d’enregistrer la note. Réessayez.');
}

header('Location: ' . $bookUrl . '&note=' . (int) $parsedNote['note']);
exit;
