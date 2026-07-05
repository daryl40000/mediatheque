<?php
/**
 * Aperçu avant import de la bibliothèque Steam.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\SteamLibraryImporter;
use Moncine\UserContext;
use Moncine\View;

$userId = UserContext::currentUserId();
$importer = new SteamLibraryImporter();

if (!$importer::isAvailable()) {
    header('Location: /import.php?steam_prepare_error=1&steam_prepare_msg=' . rawurlencode('Migration Steam non appliquée.'));
    exit;
}

$preview = $importer->getStoredPreview($userId);
if ($preview === null) {
    header('Location: /import.php?steam_prepare_error=1&steam_prepare_msg=' . rawurlencode('Aucun aperçu en cours — relancez la préparation.'));
    exit;
}

$summary = [
    'total' => count($preview),
    'in_library' => 0,
    'catalog_only' => 0,
    'new' => 0,
    'with_igdb' => 0,
];

foreach ($preview as $row) {
    $status = (string) ($row['status'] ?? 'new');
    if ($status === 'in_library') {
        $summary['in_library']++;
    } elseif ($status === 'catalog_only') {
        $summary['catalog_only']++;
    } else {
        $summary['new']++;
    }
    if ((int) ($row['igdb_id'] ?? 0) > 0) {
        $summary['with_igdb']++;
    }
}

$importRows = [];
$proposalRows = [];
foreach ($preview as $row) {
    if ((string) ($row['row_kind'] ?? '') === 'import' || (int) ($row['oeuvre_id'] ?? 0) > 0) {
        $importRows[] = $row;
    } else {
        $proposalRows[] = $row;
    }
}

View::render('import-steam', [
    'pageTitle' => 'Import Steam — aperçu',
    'importRows' => $importRows,
    'proposalRows' => $proposalRows,
    'summary' => $summary,
    'canCreateCatalogEntries' => SteamLibraryImporter::canCreateCatalogEntries(),
    'mapMessage' => isset($_GET['steam_mapped']) ? 'Lien Steam enregistré — le jeu apparaît maintenant dans « Ajouter à ma bibliothèque ».' : '',
    'mapError' => trim((string) ($_GET['steam_map_msg'] ?? '')),
]);
