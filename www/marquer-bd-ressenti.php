<?php
/**
 * Enregistre la note personnelle sur un album BD (sans date).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\Csrf;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /bd.php');
    exit;
}

MediaDomainGuards::ensureBdContext();

$albumId = (int) ($_POST['album_id'] ?? 0);
$noteRaw = (string) ($_POST['note'] ?? '');

if ($albumId <= 0) {
    header('Location: /bd.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$albumUrl = View::bdUrl($albumId);

$redirectError = static function (string $message) use ($albumUrl): void {
    header('Location: ' . $albumUrl . '&note_error=' . rawurlencode($message) . '&popover=note');
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

$album = (new BdRepository())->findByBibId($albumId, $userId, $foyerId);
if ($album === null || ($album['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Cet album est introuvable ou encore dans vos envies.');
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

if ($parsedNote['note'] === null) {
    $redirectError('Choisissez un ressenti parmi les cinq proposés.');
}

try {
    (new HistoriqueRepository())->setPersonalNote($albumId, $parsedNote['note']);
} catch (\Throwable $e) {
    $redirectError('Impossible d’enregistrer la note. Réessayez.');
}

header('Location: ' . $albumUrl . '&note=' . (int) $parsedNote['note']);
exit;
