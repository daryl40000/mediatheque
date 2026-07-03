<?php
/**
 * Enregistre la note personnelle sur un jeu (sans date).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;

MediaDomainGuards::ensureGameContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

$gameId = (int) ($_POST['game_id'] ?? 0);
$noteRaw = (string) ($_POST['note'] ?? '');

if ($gameId <= 0) {
    header('Location: /jeux.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();
$game = $repo->findByBibId($gameId, $userId, $foyerId);

$redirectError = static function (string $message) use ($gameId): void {
    header('Location: /jeu.php?id=' . $gameId . '&note_error=' . rawurlencode($message));
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

if ($game === null || ($game['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Ce jeu est introuvable ou encore dans vos envies.');
}

$parsedNote = HistoriqueRepository::parseNoteInput($noteRaw);
if (!$parsedNote['ok']) {
    $redirectError($parsedNote['error']);
}

if ($parsedNote['note'] === null) {
    $redirectError('Choisissez un ressenti parmi les cinq proposés.');
}

try {
    (new HistoriqueRepository())->setPersonalNote($gameId, $parsedNote['note']);
} catch (\Throwable $e) {
    $message = $e->getMessage();
    if ($message === '' || str_contains($message, 'SQLSTATE')) {
        $message = 'Impossible d’enregistrer la note. Rechargez la page et réessayez.';
    }
    $redirectError($message);
}

header('Location: /jeu.php?id=' . $gameId . '&note=' . (int) $parsedNote['note']);
exit;
