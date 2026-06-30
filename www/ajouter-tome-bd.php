<?php
/**
 * Formulaire : ajouter un tome à une série BD.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\BdSeriesMetadata;
use Moncine\LibraryStatut;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SeriesRepository;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureBdContext();

$seriesId = (int) ($_GET['series_id'] ?? 0);
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$series = (new SeriesRepository())->findById($seriesId, MediaDomain::BD);
if ($series === null) {
    header('Location: /bd.php');
    exit;
}

$repo = new BdRepository();
$suggestTome = BdRepository::suggestNextTomeNumero($repo->maxTomeNumeroForSeries($seriesId));

$prefillAlbum = null;
$prefillOeuvreId = max(0, (int) ($_GET['oeuvre_id'] ?? 0));
if ($prefillOeuvreId > 0 && BdRepository::isAvailable()) {
    $candidate = $repo->findCatalogByOeuvreId($prefillOeuvreId);
    if ($candidate !== null && (int) ($candidate['series_id'] ?? 0) === $seriesId) {
        $prefillAlbum = $candidate;
    }
}

View::render('ajouter-tome-bd', [
    'pageTitle' => 'Ajouter un tome — ' . (string) ($series['titre'] ?? ''),
    'series' => $series,
    'statut' => $statut,
    'suggestTomeNumero' => $suggestTome,
    'kindLabel' => BdSeriesMetadata::kindLabelFromSeries($series),
    'supportChoices' => \Moncine\BdPhysicalSupport::choices(),
    'knownGenres' => BdRepository::isAvailable() ? $repo->listKnownGenres() : [],
    'error' => (string) ($_GET['error'] ?? ''),
    'prefillAlbum' => $prefillAlbum,
    'prefillOeuvreId' => $prefillAlbum !== null ? $prefillOeuvreId : 0,
    'moduleAvailable' => BdRepository::isAvailable(),
]);
