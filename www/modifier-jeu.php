<?php
/**
 * Modifier un jeu vidéo (administrateurs).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext();

if (!UserContext::canManageCatalog()) {
    header('Location: /jeux.php');
    exit;
}

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

$game = $bibId > 0 ? $repo->findByBibId($bibId, $userId, $foyerId) : null;
if ($game === null) {
    header('Location: /jeux.php');
    exit;
}

View::render('modifier-jeu', [
    'pageTitle' => 'Modifier — ' . (string) ($game['titre'] ?? ''),
    'game' => $game,
    'platformChoices' => GamePlatform::choices(),
    'knownGenres' => $repo->listKnownGenres(),
    'saveError' => trim((string) ($_GET['error'] ?? '')),
    'saved' => isset($_GET['saved']),
]);
