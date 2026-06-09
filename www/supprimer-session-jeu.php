<?php
/**
 * Supprime une date de l’historique de sessions d’un jeu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\HistoriqueRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;

MediaDomainGuards::ensureGameContext();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

$gameId = (int) ($_POST['game_id'] ?? 0);
$historiqueId = (int) ($_POST['historique_id'] ?? 0);

if ($gameId <= 0) {
    header('Location: /jeux.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/jeu.php?id=' . $gameId);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$game = (new GameRepository())->findByBibId($gameId, $userId, $foyerId);
if ($game === null) {
    header('Location: /jeux.php');
    exit;
}

$deleted = (new HistoriqueRepository())->deleteViewing($historiqueId, $gameId);

if ($deleted) {
    header('Location: /jeu.php?id=' . $gameId . '&session_supprimee=1');
    exit;
}

header('Location: /jeu.php?id=' . $gameId . '&session_error=' . rawurlencode('Impossible de supprimer cette session.'));
exit;
