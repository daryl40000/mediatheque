<?php
/**
 * API JSON — autocomplétion séries magazines du catalogue partagé.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazineRepository;
use Moncine\UserContext;
use Moncine\View;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!MagazineRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$series = (new MagazineRepository())->searchCatalogSeries($query, $userId, $foyerId, 25);

$results = [];
foreach ($series as $row) {
    $seriesId = (int) ($row['id'] ?? 0);
    if ($seriesId <= 0) {
        continue;
    }

    $issueCount = (int) ($row['catalog_issue_count'] ?? 0);
    $inCollection = !empty($row['in_collection']);
    $titre = (string) ($row['titre'] ?? '');

    $results[] = [
        'series_id' => $seriesId,
        'titre' => $titre,
        'display_label' => $titre . ($issueCount > 0 ? ' — ' . $issueCount . ' numéro(s) catalogue' : ''),
        'catalog_issue_count' => $issueCount,
        'in_collection' => $inCollection,
        'series_url' => $inCollection ? View::magazineSeriesUrl($seriesId) : '',
        'source' => 'magazine_series_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
