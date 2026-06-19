<?php
/**
 * Fiche d’un jeu vidéo.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameAttachmentRepository;
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
        'attachments' => [],
    ]);
    exit;
}

$isWishlist = ($game['statut'] ?? '') === LibraryStatut::WISHLIST;
$listBackUrl = $isWishlist ? '/jeux-envies.php' : '/jeux.php';

$historique = new HistoriqueRepository();
$noteSur10 = $isWishlist ? null : $historique->getNoteSur10($bibId);
$noteFoyerMoyenne = $isWishlist ? null : $historique->getFoyerAverageNote($bibId);

$saved = isset($_GET['saved']);
$attachments = GameAttachmentRepository::isAvailable()
    ? (new GameAttachmentRepository())->listForBibliotheque($bibId)
    : [];
$magazineCoverage = MagazineGameLink::isAvailable()
    ? (new MagazineGameLink())->listMagazineCoverageForGame((int) ($game['oeuvre_id'] ?? 0), $userId, $foyerId)
    : [];

$baseGame = null;
$extensions = [];
$originalGame = null;
$remakes = [];
$oeuvreId = (int) ($game['oeuvre_id'] ?? 0);

if (GameRepository::hasExtensionColumns()) {
    $baseGameOeuvreId = (int) ($game['base_game_oeuvre_id'] ?? 0);
    $isExtension = !empty($game['is_extension']);

    if ($isExtension && $baseGameOeuvreId > 0) {
        $baseGame = $repo->findCatalogByOeuvreId($baseGameOeuvreId);
        if ($baseGame !== null) {
            $baseBibId = $repo->findLibraryBibIdForCatalogOeuvre($baseGameOeuvreId, $userId, $foyerId);
            $baseGame['library_bib_id'] = $baseBibId ?? 0;
            $baseGame['library_url'] = $baseBibId !== null && $baseBibId > 0 ? View::gameUrl($baseBibId) : '';
        }
    }

    if (!$isExtension && $oeuvreId > 0) {
        $extensions = $repo->listExtensionsForBaseGame($oeuvreId, $userId, $foyerId);
    }
}

if (GameRepository::hasRemakeColumns()) {
    $originalGameOeuvreId = (int) ($game['original_game_oeuvre_id'] ?? 0);
    $isRemake = !empty($game['is_remake']);

    if ($isRemake && $originalGameOeuvreId > 0) {
        $originalGame = $repo->findCatalogByOeuvreId($originalGameOeuvreId);
        if ($originalGame !== null) {
            $originalBibId = $repo->findLibraryBibIdForCatalogOeuvre($originalGameOeuvreId, $userId, $foyerId);
            $originalGame['library_bib_id'] = $originalBibId ?? 0;
            $originalGame['library_url'] = $originalBibId !== null && $originalBibId > 0 ? View::gameUrl($originalBibId) : '';
        }
    }

    if (!$isRemake && $oeuvreId > 0) {
        $remakes = $repo->listRemakesForOriginalGame($oeuvreId, $userId, $foyerId);
    }
}

View::render('jeu', [
    'pageTitle' => (string) ($game['titre'] ?? 'Jeu'),
    'game' => $game,
    'magazineCoverage' => $magazineCoverage,
    'baseGame' => $baseGame,
    'extensions' => $extensions,
    'originalGame' => $originalGame,
    'remakes' => $remakes,
    'saved' => $saved,
    'canManageCatalog' => UserContext::canManageCatalog(),
    'gameId' => $bibId,
    'isWishlist' => $isWishlist,
    'listBackUrl' => $listBackUrl,
    'noteSur10' => $noteSur10,
    'noteFoyerMoyenne' => $noteFoyerMoyenne,
    'addedAtLabel' => GameRepository::formatAddedAt((string) ($game['created_at'] ?? '')),
    'attachments' => $attachments,
]);
