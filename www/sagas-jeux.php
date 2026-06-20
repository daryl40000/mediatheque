<?php
/**
 * Franchises / sagas jeux vidéo (données IGDB oeuvre_jeu.franchise).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameFranchiseRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/sagas-jeux.php');

$repo = new GameFranchiseRepository();
$foyerId = UserContext::currentFoyerId();
$userId = UserContext::currentUserId();
$franchise = trim((string) ($_GET['franchise'] ?? $_POST['franchise'] ?? ''));
$searched = $franchise !== '';

if (!GameFranchiseRepository::isAvailable()) {
    View::render('sagas-jeux', [
        'pageTitle' => 'Sagas jeux',
        'franchise' => '',
        'searched' => false,
        'games' => [],
        'franchises' => [],
        'knownSagas' => [],
        'moduleError' => 'Le module sagas jeux n’est pas disponible (migration 047 requise).',
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'rename_franchise') {
    $oldName = trim((string) ($_POST['franchise_old'] ?? ''));
    $redirectFranchise = $oldName !== '' ? $oldName : $franchise;
    $failUrl = View::gameFranchiseUrl($redirectFranchise);

    Csrf::rejectUnlessValid($_POST, $failUrl);

    $newName = trim((string) ($_POST['franchise_new'] ?? ''));
    $result = $repo->renameFranchise($oldName, $newName, $foyerId);

    if (!$result['ok']) {
        header('Location: ' . $failUrl . '&rename_error=' . rawurlencode($result['error']));
        exit;
    }

    $params = http_build_query([
        'renamed' => '1',
        'count' => $result['updated'],
    ]);
    header('Location: ' . View::gameFranchiseUrl($newName) . '&' . $params);
    exit;
}

$games = $searched ? $repo->findByFranchise($foyerId, $userId, $franchise) : [];
$franchises = $repo->listFranchisesWithCounts($foyerId);
$knownSagas = $repo->listKnownSagas();

View::render('sagas-jeux', [
    'pageTitle' => $searched ? 'Saga : ' . $franchise : 'Sagas jeux',
    'franchise' => $franchise,
    'searched' => $searched,
    'games' => $games,
    'franchises' => $franchises,
    'knownSagas' => $knownSagas,
    'moduleError' => '',
]);
