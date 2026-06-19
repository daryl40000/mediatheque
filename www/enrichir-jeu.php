<?php
/**
 * Enrichissement IGDB d’un jeu de la bibliothèque (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\GameEnricher;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$gameId = (int) ($_POST['game_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'enrich');
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$returnUrl = $gameId > 0 ? View::gameUrl($gameId) : '/jeux.php';

if ($gameId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

Csrf::rejectUnlessValid($_POST, $returnUrl);

$enricher = new GameEnricher();

if ($action === 'igdb') {
    $result = $enricher->correctWithIgdbId($gameId, (string) ($_POST['igdb_id'] ?? ''), $userId, $foyerId);
} else {
    $result = $enricher->enrichOne($gameId, $userId, $foyerId);
}

$status = $result['ok'] ? 'ok' : ($result['not_found'] ? 'not_found' : 'error');
$params = http_build_query([
    'enrich' => $status,
    'enrich_msg' => $result['message'],
]);

$sep = str_contains($returnUrl, '?') ? '&' : '?';
header('Location: ' . $returnUrl . $sep . $params);
exit;
