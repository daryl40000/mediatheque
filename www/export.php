<?php
/**
 * Téléchargement export bibliothèque (utilisateur) ou catalogue (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\ExportCatalog;
use Moncine\ExportLibrary;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /import.php');
    exit;
}

$returnUrl = trim((string) ($_POST['return'] ?? '/import.php'));
if ($returnUrl === '' || !str_starts_with($returnUrl, '/')) {
    $returnUrl = '/import.php';
}

Csrf::rejectUnlessValid($_POST, $returnUrl);

$scope = strtolower(trim((string) ($_POST['scope'] ?? 'library')));
$format = strtolower(trim((string) ($_POST['format'] ?? '')));

if (!in_array($format, ['csv', 'ods', 'zip'], true)) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'export_error=format');
    exit;
}

try {
    if ($scope === 'catalog') {
        CatalogAdmin::denyUnlessAccess();
        $exporter = new ExportCatalog();
        if ($exporter->catalogEntryCount() === 0) {
            header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'export_error=empty_catalog');
            exit;
        }
        if ($format === 'zip') {
            $exporter->sendPostersZipDownload();
        } elseif ($format === 'csv') {
            $exporter->sendCsvDownload();
        } else {
            $exporter->sendOdsDownload();
        }
        exit;
    }

    $exporter = new ExportLibrary();
    if ($exporter->libraryEntryCount() === 0) {
        header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'export_error=empty');
        exit;
    }

    if ($format === 'csv') {
        $exporter->sendCsvDownload();
    } else {
        $exporter->sendOdsDownload();
    }
} catch (\Throwable) {
    header('Location: ' . $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'export_error=failed');
}

exit;
