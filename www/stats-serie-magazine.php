<?php
/**
 * Statistiques d’évolution d’une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineSeriesStats;
use Moncine\MagazineSubject;
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

$statsService = MagazineSeriesStats::isAvailable() ? new MagazineSeriesStats() : null;
$stats = $statsService !== null ? $statsService->getDashboard($seriesId) : null;

// Filtre « afficher les sujets d’une année » (catégorie + année de parution du numéro).
$filterCategory = MagazineSubject::normalizeCategory((string) ($_GET['category'] ?? ''));
$filterYear = (int) ($_GET['year'] ?? 0);
$subjectCategoryChoices = MagazineSubject::choicesForSeries((string) ($series['categories'] ?? ''));
if ($filterCategory !== '' && !isset($subjectCategoryChoices[$filterCategory])) {
    $filterCategory = '';
}
$availableYears = $statsService !== null ? $statsService->listParutionYears($seriesId) : [];
if ($filterYear > 0 && !in_array($filterYear, $availableYears, true)) {
    // Année demandée hors liste : on laisse quand même tenter (numéros sans année absents).
    if ($filterYear < 1900 || $filterYear > 2100) {
        $filterYear = 0;
    }
}

$filteredSubjects = [];
$filterActive = false;
if (
    $statsService !== null
    && $filterCategory !== ''
    && $filterYear >= 1900
    && $filterYear <= 2100
) {
    $filterActive = true;
    $filteredSubjects = $statsService->listSubjectsByCategoryAndYear(
        $seriesId,
        $filterCategory,
        $filterYear
    );
}

View::render('stats-serie-magazine', [
    'pageTitle' => 'Statistiques — ' . (string) ($series['titre'] ?? 'Série'),
    'series' => $series,
    'statut' => $statut,
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
    'stats' => $stats,
    'subjectCategoryChoices' => $subjectCategoryChoices,
    'availableYears' => $availableYears,
    'filterCategory' => $filterCategory,
    'filterYear' => $filterYear,
    'filterActive' => $filterActive,
    'filteredSubjects' => $filteredSubjects,
    'wideLayout' => true,
]);
