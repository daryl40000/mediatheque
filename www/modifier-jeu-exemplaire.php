<?php
/**
 * Enregistre les détails de l’exemplaire personnel (plateformes, supports, démat).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jeux.php');
    exit;
}

MediaDomainGuards::ensureGameContext();

$bibId = (int) ($_POST['game_id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$returnUrl = View::gameUrl($bibId);
$scope = (string) ($_POST['scope'] ?? '');
$isPlaytimeOnly = $scope === 'playtime';

if ($bibId <= 0) {
    header('Location: /jeux.php');
    exit;
}

$errorUrl = $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?');
if ($isPlaytimeOnly) {
    $errorUrl .= 'popover=playtime';
} else {
    $errorUrl .= 'edit=1';
}

Csrf::rejectUnlessValid($_POST, $errorUrl);

$repo = new GameRepository();
$game = $repo->findByBibId($bibId, $userId, $foyerId);
if ($game === null) {
    header('Location: /jeux.php');
    exit;
}

$result = $isPlaytimeOnly
    ? $repo->updateLibraryPlaytimeOnly($bibId, $_POST, $userId, $foyerId)
    : $repo->updateLibraryExemplaire($bibId, $_POST, $userId, $foyerId);

if ($result !== true) {
    header('Location: ' . $errorUrl . '&save_error=' . rawurlencode((string) $result));
    exit;
}

header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'saved=1');
exit;
