<?php
/**
 * Formulaire : proposer un jeu au catalogue (utilisateur connecté).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogSubmission;
use Moncine\CatalogSubmissionRepository;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

Auth::enforceWebAccess();
MediaDomainGuards::ensureGameContext('/proposer-jeu.php');
CatalogSubmission::denyUnlessSubmitter();

if (!GameRepository::isAvailable()) {
    header('Location: /jeux.php');
    exit;
}

$userId = UserContext::currentUserId();
$repo = new CatalogSubmissionRepository();
$pending = $repo->listForUser($userId, CatalogSubmissionRepository::STATUS_PENDING);
$gameRepo = new GameRepository();

View::render('proposer-jeu', [
    'pageTitle' => 'Proposer un jeu',
    'saveError' => trim((string) ($_GET['save_error'] ?? '')),
    'pendingCount' => count($pending),
    'platformChoices' => GamePlatform::choices(),
    'knownGenres' => $gameRepo->listKnownGenres(),
]);
