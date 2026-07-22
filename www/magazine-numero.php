<?php
/**
 * Fiche d’un numéro de magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineGameLink;
use Moncine\MagazineSubjectCatalogLink;
use Moncine\MagazineRepository;
use Moncine\MagazineSeriesTag;
use Moncine\MagazineSubject;
use Moncine\MagazineSubjectRepository;
use Moncine\MediaDomainGuards;
use Moncine\PublicationType;
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
if ($error !== '') {
    $popoverOpen = isset($_GET['pdf']) ? 'pdf' : 'edit';
} elseif (isset($_GET['popover']) && in_array((string) $_GET['popover'], $allowedPopovers, true)) {
    $popoverOpen = (string) $_GET['popover'];
}
$subjectSaved = isset($_GET['subject']);
$subjectDetached = isset($_GET['subject_detached']);
$subjectError = (string) ($_GET['subject_error'] ?? '');

$oeuvreId = (int) ($issue['oeuvre_id'] ?? 0);
$seriesForSubjects = [
    'id' => (int) ($issue['series_id'] ?? 0),
    'tags' => (string) ($issue['series_tags'] ?? ''),
];
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

View::render('magazine-numero', [
    'pageTitle' => (string) ($issue['titre'] ?? 'Numéro'),
    'issue' => $issue,
    'saved' => $saved,
    'error' => $error,
    'popoverOpen' => $popoverOpen,
    'subjectSaved' => $subjectSaved,
    'subjectDetached' => $subjectDetached,
    'subjectError' => $subjectError,
    'issueSubjects' => $issueSubjects,
    'offeredSubjects' => $offeredSubjects,
    'subjectCategories' => $subjectCategories,
    'subjectsAvailable' => MagazineSubjectRepository::isAvailable(),
    'seriesTags' => $seriesTags,
    'forcedTag' => $forcedTag,
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
]);
