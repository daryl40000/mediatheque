<?php
/**
 * Fiche catalogue — jeu vidéo (administration).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\GamePlatform;
use Moncine\GameCompletionRepository;
use Moncine\GameFranchiseRepository;
use Moncine\GameRelatedSections;
use Moncine\GameRepository;
use Moncine\MagazineGameLink;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessCatalogAvailable();

$oeuvreId = (int) ($_GET['id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_GET);
$catalogSearch = $catalogListContext->search();
$catalogSort = $catalogListContext->sortBy();
$catalogDir = $catalogListContext->sortDir();
$catalogPage = $catalogListContext->page();
$catalogueBackUrl = $catalogListContext->backUrl();

$admin = new CatalogAdmin();
$detail = $oeuvreId > 0 ? $admin->findOeuvreDetail($oeuvreId) : null;

if ($detail === null) {
    View::render('oeuvre-jeu', [
        'pageTitle' => 'Jeu introuvable',
        'game' => null,
        'library' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
    ]);
    exit;
}

$oeuvre = $detail['oeuvre'];
$domain = MediaDomain::normalize((string) ($oeuvre['media_domain'] ?? MediaDomain::FILM));
if ($domain !== MediaDomain::JEU) {
    header('Location: ' . View::catalogOeuvreDetailUrl(
        $oeuvreId,
        $domain,
        $catalogSearch,
        $catalogSort,
        $catalogDir,
        $catalogPage
    ));
    exit;
}

$repo = new GameRepository();
$game = GameRepository::isAvailable() ? $repo->findCatalogByOeuvreId($oeuvreId) : null;

if ($game === null) {
    View::render('oeuvre-jeu', [
        'pageTitle' => 'Jeu introuvable',
        'game' => null,
        'library' => null,
        'libraryCount' => 0,
        'catalogListContext' => $catalogListContext,
        'catalogueBackUrl' => $catalogueBackUrl,
    ]);
    exit;
}

$baseGame = null;
$catalogExtensions = [];
$originalGame = null;
$catalogRemakes = [];
if (GameRepository::hasExtensionColumns()) {
    if (!empty($game['is_extension']) && (int) ($game['base_game_oeuvre_id'] ?? 0) > 0) {
        $baseGame = $repo->findCatalogByOeuvreId((int) $game['base_game_oeuvre_id']);
        if ($baseGame !== null) {
            $userId = UserContext::currentUserId();
            $foyerId = UserContext::currentFoyerId();
            $baseOeuvreId = (int) $baseGame['oeuvre_id'];
            $baseGame = array_merge(
                $baseGame,
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $baseOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($baseOeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage),
                ),
            );
        }
        $game['base_game_label'] = trim((string) ($baseGame['titre'] ?? ''));
    } elseif ((int) ($game['oeuvre_id'] ?? 0) > 0) {
        $catalogExtensions = $repo->listCatalogExtensionsForBaseGame((int) $game['oeuvre_id']);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        foreach ($catalogExtensions as $index => $row) {
            $childOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($childOeuvreId <= 0) {
                continue;
            }
            $catalogExtensions[$index] = array_merge(
                $catalogExtensions[$index],
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $childOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($childOeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage),
                ),
            );
        }
    }
}

if (GameRepository::hasRemakeColumns()) {
    if (!empty($game['is_remake']) && (int) ($game['original_game_oeuvre_id'] ?? 0) > 0) {
        $originalGame = $repo->findCatalogByOeuvreId((int) $game['original_game_oeuvre_id']);
        if ($originalGame !== null) {
            $userId = UserContext::currentUserId();
            $foyerId = UserContext::currentFoyerId();
            $originalOeuvreId = (int) $originalGame['oeuvre_id'];
            $originalGame = array_merge(
                $originalGame,
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $originalOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($originalOeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage),
                ),
            );
        }
        $game['original_game_label'] = trim((string) ($originalGame['titre'] ?? ''));
    } elseif ((int) ($game['oeuvre_id'] ?? 0) > 0) {
        $catalogRemakes = $repo->listCatalogRemakesForOriginalGame((int) $game['oeuvre_id']);
        $userId = UserContext::currentUserId();
        $foyerId = UserContext::currentFoyerId();
        foreach ($catalogRemakes as $index => $row) {
            $childOeuvreId = (int) ($row['oeuvre_id'] ?? 0);
            if ($childOeuvreId <= 0) {
                continue;
            }
            $catalogRemakes[$index] = array_merge(
                $catalogRemakes[$index],
                GameRelatedSections::libraryStateForRelatedOeuvre(
                    $repo,
                    $childOeuvreId,
                    $userId,
                    $foyerId,
                    View::oeuvreJeuUrl($childOeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage),
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
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
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
                View::oeuvreJeuUrl($sagaOeuvreId, $catalogSearch, $catalogSort, $catalogDir, $catalogPage),
            ),
        );
    }
}

$saveError = (string) ($_GET['save_error'] ?? '');
$posterUploadError = (string) ($_GET['poster_error'] ?? '');
$editOpen = isset($_GET['edit']) || $saveError !== '';
$posterUploadOpen = $posterUploadError !== '';

$enrichStatus = null;
$enrichMessage = '';
if (isset($_GET['enrich'])) {
    $enrichStatus = match ((string) $_GET['enrich']) {
        'ok' => 'ok',
        'not_found' => 'not_found',
        default => 'error',
    };
    $enrichMessage = (string) ($_GET['enrich_msg'] ?? '');
    $refreshed = $repo->findCatalogByOeuvreId($oeuvreId);
    if ($refreshed !== null) {
        $game = $refreshed;
    }
}

if (isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1') {
    $refreshed = $repo->findCatalogByOeuvreId($oeuvreId);
    if ($refreshed !== null) {
        $game = $refreshed;
    }
}

$oeuvreNav = CatalogAdmin::canAccess()
    ? $admin->getOeuvreNavigation(
        $oeuvreId,
        $catalogSearch,
        $catalogSort,
        $catalogDir,
        $catalogListContext->mediaDomain()
    )
    : null;
$library = $detail['library'];
$libraryBibId = null;
if ($library !== null) {
    $libraryBibId = $repo->findLibraryBibIdForCatalogOeuvre(
        $oeuvreId,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    );
}

$magazineIssueCount = MagazineGameLink::isAvailable()
    ? (new MagazineGameLink())->countIssueCoverageForGame($oeuvreId, UserContext::currentUserId(), UserContext::currentFoyerId())
    : 0;

$mergeMessage = '';
$mergeError = '';
if (isset($_GET['merge_ok']) && (string) $_GET['merge_ok'] === '1') {
    $removedId = (int) ($_GET['merge_removed'] ?? 0);
    $mergeMessage = $removedId > 0
        ? 'Fusion réussie : la fiche n°' . $removedId . ' a été intégrée dans celle-ci.'
        : 'Fusion réussie.';
}
if (isset($_GET['merge_error'])) {
    $mergeError = trim((string) $_GET['merge_error']);
}

$gameCompletions = [];
$completionCount = 0;
if ($libraryBibId !== null && $libraryBibId > 0) {
    $userId = UserContext::currentUserId();
    $foyerId = UserContext::currentFoyerId();
    $libraryGame = $repo->findByBibId($libraryBibId, $userId, $foyerId);
    if ($libraryGame !== null) {
        foreach (['steam_playtime_minutes', 'steam_playtime_label', 'steam_never_played', 'steam_last_played_unix', 'tested_on_linux', 'linux_not_supported', 'linux_badge'] as $key) {
            if (array_key_exists($key, $libraryGame)) {
                $game[$key] = $libraryGame[$key];
            }
        }
        $game['edition_icon_keys'] = $libraryGame['edition_icon_keys'] ?? $game['edition_icon_keys'] ?? [];
    }
    if (GameCompletionRepository::isAvailable()) {
        $completionRepo = new GameCompletionRepository();
        $gameCompletions = $completionRepo->listForGame($libraryBibId, $userId);
        $completionCount = count($gameCompletions);
    }
}

View::render('oeuvre-jeu', [
    'pageTitle' => (string) ($game['display_titre'] ?? $game['titre'] ?? 'Jeu catalogue'),
    'catalogListContext' => $catalogListContext,
    'oeuvreNav' => $oeuvreNav,
    'game' => $game,
    'baseGame' => $baseGame,
    'catalogExtensions' => $catalogExtensions,
    'originalGame' => $originalGame,
    'catalogRemakes' => $catalogRemakes,
    'franchiseGames' => $franchiseGames,
    'library' => $library,
    'libraryBibId' => $libraryBibId,
    'libraryCount' => (int) $detail['library_count'],
    'catalogueBackUrl' => $catalogueBackUrl,
    'catalogSearch' => $catalogSearch,
    'catalogSort' => $catalogSort,
    'catalogDir' => $catalogDir,
    'catalogPage' => $catalogPage,
    'oeuvreId' => $oeuvreId,
    'saved' => isset($_GET['saved']) && (string) $_GET['saved'] === '1',
    'saveError' => $saveError,
    'posterUploadError' => $posterUploadError,
    'posterUploaded' => isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1',
    'editOpen' => $editOpen,
    'posterUploadOpen' => $posterUploadOpen,
    'enrichStatus' => $enrichStatus,
    'enrichMessage' => $enrichMessage,
    'platformChoices' => GameRepository::isAvailable() ? GamePlatform::choices() : [],
    'knownGenres' => GameRepository::isAvailable() ? $repo->listKnownGenres() : [],
    'knownSagas' => GameFranchiseRepository::isAvailable()
        ? (new GameFranchiseRepository())->listKnownSagas()
        : [],
    'magazineIssueCount' => $magazineIssueCount,
    'gameCompletions' => $gameCompletions,
    'completionCount' => $completionCount,
    'mergeMessage' => $mergeMessage,
    'mergeError' => $mergeError,
    'storeLinksSaved' => isset($_GET['store_links_saved']) && (string) $_GET['store_links_saved'] === '1',
    'storeLinksError' => trim((string) ($_GET['store_links_error'] ?? '')),
]);
