<?php
/**
 * Statistiques d’évolution d’une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineSeriesStats;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SeriesPoster;
use Moncine\SeriesRepository;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$seriesId = (int) ($_GET['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
if ($series !== null) {
    $series = SeriesPoster::enrichSeries($series);
}

if ($series === null) {
    View::render('stats-serie-magazine', [
        'pageTitle' => 'Série introuvable',
        'series' => null,
        'statut' => $statut,
        'stats' => null,
        'wideLayout' => true,
    ]);
    http_response_code(404);
    exit;
}

$stats = MagazineSeriesStats::isAvailable()
    ? (new MagazineSeriesStats())->getDashboard($seriesId)
    : null;

View::render('stats-serie-magazine', [
    'pageTitle' => 'Statistiques — ' . (string) ($series['titre'] ?? 'Série'),
    'series' => $series,
    'statut' => $statut,
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
    'stats' => $stats,
    'wideLayout' => true,
]);
