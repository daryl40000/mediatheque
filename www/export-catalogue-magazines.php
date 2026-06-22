<?php
/**
 * Export JSON du catalogue magazines (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\MagazineCatalogExporter;
use Moncine\MagazineRepository;

CatalogAdmin::denyUnlessAccess();

if (!MagazineRepository::isAvailable()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Module magazines non disponible.';
    exit;
}

$magFilter = trim((string) ($_GET['magazine'] ?? ''));
$filters = $magFilter !== '' ? [$magFilter] : [];

$export = (new MagazineCatalogExporter())->exportToArray($filters);
$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Échec de l’encodage JSON.';
    exit;
}

$filename = 'magazines-catalogue';
if ($magFilter !== '') {
    $filename .= '-' . preg_replace('/[^a-z0-9_-]+/i', '-', $magFilter);
}
$filename .= '-' . date('Y-m-d') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo $json . "\n";
