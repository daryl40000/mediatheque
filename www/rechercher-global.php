<?php
/**
 * API JSON — suggestions de recherche globale (bibliothèque + catalogue).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\GlobalSearch;
use Moncine\UserContext;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$userId = Auth::currentUserId();
if ($userId <= 0) {
    echo json_encode(['library' => [], 'catalog' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$limit = max(1, min(15, (int) ($_GET['limit'] ?? 8)));

$results = (new GlobalSearch())->search(
    $query,
    $userId,
    UserContext::currentFoyerId(),
    $limit
);

echo json_encode($results, JSON_UNESCAPED_UNICODE);
