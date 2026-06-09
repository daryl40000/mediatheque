<?php
/**
 * Fiche d’un jeu vidéo.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameRepository;
use Moncine\HistoriqueRepository;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureGameContext();

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new GameRepository();

$game = $bibId > 0 ? $repo->findByBibId($bibId, $userId, $foyerId) : null;

if ($game === null) {
    http_response_code(404);
    View::render('jeu', [
        'pageTitle' => 'Jeu introuvable',
        'game' => null,
        'magazineCoverage' => [],
        'saved' => false,
        'gameId' => 0,
        'isWishlist' => false,
        'listBackUrl' => '/jeux.php',
    ]);
    exit;
}

$isWishlist = ($game['statut'] ?? '') === LibraryStatut::WISHLIST;
$listBackUrl = $isWishlist ? '/jeux-envies.php' : '/jeux.php';

$historique = new HistoriqueRepository();
$noteSur10 = $isWishlist ? null : $historique->getNoteSur10($bibId);
$noteFoyerMoyenne = $isWishlist ? null : $historique->getFoyerAverageNote($bibId);

$saved = isset($_GET['saved']);
$magazineCoverage = MagazineGameLink::isAvailable()
    ? (new MagazineGameLink())->listMagazineCoverageForGame((int) ($game['oeuvre_id'] ?? 0), $userId, $foyerId)
    : [];

View::render('jeu', [
    'pageTitle' => (string) ($game['titre'] ?? 'Jeu'),
    'game' => $game,
    'magazineCoverage' => $magazineCoverage,
    'saved' => $saved,
    'canManageCatalog' => UserContext::canManageCatalog(),
    'gameId' => $bibId,
    'isWishlist' => $isWishlist,
    'listBackUrl' => $listBackUrl,
    'noteSur10' => $noteSur10,
    'noteFoyerMoyenne' => $noteFoyerMoyenne,
    'addedAtLabel' => GameRepository::formatAddedAt((string) ($game['created_at'] ?? '')),
]);
