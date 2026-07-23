<?php
/**
 * Numéros magazines ayant offert un jeu (catégorie « Jeux offerts »).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineJeuxOffertsList;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines-jeux-offerts.php');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$seriesGroups = MagazineJeuxOffertsList::isAvailable()
    ? (new MagazineJeuxOffertsList())->listGroupedBySeries($userId, $foyerId)
    : [];

$mentionCount = 0;
foreach ($seriesGroups as $group) {
    $mentionCount += count($group['issues'] ?? []);
}

View::render('magazines-jeux-offerts', [
    'pageTitle' => 'Jeux offerts',
    'seriesGroups' => $seriesGroups,
    'mentionCount' => $mentionCount,
    'seriesCount' => count($seriesGroups),
    'moduleAvailable' => MagazineJeuxOffertsList::isAvailable(),
    'wideLayout' => true,
]);
