<?php
/**
 * Numéros d’une série magazine.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\LibraryStatut;
use Moncine\MagazinePdfTextExtractor;
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
$searchQuery = trim((string) ($_GET['q'] ?? ''));
// Compatibilité : anciens liens avec q_numero / q_date / q_mot
if ($searchQuery === '') {
    $legacyParts = array_filter([
        trim((string) ($_GET['q_numero'] ?? '')),
        trim((string) ($_GET['q_date'] ?? '')),
        trim((string) ($_GET['q_mot'] ?? '')),
    ], static fn (string $part): bool => $part !== '');
    $searchQuery = implode(' ', $legacyParts);
}
$hasSearch = $searchQuery !== '';
$possessionFilter = MagazineRepository::normalizePossessionFilter((string) ($_GET['possession'] ?? 'all'));

$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

$series = (new SeriesRepository())->findById($seriesId, \Moncine\MediaDomain::MAGAZINE);
if ($series === null) {
    View::render('serie-magazine', [
        'pageTitle' => 'Série introuvable',
        'series' => null,
        'issues' => [],
        'statut' => $statut,
        'searchQuery' => '',
        'hasSearch' => false,
        'totalAllIssues' => 0,
        'filteredCount' => 0,
        'pdfTextSearchEnabled' => false,
    ]);
    http_response_code(404);
    exit;
}

$repo = new MagazineRepository();
if (MagazineRepository::isAvailable()) {
    $repo->registerSeriesInLibrary($seriesId, $statut, $userId, $foyerId);
}

$reindexMessage = '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'reindex_pdf_text'
    && MagazineRepository::pdfTextPreviewColumnExists()
) {
    $redirectBase = View::magazineSeriesUrl($seriesId, 'numero_ordre', 'desc', array_filter([
        'statut' => $statut,
        'possession' => $possessionFilter !== MagazineRepository::POSSESSION_ALL ? $possessionFilter : null,
    ]));
    \Moncine\Csrf::rejectUnlessValid($_POST, $redirectBase);
    $stats = $repo->reindexPdfTextPreviewsForSeries($seriesId, $userId, $foyerId, $statut);
    $msg = sprintf(
        '%d indexé(s), %d sans PDF, %d erreur(s)',
        $stats['indexed'],
        $stats['skipped'],
        $stats['errors']
    );
    header('Location: ' . $redirectBase . '&reindex=' . rawurlencode($msg));
    exit;
}

if (isset($_GET['reindex']) && is_string($_GET['reindex'])) {
    $reindexMessage = 'Indexation terminée : ' . trim($_GET['reindex']) . '.';
}

$perPage = MagazineRepository::ISSUES_PER_PAGE;
$listTotal = $repo->countIssuesForSeries(
    $seriesId,
    $userId,
    $foyerId,
    $statut,
    $searchQuery,
    $possessionFilter
);
$totalPages = max(1, (int) ceil($listTotal / $perPage));
$page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;

$issues = $repo->listIssuesForSeries(
    $seriesId,
    $userId,
    $foyerId,
    $statut,
    $sortBy,
    $sortDir,
    $searchQuery,
    $possessionFilter,
    $perPage,
    $offset
);
$totalAllIssues = $repo->countIssuesForSeries($seriesId, $userId, $foyerId, $statut, $searchQuery);
$totalWithPossessionFilter = $possessionFilter !== MagazineRepository::POSSESSION_ALL
    ? $listTotal
    : $totalAllIssues;
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
    'searchQuery' => $searchQuery,
    'hasSearch' => $hasSearch,
    'totalAllIssues' => $totalAllIssues,
    'filteredCount' => $listTotal,
    'pdfTextSearchEnabled' => MagazineRepository::pdfTextPreviewColumnExists(),
    'pdftotextAvailable' => MagazinePdfTextExtractor::isAvailable(),
    'reindexMessage' => $reindexMessage,
    'possessionFilter' => $possessionFilter,
    'totalWithPossessionFilter' => $totalWithPossessionFilter,
    'page' => $page,
    'totalPages' => $totalPages,
    'perPage' => $perPage,
    'listTotal' => $listTotal,
]);
