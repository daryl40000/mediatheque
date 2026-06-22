<?php
/**
 * API JSON — autocomplétion numéros magazines du catalogue (par série).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineRepository;
use Moncine\PublicationType;
use Moncine\UserContext;
use Moncine\View;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!MagazineRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$seriesId = max(0, (int) ($_GET['series_id'] ?? 0));
$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();

if ($seriesId <= 0) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$issues = (new MagazineRepository())->searchCatalogIssues($seriesId, $query, $userId, $foyerId, 25);

$results = [];
foreach ($issues as $row) {
    $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
    if ($oeuvreId <= 0) {
        continue;
    }

    $numero = (string) ($row['numero'] ?? '');
    $dateRaw = trim((string) ($row['date_parution'] ?? ''));
    $pubType = (string) ($row['publication_type'] ?? '');
    $dateLabel = $dateRaw !== ''
        ? PublicationType::formatParutionDate($dateRaw, $pubType)
        : '';

    $parts = ['n°' . $numero];
    if ($dateLabel !== '' && $dateLabel !== '—') {
        $parts[] = $dateLabel;
    }
    if (!empty($row['est_hors_serie'])) {
        $parts[] = 'HS';
    }

    $bibId = (int) ($row['library_bib_id'] ?? 0);
    $inLibrary = !empty($row['in_library']);

    $results[] = [
        'oeuvre_id' => $oeuvreId,
        'numero' => $numero,
        'numero_ordre' => (float) ($row['numero_ordre'] ?? 0),
        'date_parution' => $dateRaw,
        'date_label' => $dateLabel,
        'est_hors_serie' => !empty($row['est_hors_serie']),
        'display_label' => implode(' · ', $parts),
        'in_library' => $inLibrary,
        'library_bib_id' => $bibId,
        'library_url' => $inLibrary && $bibId > 0 ? View::magazineIssueUrl($bibId) : '',
        'source' => 'magazine_issue_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
