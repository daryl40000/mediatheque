<?php
/**
 * Magazines qui traitent un jeu (tests, previews, dossiers…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameRepository;
use Moncine\MagazineGameLink;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::ensureGameContext();

$oeuvreId = (int) ($_GET['oeuvre_id'] ?? 0);
$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

$game = null;
if ($bibId > 0) {
    $game = $repo->findByBibId($bibId, $userId, $foyerId);
    if ($game !== null) {
        $oeuvreId = (int) ($game['oeuvre_id'] ?? $oeuvreId);
    }
}
if ($game === null && $oeuvreId > 0) {
    $game = $repo->findCatalogByOeuvreId($oeuvreId);
}

$issues = [];
if ($oeuvreId > 0 && MagazineGameLink::isAvailable()) {
    $issues = (new MagazineGameLink())->listIssueCoverageForGame($oeuvreId, $userId, $foyerId);
}

$gameTitle = (string) ($game['display_titre'] ?? $game['titre'] ?? 'Jeu');
$backUrl = $bibId > 0
    ? View::gameNavUrl($bibId)
    : ($oeuvreId > 0 ? View::oeuvreJeuUrl($oeuvreId) : '/jeux.php');

if ($game === null) {
    http_response_code(404);
}

View::render('jeu-magazines', [
    'pageTitle' => 'Magazines — ' . $gameTitle,
    'game' => $game,
    'gameTitle' => $gameTitle,
    'issues' => $issues,
    'backUrl' => $backUrl,
    'oeuvreId' => $oeuvreId,
    'bibId' => $bibId,
    'wideLayout' => true,
]);
