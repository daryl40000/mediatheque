<?php
/**
 * Formulaire : modifier une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\MagazineSeriesCategory;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines.php');

$seriesId = (int) ($_GET['series_id'] ?? 0);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
if ($series === null) {
    header('Location: /magazines.php');
    exit;
}

$series = \Moncine\SeriesPoster::enrichSeries($series);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();
$libraryStatut = LibraryStatut::COLLECTION;
$seriesInLibrary = MagazineRepository::isAvailable()
    && $repo->isSeriesInLibrary($seriesId, $libraryStatut, $userId, $foyerId);
$libraryIssueCount = $seriesInLibrary
    ? $repo->countIssuesForSeries($seriesId, $userId, $foyerId, $libraryStatut)
    : 0;

View::render('modifier-serie-magazine', [
    'pageTitle' => 'Modifier — ' . (string) ($series['titre'] ?? ''),
    'publicationTypes' => PublicationType::choices(),
    'series' => $series,
    'error' => (string) ($_GET['error'] ?? ''),
    'saved' => isset($_GET['saved']),
    'seriesInLibrary' => $seriesInLibrary,
    'libraryStatut' => $libraryStatut,
    'libraryIssueCount' => $libraryIssueCount,
    'knownCategories' => MagazineSeriesCategory::suggestionLabels(),
]);
