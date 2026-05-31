<?php
/**
 * Numéros d’une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$seriesId = (int) ($_GET['series_id'] ?? 0);
$sortBy = (string) ($_GET['sort'] ?? 'numero_ordre');
$sortDir = (string) ($_GET['dir'] ?? 'desc');
$statut = LibraryStatut::normalize((string) ($_GET['statut'] ?? LibraryStatut::COLLECTION));

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$series = (new SeriesRepository())->findById($seriesId, \Moncine\MediaDomain::MAGAZINE);
if ($series === null) {
    View::render('serie-magazine', [
        'pageTitle' => 'Série introuvable',
        'series' => null,
        'issues' => [],
        'statut' => $statut,
    ]);
    http_response_code(404);
    exit;
}

$repo = new MagazineRepository();
if (MagazineRepository::isAvailable()) {
    $repo->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
}
$issues = $repo->listIssuesForSeries($seriesId, $userId, $foyerId, $statut, $sortBy, $sortDir);
$suggestNumero = PublicationType::suggestNextNumeroOrdre($repo->maxNumeroOrdreForSeries($seriesId));

View::render('serie-magazine', [
    'pageTitle' => (string) ($series['titre'] ?? 'Série'),
    'series' => $series,
    'issues' => $issues,
    'statut' => $statut,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'suggestNumeroOrdre' => $suggestNumero,
    'publicationTypeLabel' => PublicationType::label((string) ($series['publication_type'] ?? '')),
]);
