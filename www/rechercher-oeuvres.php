<?php
/**
 * API JSON — suggestions du catalogue pour l’autocomplétion du titre (ajout film).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\FilmRepository;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$repo = new FilmRepository();
if (!$repo->usesCatalogModel()) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);

    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$results = $repo->searchCatalogOeuvres($query, 20);

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
