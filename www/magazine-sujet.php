<?php
/**
 * Fiche d’un sujet magazine : combien de numéros, dans quelles séries.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazineGameLink;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$subjectId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineSubjectRepository();

if (!MagazineSubjectRepository::isAvailable()) {
    View::render('magazine-sujet', [
        'pageTitle' => 'Sujet introuvable',
        'subject' => null,
        'stats' => ['issue_count' => 0, 'series_count' => 0],
        'issues' => [],
        'moduleError' => 'Module sujets non disponible.',
        'page' => 1,
        'totalPages' => 1,
        'listTotal' => 0,
    ]);
    exit;
}

$subject = $repo->findById($subjectId);
if ($subject === null) {
    http_response_code(404);
    View::render('magazine-sujet', [
        'pageTitle' => 'Sujet introuvable',
        'subject' => null,
        'stats' => ['issue_count' => 0, 'series_count' => 0],
        'issues' => [],
        'moduleError' => '',
        'page' => 1,
        'totalPages' => 1,
        'listTotal' => 0,
    ]);
    exit;
}

$perPage = MagazineSubjectRepository::ISSUES_PER_PAGE;
$listTotal = $repo->countIssuesInLibrary($subjectId, $userId, $foyerId);
$totalPages = max(1, (int) ceil($listTotal / $perPage));
$page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;

$issues = $repo->listIssuesInLibrary($subjectId, $userId, $foyerId, null, $perPage, $offset);
$stats = $repo->countInLibrary($subjectId, $userId, $foyerId);

if (MagazineGameLink::isAvailable()) {
    $subject = (new MagazineGameLink())->enrichSubjectRow($subject, $userId, $foyerId);
}

View::render('magazine-sujet', [
    'pageTitle' => (string) ($subject['display_label'] ?? 'Sujet'),
    'subject' => $subject,
    'stats' => $stats,
    'issues' => $issues,
    'moduleError' => '',
    'page' => $page,
    'totalPages' => $totalPages,
    'listTotal' => $listTotal,
    'wideLayout' => true,
]);
