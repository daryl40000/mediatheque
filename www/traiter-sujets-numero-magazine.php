<?php
/**
 * Ajoute ou retire un sujet sur un numéro magazine (ou un supplément).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineIssueSupplementRepository;
use Moncine\MagazineRepository;
use Moncine\MagazineGameLink;
use Moncine\MagazineRatingPeriod;
use Moncine\MagazineRatingScale;
use Moncine\MagazineSeriesCategory;
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
$supplementId = max(0, (int) ($_POST['supplement_id'] ?? 0));
$returnUrl = $supplementId > 0
    ? View::magazineSupplementUrl($bibId, $supplementId)
    : View::magazineIssueUrl($bibId);
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

if ($supplementId > 0) {
    if (!MagazineSubjectRepository::hasSupplementSubjectTable()) {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('Sujets sur supplément non disponibles (migration 072).'));
        exit;
    }
    $supplement = MagazineIssueSupplementRepository::isAvailable()
        ? (new MagazineIssueSupplementRepository())->findById($supplementId, $oeuvreId)
        : null;
    if ($supplement === null) {
        header('Location: ' . View::magazineIssueUrl($bibId) . '&subject_error=' . rawurlencode('Supplément introuvable.'));
        exit;
    }
}

$action = (string) ($_POST['action'] ?? 'attach');

if ($action === 'detach') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    if ($supplementId > 0) {
        $subjectRepo->detachFromSupplement($supplementId, $subjectId);
    } else {
        $subjectRepo->detachFromOeuvre($oeuvreId, $subjectId);
    }
    header('Location: ' . $returnUrl . '&subject_detached=1');
    exit;
}

// Mise à jour page et/ou note en une seule validation (formulaire combiné).
if ($action === 'update_meta' || $action === 'update_page' || $action === 'update_score') {
    $subjectId = (int) ($_POST['subject_id'] ?? 0);
    $subject = $subjectRepo->findById($subjectId);
    if ($subject === null) {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('Sujet introuvable.'));
        exit;
    }

    $category = MagazineSubject::normalizeCategory((string) ($subject['category'] ?? ''));
    $seriesId = (int) ($issue['series_id'] ?? 0);
    $seriesRow = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) ?? [];
    $ratingScale = MagazineRatingPeriod::resolve(
        MagazineRatingScale::normalize($seriesRow['rating_scale'] ?? null),
        MagazineRatingPeriod::listForSeries($seriesId),
        (float) ($issue['numero_ordre'] ?? 0)
    );

    $touchesPage = $action === 'update_meta' || $action === 'update_page' || array_key_exists('page', $_POST);
    $touchesScore = $action === 'update_meta' || $action === 'update_score' || array_key_exists('score', $_POST);

    if ($touchesPage) {
        $page = MagazineSubjectRepository::normalizePage($_POST['page'] ?? 0);
        $pageResult = $supplementId > 0
            ? $subjectRepo->updateSupplementLinkPage($supplementId, $subjectId, $page)
            : $subjectRepo->updateLinkPage($oeuvreId, $subjectId, $page);
        if ($pageResult !== true) {
            header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $pageResult));
            exit;
        }
    }

    if ($touchesScore && $category === MagazineSubject::TEST) {
        if ($ratingScale === null) {
            // Pas d’échelle sur la série : on ignore le champ note.
        } else {
            $parsed = MagazineRatingScale::parseScore($_POST['score'] ?? '', $ratingScale);
            if (is_string($parsed)) {
                header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode($parsed));
                exit;
            }
            $scoreResult = $supplementId > 0
                ? $subjectRepo->updateSupplementLinkScore($supplementId, $subjectId, $parsed)
                : $subjectRepo->updateLinkScore($oeuvreId, $subjectId, $parsed);
            if ($scoreResult !== true) {
                header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $scoreResult));
                exit;
            }
        }
    } elseif ($touchesScore && $category !== MagazineSubject::TEST && $action === 'update_score') {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode('La note est réservée aux tests.'));
        exit;
    }

    $savedScore = $touchesScore && $category === MagazineSubject::TEST && $ratingScale !== null;
    $flash = ($touchesPage && $savedScore)
        ? 'subject_meta=1'
        : ($savedScore ? 'subject_score=1' : 'subject_page=1');
    header('Location: ' . $returnUrl . '&' . $flash);
    exit;
}

$seriesId = (int) ($issue['series_id'] ?? 0);
$series = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) ?? [
    'tags' => (string) ($issue['series_tags'] ?? ''),
    'categories' => (string) ($issue['series_categories'] ?? ''),
];
if (!isset($series['categories'])) {
    $series['categories'] = (string) ($issue['series_categories'] ?? '');
}

$category = (string) ($_POST['category'] ?? '');
$normalizedCategory = MagazineSubject::normalizeCategory($category);
if (
    MagazineSubject::isJeuxOfferts($normalizedCategory)
    && !MagazineSeriesCategory::includesJeuxVideo($series)
) {
    header(
        'Location: ' . $returnUrl . '&subject_error='
        . rawurlencode('La catégorie « Jeux offerts » est réservée aux séries Jeux vidéo.')
    );
    exit;
}

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

$page = MagazineSubjectRepository::normalizePage($_POST['page'] ?? 0);
$subjectId = (int) ($subject['id'] ?? 0);
$result = $supplementId > 0
    ? $subjectRepo->attachToSupplement($supplementId, $subjectId, $page)
    : $subjectRepo->attachToOeuvre($oeuvreId, $subjectId, $page);
if ($result !== true) {
    header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $result));
    exit;
}

// Note saisie dès la création (tests uniquement, si ce numéro a une échelle).
$attachCategory = MagazineSubject::normalizeCategory((string) ($prepared['category'] ?? ''));
if ($attachCategory === MagazineSubject::TEST && array_key_exists('score', $_POST)) {
    $seriesRowForScore = (new SeriesRepository())->findById($seriesId, MediaDomain::MAGAZINE) ?? $series;
    $ratingScale = MagazineRatingPeriod::resolve(
        MagazineRatingScale::normalize($seriesRowForScore['rating_scale'] ?? null),
        MagazineRatingPeriod::listForSeries($seriesId),
        (float) ($issue['numero_ordre'] ?? 0)
    );
    if ($ratingScale !== null) {
        $parsedScore = MagazineRatingScale::parseScore($_POST['score'] ?? '', $ratingScale);
        if (is_string($parsedScore)) {
            header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode($parsedScore));
            exit;
        }
        if ($parsedScore !== null) {
            $scoreResult = $supplementId > 0
                ? $subjectRepo->updateSupplementLinkScore($supplementId, $subjectId, $parsedScore)
                : $subjectRepo->updateLinkScore($oeuvreId, $subjectId, $parsedScore);
            if ($scoreResult !== true) {
                header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $scoreResult));
                exit;
            }
        }
    }
}

if ($subjectId > 0 && $catalogOeuvreId > 0 && MagazineGameLink::isAvailable()) {
    $linkResult = (new MagazineGameLink())->setSubjectCatalogLink($subjectId, $catalogOeuvreId);
    if ($linkResult !== true) {
        header('Location: ' . $returnUrl . '&subject_error=' . rawurlencode((string) $linkResult));
        exit;
    }
}

header('Location: ' . $returnUrl . '&subject=1');
exit;
