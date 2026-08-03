<?php
/**
 * Fiche d’un supplément magazine (livret, posters…) — sujets et lecture PDF.
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
$supplementId = (int) ($_GET['supplement_id'] ?? 0);
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$repo = new MagazineRepository();

$issue = $bibId > 0 ? $repo->findIssueByBibId($bibId, $userId, $foyerId) : null;
$supplement = null;
if (
    $issue !== null
    && $supplementId > 0
    && MagazineIssueSupplementRepository::isAvailable()
) {
    $supplement = (new MagazineIssueSupplementRepository())->findById(
        $supplementId,
        (int) ($issue['oeuvre_id'] ?? 0)
    );
}

if ($issue === null || $supplement === null) {
    http_response_code(404);
    View::render('magazine-supplement', [
        'pageTitle' => 'Supplément introuvable',
        'issue' => $issue,
        'supplement' => null,
        'error' => '',
    ]);
    exit;
}

$subjectSaved = isset($_GET['subject']);
$subjectDetached = isset($_GET['subject_detached']);
$subjectPageUpdated = isset($_GET['subject_page']);
$subjectScoreUpdated = isset($_GET['subject_score']);
$subjectError = (string) ($_GET['subject_error'] ?? '');

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

$issueSubjects = MagazineSubjectRepository::hasSupplementSubjectTable()
    ? (new MagazineSubjectRepository())->listForSupplement($supplementId)
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

View::render('magazine-supplement', [
    'pageTitle' => (string) ($supplement['display_label'] ?? 'Supplément'),
    'issue' => $issue,
    'supplement' => $supplement,
    'error' => (string) ($_GET['error'] ?? ''),
    'subjectSaved' => $subjectSaved,
    'subjectDetached' => $subjectDetached,
    'subjectPageUpdated' => $subjectPageUpdated,
    'subjectScoreUpdated' => $subjectScoreUpdated,
    'subjectError' => $subjectError,
    'issueSubjects' => $issueSubjects,
    'offeredSubjects' => $offeredSubjects,
    'subjectCategories' => $subjectCategories,
    'subjectsAvailable' => MagazineSubjectRepository::hasSupplementSubjectTable(),
    'seriesTags' => $seriesTags,
    'forcedTag' => $forcedTag,
    'ratingScale' => $ratingScale,
    'parutionYear' => $parutionYear,
    'defaultSubjectYear' => $defaultSubjectYear,
    'subjectYearChoices' => $subjectYearChoices,
    'catalogMediaLinkAvailable' => MagazineSubjectCatalogLink::isAvailable(),
    'catalogMediaDomainChoices' => MagazineSubjectCatalogLink::linkableMediaDomainChoices(),
    'dateLabel' => PublicationType::formatParutionDate(
        (string) ($issue['date_parution'] ?? ''),
        (string) ($issue['publication_type'] ?? '')
    ),
    'pdfUrl' => (string) ($supplement['pdf_url'] ?? ''),
    'subjectTargetSupplementId' => $supplementId,
]);
