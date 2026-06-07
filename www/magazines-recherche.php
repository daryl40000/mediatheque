<?php
/**
 * Recherche globale de numéros magazines par sujet (tests, previews…).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineSubjectRepository();

$categoryParam = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$category = $categoryParam !== ''
    ? MagazineSubject::normalizeCategory($categoryParam)
    : MagazineSubject::TEST;
$query = trim((string) ($_GET['q'] ?? ''));
$subjectId = (int) ($_GET['subject_id'] ?? 0);

if ($subjectId > 0) {
    header('Location: ' . View::magazineSubjectUrl($subjectId));
    exit;
}

$subjects = [];
$moduleError = '';
$formSubmitted = isset($_GET['category']) || $query !== '';
if (!MagazineSubjectRepository::isAvailable()) {
    $moduleError = 'Le module sujets magazines n’est pas encore disponible. Rechargez la page dans quelques secondes.';
} elseif ($formSubmitted) {
    $subjects = $repo->searchCatalog($query, $category, 50);
    foreach ($subjects as $i => $subject) {
        $counts = $repo->countInLibrary((int) ($subject['id'] ?? 0), $userId, $foyerId);
        $subjects[$i]['library_issue_count'] = $counts['issue_count'];
        $subjects[$i]['library_series_count'] = $counts['series_count'];
    }
}

View::render('magazines-recherche', [
    'pageTitle' => 'Recherche par sujet',
    'query' => $query,
    'category' => $category,
    'subjects' => $subjects,
    'subjectCategories' => MagazineSubject::choices(),
    'moduleError' => $moduleError,
    'wideLayout' => true,
]);
