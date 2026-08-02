<?php
/**
 * Liste des remakes liés à la collection (jaquettes remake / jeu d’origine).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameCollectionStats;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext('/statistiques.php');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$pairs = (new GameCollectionStats())->listRemakePairs($userId, $foyerId);

View::render('stats-remakes-jeux', [
    'pageTitle' => 'Remakes — statistiques',
    'remakePairs' => $pairs,
    'backUrl' => '/statistiques.php',
    'wideLayout' => true,
]);
