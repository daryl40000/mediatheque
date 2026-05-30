<?php
/**
 * Supprime une date de l’historique des visions d’un film.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\HistoriqueRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /films.php');
    exit;
}

$filmId = (int) ($_POST['film_id'] ?? 0);
$historiqueId = (int) ($_POST['historique_id'] ?? 0);

if ($filmId <= 0) {
    header('Location: /films.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/film.php?id=' . $filmId);

$repo = new HistoriqueRepository();
$deleted = $repo->deleteViewing($historiqueId, $filmId);

if ($deleted) {
    header('Location: /film.php?id=' . $filmId . '&vision_supprimee=1');
    exit;
}

header('Location: /film.php?id=' . $filmId . '&vision_error=' . rawurlencode('Impossible de supprimer cette vision.'));
