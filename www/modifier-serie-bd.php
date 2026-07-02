<?php
/**
 * Formulaire : modifier une série BD / manga.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdKind;
use Moncine\BdRepository;
use Moncine\BdSeriesMetadata;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SeriesPoster;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext('/bd.php');

$seriesId = (int) ($_GET['series_id'] ?? 0);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
if ($series === null) {
    header('Location: /bd.php');
    exit;
}

$series = SeriesPoster::enrichSeries($series);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new BdRepository();
$libraryStatut = LibraryStatut::COLLECTION;
$seriesInLibrary = BdRepository::isAvailable()
    && $repo->isSeriesInLibrary($seriesId, $libraryStatut, $userId, $foyerId);
$tomeCount = $seriesInLibrary
    ? $repo->countTomesForSeries($seriesId, $userId, $foyerId, $libraryStatut)
    : 0;

View::render('modifier-serie-bd', [
    'pageTitle' => 'Modifier — ' . (string) ($series['titre'] ?? ''),
    'kindChoices' => BdKind::choices(),
    'series' => $series,
    'kind' => BdSeriesMetadata::kindFromSeries($series),
    'error' => (string) ($_GET['error'] ?? ''),
    'saved' => isset($_GET['saved']),
    'seriesInLibrary' => $seriesInLibrary,
    'libraryStatut' => $libraryStatut,
    'tomeCount' => $tomeCount,
]);
