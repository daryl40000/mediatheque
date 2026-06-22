<?php
/**
 * Mes magazines — liste des séries (PC Jeux, Joystick…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\LibraryStatut;
use Moncine\MagazineRepository;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext('/magazines.php');

if (!MagazineRepository::isAvailable()) {
    View::render('magazines', [
        'pageTitle' => \Moncine\MediaContext::navLabels()['collection'],
        'seriesList' => [],
        'totalCount' => 0,
        'query' => '',
        'sortBy' => 'titre',
        'sortDir' => 'asc',
        'contentSubjects' => [],
        'contentIssues' => [],
        'moduleError' => 'Le module magazines n’est pas encore disponible. Rechargez la page dans quelques secondes.',
    ]);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$sortBy = (string) ($_GET['sort'] ?? 'titre');
$sortDir = (string) ($_GET['dir'] ?? 'asc');

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();
$subjectRepo = new MagazineSubjectRepository();

$seriesList = $repo->listSeriesInLibrary(
    $userId,
    $foyerId,
    LibraryStatut::COLLECTION,
    $sortBy,
    $sortDir,
    $query
);

$contentSubjects = [];
$contentIssues = [];
if ($query !== '' && MagazineSubjectRepository::isAvailable()) {
    $contentSubjects = $subjectRepo->searchCatalog($query, null, 20);
    foreach ($contentSubjects as $i => $subject) {
        $counts = $subjectRepo->countInLibrary((int) ($subject['id'] ?? 0), $userId, $foyerId);
        $contentSubjects[$i]['library_issue_count'] = $counts['issue_count'];
        $contentSubjects[$i]['library_series_count'] = $counts['series_count'];
    }
    $contentIssues = $repo->searchIssuesInLibrary(
        $userId,
        $foyerId,
        LibraryStatut::COLLECTION,
        $query,
        24
    );
}

View::render('magazines', [
    'pageTitle' => \Moncine\MediaContext::navLabels()['collection'],
    'seriesList' => $seriesList,
    'totalCount' => count($seriesList),
    'query' => $query,
    'sortBy' => $sortBy,
    'sortDir' => $sortDir,
    'contentSubjects' => $contentSubjects,
    'contentIssues' => $contentIssues,
    'moduleError' => '',
    'canManageCatalog' => CatalogAdmin::canAccess(),
]);
