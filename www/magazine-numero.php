<?php
/**
 * Fiche d’un numéro de magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineGameLink;
use Moncine\MagazineIssueSupplementRepository;
use Moncine\MagazineSubjectCatalogLink;
use Moncine\MagazineRatingScale;
use Moncine\MagazineRepository;
use Moncine\MagazineSeriesTag;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomain;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
use Moncine\SeriesRepository;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureMagazineContext();

$bibId = (int) ($_GET['id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$issue = $bibId > 0 ? $repo->findIssueByBibId($bibId, $userId, $foyerId) : null;

if ($issue === null) {
    http_response_code(404);
    View::render('magazine-numero', [
        'pageTitle' => 'Numéro introuvable',
        'issue' => null,
        'saved' => false,
        'error' => '',
    ]);
    exit;
}

$saved = isset($_GET['saved']);
$error = (string) ($_GET['error'] ?? '');
$allowedPopovers = ['edit', 'pdf'];
$popoverOpen = '';
if (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
} elseif ($error !== '') {
    $popoverOpen = isset($_GET['pdf']) ? 'pdf' : 'edit';
} elseif (isset($_GET['supplement']) || isset($_GET['supplement_removed'])) {
    $popoverOpen = 'pdf';
}
$subjectSaved = isset($_GET['subject']);
$subjectDetached = isset($_GET['subject_detached']);
$subjectPageUpdated = isset($_GET['subject_page']);
$subjectScoreUpdated = isset($_GET['subject_score']);
$subjectMetaUpdated = isset($_GET['subject_meta']);
$subjectError = (string) ($_GET['subject_error'] ?? '');

$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
$seriesForSubjects = [
    'id' => (int) ($issue['series_id'] ?? 0),
    'tags' => (string) ($issue['series_tags'] ?? ''),
];
$seriesRow = (new SeriesRepository())->findById((int) ($issue['series_id'] ?? 0), MediaDomain::MAGAZINE);
$ratingScale = MagazineRatingScale::normalize($seriesRow['rating_scale'] ?? null);
$parutionYear = MagazineSubject::parutionYearFromIssue($issue);
$defaultSubjectYear = MagazineSubject::defaultSubjectYearFromIssue($issue);
$subjectYearChoices = MagazineSubject::subjectYearChoices($defaultSubjectYear);
$seriesTags = MagazineSeriesTag::listForSeries($seriesForSubjects);
$forcedTag = MagazineSeriesTag::singleTag($seriesForSubjects);
$issueSubjects = MagazineSubjectRepository::isAvailable()
    ? (new MagazineSubjectRepository())->listForOeuvre($oeuvreId)
    : [];
if (MagazineGameLink::isAvailable()) {
    $gameLink = new MagazineGameLink();
    foreach ($issueSubjects as $index => $issueSubject) {
        $issueSubjects[$index] = $gameLink->enrichSubjectRow($issueSubject, $userId, $foyerId);
    }
}
$subjectCategories = MagazineSubject::choicesForSeries(
    (string) ($issue['series_categories'] ?? '')
);
$partitionedSubjects = MagazineSubject::partitionSubjectsByOffer($issueSubjects);
$offeredSubjects = $partitionedSubjects['offered'];
$issueSubjects = $partitionedSubjects['regular'];
$catalogMediaLinkAvailable = MagazineSubjectCatalogLink::isAvailable();
$catalogMediaDomainChoices = MagazineSubjectCatalogLink::linkableMediaDomainChoices();

$supplements = MagazineIssueSupplementRepository::isAvailable()
    ? (new MagazineIssueSupplementRepository())->listForOeuvre($oeuvreId)
    : [];

View::render('magazine-numero', [
    'pageTitle' => (string) ($issue['titre'] ?? 'Numéro'),
    'issue' => $issue,
    'saved' => $saved,
    'error' => $error,
    'popoverOpen' => $popoverOpen,
    'subjectSaved' => $subjectSaved,
    'subjectDetached' => $subjectDetached,
    'subjectPageUpdated' => $subjectPageUpdated,
    'subjectScoreUpdated' => $subjectScoreUpdated,
    'subjectMetaUpdated' => $subjectMetaUpdated,
    'subjectError' => $subjectError,
    'issueSubjects' => $issueSubjects,
    'offeredSubjects' => $offeredSubjects,
    'subjectCategories' => $subjectCategories,
    'subjectsAvailable' => MagazineSubjectRepository::isAvailable(),
    'seriesTags' => $seriesTags,
    'forcedTag' => $forcedTag,
    'ratingScale' => $ratingScale,
    'parutionYear' => $parutionYear,
    'defaultSubjectYear' => $defaultSubjectYear,
    'subjectYearChoices' => $subjectYearChoices,
    'catalogMediaLinkAvailable' => $catalogMediaLinkAvailable,
    'catalogMediaDomainChoices' => $catalogMediaDomainChoices,
    'dateLabel' => PublicationType::formatParutionDate(
        (string) ($issue['date_parution'] ?? ''),
        (string) ($issue['publication_type'] ?? '')
    ),
    'pdfUrl' => (int) ($issue['stored_object_id'] ?? 0) > 0
        ? '/media-object.php?id=' . (int) $issue['stored_object_id']
        : '',
    'supplements' => $supplements,
]);
