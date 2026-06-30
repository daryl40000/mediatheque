<?php
/**
 * API JSON — autocomplétion séries BD du catalogue partagé.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdRepository;
use Moncine\UserContext;
use Moncine\View;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!BdRepository::isAvailable()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$series = (new BdRepository())->searchCatalogSeries($query, $userId, $foyerId, 25);

$results = [];
foreach ($series as $row) {
    $seriesId = (int) ($row['id'] ?? 0);
    if ($seriesId <= 0) {
        continue;
    }

    $tomeCount = (int) ($row['catalog_tome_count'] ?? 0);
    $inCollection = !empty($row['in_collection']);
    $titre = (string) ($row['titre'] ?? '');

    $results[] = [
        'series_id' => $seriesId,
        'titre' => $titre,
        'display_label' => $titre
            . ($tomeCount > 0 ? ' — ' . $tomeCount . ' tome(s) catalogue' : '')
            . ' (' . (string) ($row['kind_label'] ?? '') . ')',
        'catalog_tome_count' => $tomeCount,
        'in_collection' => $inCollection,
        'series_url' => $inCollection ? View::bdSeriesUrl($seriesId) : '',
        'source' => 'bd_series_catalog',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
