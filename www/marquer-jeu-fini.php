<?php
/**
 * Enregistre une fin de partie (date du jour ou passée).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameCompletionRepository;
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
$dateRaw = (string) ($_POST['date_fin'] ?? '');

if ($gameId <= 0) {
    header('Location: /jeux.php');
    exit;
}

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();
$game = $repo->findByBibId($gameId, $userId, $foyerId);

$redirectError = static function (string $message) use ($gameId): void {
    header('Location: /jeu.php?id=' . $gameId . '&fin_error=' . rawurlencode($message));
    exit;
};

if (!Csrf::validateFromPost($_POST)) {
    $redirectError(Csrf::REJECT_MESSAGE);
}

if ($game === null || ($game['statut'] ?? '') === LibraryStatut::WISHLIST) {
    $redirectError('Ce jeu est introuvable ou encore dans vos envies.');
}

if (!GameCompletionRepository::isAvailable()) {
    $redirectError('Fonctionnalité non disponible. Rechargez la page après mise à jour.');
}

$parsedDate = HistoriqueRepository::parseDateVueInput($dateRaw);
if (!$parsedDate['ok']) {
    $redirectError($parsedDate['error']);
}

try {
    (new GameCompletionRepository())->recordCompletion($gameId, $userId, $parsedDate['date']);
} catch (\Throwable $e) {
    $message = $e->getMessage();
    if ($message === '' || str_contains($message, 'SQLSTATE')) {
        $message = 'Impossible d’enregistrer la fin de partie. Rechargez la page et réessayez.';
    }
    $redirectError($message);
}

header('Location: /jeu.php?id=' . $gameId . '&fin=1&date=' . rawurlencode($parsedDate['date']));
exit;
