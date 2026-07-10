<?php
/**
 * Ajoute ou retire un sujet sur un numéro magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineRepository;
use Moncine\MagazineGameLink;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectCatalogLink;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /magazines-recherche.php');
    exit;
}

MediaDomainGuards::ensureMagazineContext();

$bibId = (int) ($_POST['bib_id'] ?? 0);
$returnUrl = View::magazineIssueUrl($bibId);
\Moncine\Csrf::rejectUnlessValid($_POST, $returnUrl);

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$magRepo = new MagazineRepository();
$subjectRepo = new MagazineSubjectRepository();

if (!MagazineSubjectRepository::isAvailable()) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('Module sujets non disponible.'));
    exit;
}

$issue = $bibId > 0 ? $magRepo->findIssueByBibId($bibId, $userId, $foyerId) : null;
if ($issue === null) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('Numéro introuvable.'));
    exit;
}

$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'attach');

if ($action === 'detach') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $subjectRepo->detachFromOeuvre($oeuvreId, $subjectId);
    header('Location: ' . $returnUrl . '&subject_detached=1');
    exit;
}

$seriesId = (int) ($issue['series_id'] ?? 0);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) ?? [
    'tags' => (string) ($issue['series_tags'] ?? ''),
];

$category = (string) ($_POST['category'] ?? '');
$label = trim((string) ($_POST['label'] ?? ''));
$userDetail = trim((string) ($_POST['detail'] ?? ''));
$parutionYear = (int) ($_POST['parution_year'] ?? 0);
$catalogMediaDomain = MediaDomain::normalize((string) ($_POST['catalog_media_domain'] ?? ''));
$catalogOeuvreId = max(0, (int) ($_POST['catalog_oeuvre_id'] ?? 0));
$catalogLink = new MagazineSubjectCatalogLink();

if (
    $catalogOeuvreId <= 0
    && $catalogMediaDomain !== ''
    && MagazineSubject::supportsCatalogGameLink($category)
    && MagazineSubjectCatalogLink::isAvailable()
) {
    $resolved = $catalogLink->findOrCreateCatalogOeuvre($catalogMediaDomain, $label, $parutionYear);
    if (!is_int($resolved)) {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode($resolved));
        exit;
    }
    $catalogOeuvreId = $resolved;
}

$prepared = $subjectRepo->prepareSubjectForIssue(
    $category,
    $label,
    $userDetail,
    $series,
    $issue,
    $parutionYear
);

if ($catalogOeuvreId > 0 && MagazineSubjectCatalogLink::isAvailable()) {
    $prepared = $subjectRepo->prepareSubjectForIssueWithCatalog(
        $category,
        $label,
        $userDetail,
        $series,
        $issue,
        $parutionYear,
        $catalogOeuvreId,
        $catalogMediaDomain
    );
}

if (!is_array($prepared)) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode($prepared));
    exit;
}

$subject = $subjectRepo->findOrCreate(
    (string) $prepared['category'],
    (string) $prepared['label'],
    (string) $prepared['detail'],
    (int) $prepared['parution_year']
);
if ($subject === null) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('Impossible de créer le sujet.'));
    exit;
}

$result = $subjectRepo->attachToOeuvre($oeuvreId, (int) ($subject['id'] ?? 0));
if ($result !== true) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $result));
    exit;
}

$subjectId = (int) ($subject['id'] ?? 0);
if ($subjectId > 0 && $catalogOeuvreId > 0 && MagazineGameLink::isAvailable()) {
    $linkResult = (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogOeuvreId);
    if ($linkResult !== true) {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $linkResult));
        exit;
    }
}

header('Location: ' . $returnUrl . '&subject=1');
exit;
