<?php
/**
 * API JSON — autocomplétion des sujets magazines.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\UserContext;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!MagazineSubjectRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$categoryRaw = trim((string) ($_GET['category'] ?? ''));
$category = $categoryRaw !== '' ? MagazineSubject::normalizeCategory($categoryRaw) : null;

$repo = new MagazineSubjectRepository();
$subjects = $repo->searchCatalog($query, $category, 25);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$results = [];
foreach ($subjects as $subject) {
    $subjectId = (int) ($subject['id'] ?? 0);
    $counts = $subjectId > 0
        ? $repo->countInLibrary($subjectId, $userId, $foyerId)
        : ['issue_count' => 0, 'series_count' => 0];

    $results[] = [
        'id' => $subjectId,
        'label' => (string) ($subject['label'] ?? ''),
        'detail' => (string) ($subject['detail'] ?? ''),
        'display_label' => (string) ($subject['display_label'] ?? ''),
        'category' => (string) ($subject['category'] ?? ''),
        'category_label' => (string) ($subject['category_label'] ?? ''),
        'parution_year' => (int) ($subject['parution_year'] ?? 0),
        'catalog_oeuvre_id' => (int) ($subject['catalog_oeuvre_id'] ?? 0),
        'issue_count' => (int) ($counts['issue_count'] ?? 0),
        'series_count' => (int) ($counts['series_count'] ?? 0),
        'url' => $subjectId > 0 ? \Moncine\View::magazineSubjectUrl($subjectId) : '',
        'source' => 'magazine_subject',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
