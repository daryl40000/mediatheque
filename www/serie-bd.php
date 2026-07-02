<?php
/**
 * Tomes d’une série BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\BdSeriesMetadata;
use Moncine\CollectionViewMode;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext();

$seriesId = (int) ($_GET['series_id'] ?? 0);
$sortBy = (string) ($_GET['sort'] ?? 'tome');
$sortDir = (string) ($_GET['dir'] ?? 'asc');
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$viewMode = CollectionViewMode::normalizeBdSeries((string) ($_GET['view'] ?? ''));
$possessionFilter = BdRepository::normalizePossessionFilter((string) ($_GET['possession'] ?? ''));

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
if ($series === null) {
    View::render('serie-bd', [
        'pageTitle' => 'Série introuvable',
        'series' => null,
        'tomes' => [],
        'statut' => $statut,
        'sortBy' => $sortBy,
        'sortDir' => $sortDir,
        'searchQuery' => '',
        'viewMode' => '',
        'possessionFilter' => BdRepository::POSSESSION_ALL,
        'totalCount' => 0,
        'totalAllTomes' => 0,
        'possessedCount' => 0,
        'catalogTomeCount' => 0,
        'suggestTomeNumero' => 1,
        'kindLabel' => '',
    ]);
    http_response_code(404);
    exit;
}

$repo = new BdRepository();
if (BdRepository::isAvailable()) {
    $repo->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
}

$tomes = BdRepository::isAvailable()
    ? $repo->listTomesForSeries(
        $seriesId,
        $userId,
        $foyerId,
        $statut,
        $sortBy,
        $sortDir,
        $searchQuery,
        $possessionFilter !== BdRepository::POSSESSION_ALL ? $possessionFilter : null
    )
    : [];

$totalAllTomes = BdRepository::isAvailable()
    ? $repo->countTomesForSeries($seriesId, $userId, $foyerId, $statut, $searchQuery)
    : 0;

View::render('serie-bd', [
    'pageTitle' => (string) ($series['titre'] ?? 'Série'),
    'series' => $series,
    'tomes' => $tomes,
    'statut' => $statut,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'searchQuery' => $searchQuery,
    'viewMode' => $viewMode,
    'possessionFilter' => $possessionFilter,
    'totalCount' => count($tomes),
    'totalAllTomes' => $totalAllTomes,
    'possessedCount' => BdRepository::isAvailable()
        ? $repo->countPossessedTomesForSeries($seriesId, $userId, $foyerId, $statut)
        : 0,
    'catalogTomeCount' => BdRepository::isAvailable()
        ? $repo->countCatalogTomesForSeries($seriesId)
        : 0,
    'suggestTomeNumero' => BdRepository::suggestNextTomeNumero($repo->maxTomeNumeroForSeries($seriesId)),
    'suggestTomeOrdre' => BdRepository::suggestNextTomeOrdre($repo->maxTomeOrdreForSeries($seriesId)),
    'kindLabel' => BdSeriesMetadata::kindLabelFromSeries($series),
    'seriesInLibrary' => BdRepository::isAvailable()
        && $repo->isSeriesInLibrary($seriesId, $statut, $userId, $foyerId),
]);
