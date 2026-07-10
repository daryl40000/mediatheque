<?php
/**
 * Fiche d’un jeu vidéo.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\GameAttachmentRepository;
use Moncine\GameCompletionRepository;
use Moncine\GamePlatform;
use Moncine\GameFranchiseRepository;
use Moncine\GameRelatedSections;
use Moncine\GameRepository;
use Moncine\HistoriqueRepository;
use Moncine\IgdbConfig;
use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SocialRessentiService;
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
$monRessenti = $isWishlist ? null : $historique->getBestRessentiScore($bibId);

$oeuvreId = (int) ($game['oeuvre_id'] ?? 0);
$socialRessentis = !$isWishlist && $oeuvreId > 0
    ? (new SocialRessentiService())->listAroundOeuvre(
        $oeuvreId,
        MediaDomain::JEU,
        $userId,
        $foyerId
    )
    : ['foyer' => [], 'friends' => []];

$saved = isset($_GET['saved']);
$saveError = trim((string) ($_GET['save_error'] ?? ''));
$allowedPopovers = ['note', 'playtime', 'edit', 'finish'];
$popoverOpen = '';
if (!empty($_GET['note_error'])) {
    $popoverOpen = 'note';
} elseif (!empty($_GET['fin_error'])) {
    $popoverOpen = 'finish';
} elseif (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
} elseif (isset($_GET['edit']) || $saveError !== '') {
    $popoverOpen = 'edit';
}
$editOpen = $popoverOpen === 'edit';
$enrichStatus = null;
$enrichMessage = '';
if (isset($_GET['enrich'])) {
    $enrichStatus = match ((string) $_GET['enrich']) {
        'ok' => 'ok',
        'not_found' => 'not_found',
        default => 'error',
    };
    $enrichMessage = (string) ($_GET['enrich_msg'] ?? '');
    $refreshed = $repo->findByBibId($bibId, $userId, $foyerId);
    if ($refreshed !== null) {
        $game = $refreshed;
    }
}

$attachments = GameAttachmentRepository::isAvailable()
    ? (new GameAttachmentRepository())->listForBibliotheque($bibId)
    : [];
$magazineIssues = MagazineGameLink::isAvailable()
    ? (new MagazineGameLink())->listIssueCoverageForGame((int) ($game['oeuvre_id'] ?? 0), $userId, $foyerId)
    : [];
$magazineIssueCount = count($magazineIssues);

$gameCompletions = [];
$completionCount = 0;
if (!$isWishlist && GameCompletionRepository::isAvailable()) {
    $completionRepo = new GameCompletionRepository();
    $gameCompletions = $completionRepo->listForGame($bibId, $userId);
    $completionCount = count($gameCompletions);
}

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
            $baseGame = array_merge(
                $baseGame,
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $baseGameOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($baseGameOeuvreId),
                ),
            );
        }
    }

    if (!$isExtension && $oeuvreId > 0) {
        $extensions = $repo->listCatalogExtensionsForBaseGame($oeuvreId);
        foreach ($extensions as $index => $row) {
            $childOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($childOeuvreId <= 0) {
                continue;
            }
            $extensions[$index] = array_merge(
                $extensions[$index],
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $childOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($childOeuvreId),
                ),
            );
        }
    }
}

if (GameRepository::hasRemakeColumns()) {
    $originalGameOeuvreId = (int) ($game['original_game_oeuvre_id'] ?? 0);
    $isRemake = !empty($game['is_remake']);

    if ($isRemake && $originalGameOeuvreId > 0) {
        $originalGame = $repo->findCatalogByOeuvreId($originalGameOeuvreId);
        if ($originalGame !== null) {
            $originalGame = array_merge(
                $originalGame,
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $originalGameOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($originalGameOeuvreId),
                ),
            );
        }
    }

    if (!$isRemake && $oeuvreId > 0) {
        $remakes = $repo->listCatalogRemakesForOriginalGame($oeuvreId);
        foreach ($remakes as $index => $row) {
            $childOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($childOeuvreId <= 0) {
                continue;
            }
            $remakes[$index] = array_merge(
                $remakes[$index],
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $childOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($childOeuvreId),
                ),
            );
        }
    }
}

$franchiseGames = [];
$franchiseName = GameRelatedSections::resolveFranchiseName($game, $baseGame, $originalGame);
if ($franchiseName !== '' && GameFranchiseRepository::isAvailable() && $oeuvreId > 0) {
    $franchiseRepo = new GameFranchiseRepository();
    $franchiseGames = $franchiseRepo->listCatalogByFranchise($franchiseName, $oeuvreId);
    foreach ($franchiseGames as $index => $row) {
        $sagaOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
        if ($sagaOeuvreId <= 0) {
            continue;
        }
        $franchiseGames[$index] = array_merge(
            $franchiseGames[$index],
            GameRelatedSections::libraryStateForRelatedOeuvre(
                $repo,
                $sagaOeuvreId,
                $userId,
                $foyerId,
                View::oeuvreJeuUrl($sagaOeuvreId),
            ),
        );
    }
}

View::render('jeu', [
    'pageTitle' => (string) ($game['display_titre'] ?? $game['titre'] ?? 'Jeu'),
    'game' => $game,
    'magazineCoverage' => $magazineIssues,
    'magazineIssueCount' => $magazineIssueCount,
    'baseGame' => $baseGame,
    'extensions' => $extensions,
    'originalGame' => $originalGame,
    'remakes' => $remakes,
    'franchiseGames' => $franchiseGames,
    'saved' => $saved,
    'saveError' => $saveError,
    'editOpen' => $editOpen,
    'popoverOpen' => $popoverOpen,
    'platformChoices' => GamePlatform::choices(),
    'canManageCatalog' => UserContext::canManageCatalog(),
    'showIgdbEnrich' => UserContext::canManageCatalog() && GameRepository::hasIgdbColumns(),
    'hasIgdbCredentials' => IgdbConfig::hasCredentials() && GameRepository::hasIgdbColumns(),
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
    'currentIgdbId' => (int) ($game['igdb_id'] ?? 0),
    'gameId' => $bibId,
    'isWishlist' => $isWishlist,
    'listBackUrl' => $listBackUrl,
    'monRessenti' => $monRessenti,
    'socialRessentis' => $socialRessentis,
    'gameCompletions' => $gameCompletions,
    'completionCount' => $completionCount,
    'addedAtLabel' => GameRepository::formatAddedAt((string) ($game['created_at'] ?? '')),
    'attachments' => $attachments,
]);
