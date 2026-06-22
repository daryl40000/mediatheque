<?php
/**
 * Formulaire : ajouter un numéro à une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$seriesId = (int) ($_GET['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE);
if ($series === null) {
    header('Location: /magazines.php');
    exit;
}

$repo = new MagazineRepository();
$suggestOrdre = PublicationType::suggestNextNumeroOrdre($repo->maxNumeroOrdreForSeries($seriesId));

$catalogIssue = null;
$catalogOeuvreId = max(0, (int) ($_GET['oeuvre_id'] ?? 0));
if ($catalogOeuvreId > 0) {
    $candidate = $repo->findCatalogIssueByOeuvreId($catalogOeuvreId);
    if ($candidate !== null && (int) ($candidate['series_id'] ?? 0) === $seriesId) {
        $catalogIssue = $candidate;
    }
}

View::render('ajouter-numero-magazine', [
    'pageTitle' => 'Ajouter un numéro — ' . (string) ($series['titre'] ?? ''),
    'series' => $series,
    'statut' => $statut,
    'suggestNumeroOrdre' => $suggestOrdre,
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
    'error' => (string) ($_GET['error'] ?? ''),
    'catalogIssue' => $catalogIssue,
    'catalogOeuvreId' => $catalogIssue !== null ? $catalogOeuvreId : 0,
]);
