<?php
/**
 * Import catalogue BD / Manga depuis un CSV (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdCatalogImporter;
use Moncine\BdRepository;
use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\LibraryStatut;
use Moncine\UserContext;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$message = '';
$errors = [];
$importResult = null;
$bdModuleAvailable = BdRepository::isAvailable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/import-catalogue-bd.php');

    if (!$bdModuleAvailable) {
        $errors[] = 'Module BD non disponible.';
    } else {
        @set_time_limit(600);

        $uploadError = (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
                UPLOAD_ERR_NO_FILE => 'Sélectionnez un fichier CSV.',
                default => 'Échec de l’envoi du fichier.',
            };
        } else {
            $tmp = (string) ($_FILES['csv_file']['tmp_name'] ?? '');
            $options = [
                'dry_run' => !empty($_POST['dry_run']),
                'skip_existing' => !empty($_POST['skip_existing']),
                'add_to_library' => !empty($_POST['add_to_library']),
                'user_id' => UserContext::currentUserId(),
                'foyer_id' => UserContext::currentFoyerId(),
                'library_statut' => LibraryStatut::COLLECTION,
            ];

            $importResult = (new BdCatalogImporter())->importFromPath($tmp, $options);
            if ($importResult['errors'] !== [] && (int) $importResult['tomes_created'] === 0
                && (int) $importResult['series_created'] === 0) {
                $errors = array_merge($errors, $importResult['errors']);
            } else {
                $message = !empty($importResult['dry_run'])
                    ? 'Simulation terminée (aucune écriture en base).'
                    : 'Import terminé.';
            }
        }
    }
}

View::render('import-catalogue-bd', [
    'pageTitle' => 'Import catalogue BD',
    'bdModuleAvailable' => $bdModuleAvailable,
    'message' => $message,
    'errors' => $errors,
    'importResult' => $importResult,
]);
