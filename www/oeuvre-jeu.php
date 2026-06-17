<?php
/**
 * Fiche catalogue — jeu vidéo (administration).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\GamePlatform;
use Moncine\GameRepository;
use Moncine\MediaDomain;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

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
if (GameRepository::hasExtensionColumns()) {
    if (!empty($game['is_extension']) && (int) ($game['base_game_oeuvre_id'] ?? 0) > 0) {
        $baseGame = $repo->findCatalogByOeuvreId((int) $game['base_game_oeuvre_id']);
        if ($baseGame !== null) {
            $userId = UserContext::currentUserId();
            $foyerId = UserContext::currentFoyerId();
            $baseBibId = $repo->findLibraryBibIdForCatalogOeuvre((int) $baseGame['oeuvre_id'], $userId, $foyerId);
            $baseGame['library_bib_id'] = $baseBibId ?? 0;
            $baseGame['library_url'] = $baseBibId !== null && $baseBibId > 0
                ? View::gameUrl($baseBibId)
                : View::oeuvreJeuUrl((int) $baseGame['oeuvre_id'], $catalogSearch, $catalogSort, $catalogDir, $catalogPage);
        }
        $game['base_game_label'] = trim((string) ($baseGame['titre'] ?? ''));
    } elseif ((int) ($game['oeuvre_id'] ?? 0) > 0) {
        $catalogExtensions = $repo->listCatalogExtensionsForBaseGame((int) $game['oeuvre_id']);
    }
}

$saveError = (string) ($_GET['save_error'] ?? '');
$posterUploadError = (string) ($_GET['poster_error'] ?? '');
$editOpen = isset($_GET['edit']) || $saveError !== '';
$posterUploadOpen = $posterUploadError !== '';

if (isset($_GET['poster_uploaded']) && (string) $_GET['poster_uploaded'] === '1') {
    $refreshed = $repo->findCatalogByOeuvreId($oeuvreId);
    if ($refreshed !== null) {
        $game = $refreshed;
    }
}

$oeuvreNav = $admin->getOeuvreNavigation($oeuvreId, $catalogSearch, $catalogSort, $catalogDir);
$library = $detail['library'];
$libraryBibId = null;
if ($library !== null) {
    $libraryBibId = $repo->findLibraryBibIdForCatalogOeuvre(
        $oeuvreId,
        UserContext::currentUserId(),
        UserContext::currentFoyerId()
    );
}

View::render('oeuvre-jeu', [
    'pageTitle' => (string) ($game['titre'] ?? 'Jeu catalogue'),
    'catalogListContext' => $catalogListContext,
    'oeuvreNav' => $oeuvreNav,
    'game' => $game,
    'baseGame' => $baseGame,
    'catalogExtensions' => $catalogExtensions,
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
    'platformChoices' => GameRepository::isAvailable() ? GamePlatform::choices() : [],
    'knownGenres' => GameRepository::isAvailable() ? $repo->listKnownGenres() : [],
]);
