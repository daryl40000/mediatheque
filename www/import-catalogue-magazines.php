<?php
/**
 * Import catalogue magazines depuis un export JSON (admin).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\MagazineCatalogImporter;
use Moncine\MagazineRepository;
use Moncine\MediaDomain;
use Moncine\SeriesRepository;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$message = '';
$errors = [];
$importResult = null;
$magazineModuleAvailable = MagazineRepository::isAvailable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/import-catalogue-magazines.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_series') {
        if (!$magazineModuleAvailable) {
            $errors[] = 'Module magazines non disponible.';
        } else {
            $result = (new SeriesRepository())->create([
                'titre' => trim((string) ($_POST['series_titre'] ?? '')),
                'publication_type' => (string) ($_POST['publication_type'] ?? 'mensuel'),
                'editeur' => trim((string) ($_POST['editeur'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ], MediaDomain::MAGAZINE);

            if (!is_int($result)) {
                $errors[] = (string) $result;
            } else {
                header('Location: /import-catalogue-magazines.php?series_created=' . $result);
                exit;
            }
        }
    }

    if ($action === 'import_json') {
        if (!$magazineModuleAvailable) {
            $errors[] = 'Module magazines non disponible.';
        } else {
            @set_time_limit(600);

            $jsonRaw = '';
            $uploadError = (int) ($_FILES['json_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_OK) {
                $jsonRaw = (string) file_get_contents((string) $_FILES['json_file']['tmp_name']);
            } elseif (trim((string) ($_POST['json_path'] ?? '')) !== '') {
                $path = trim((string) $_POST['json_path']);
                if (!str_starts_with($path, '/')) {
                    $path = dirname(__DIR__) . '/' . ltrim($path, '/');
                }
                if (!is_file($path)) {
                    $errors[] = 'Fichier introuvable : ' . $path;
                } else {
                    $jsonRaw = (string) file_get_contents($path);
                }
            } else {
                $errors[] = 'Sélectionnez un fichier JSON ou indiquez un chemin sur le serveur.';
            }

            if ($errors === [] && $jsonRaw !== '') {
                $export = MagazineCatalogImporter::parseJsonString($jsonRaw);
                if ($export === null) {
                    $errors[] = 'Fichier JSON invalide ou vide.';
                } else {
                    $options = [
                        'dry_run' => !empty($_POST['dry_run']),
                        'skip_existing' => !empty($_POST['skip_existing']),
                        'download_covers' => !empty($_POST['download_covers']),
                        'cover_batch_size' => (int) ($_POST['cover_batch_size'] ?? MagazineCatalogImporter::DEFAULT_COVER_BATCH_SIZE),
                    ];

                    $magFilter = trim((string) ($_POST['magazine_filter'] ?? ''));
                    if ($magFilter !== '') {
                        $options['series_filter'] = [$magFilter];
                    }

                    $importResult = (new MagazineCatalogImporter())->importFromExportArray($export, $options);
                    if ($importResult['errors'] !== []) {
                        $errors = array_merge($errors, array_slice($importResult['errors'], 0, 20));
                        if (count($importResult['errors']) > 20) {
                            $errors[] = '… et ' . (count($importResult['errors']) - 20) . ' autre(s) erreur(s).';
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['series_created'])) {
    $seriesId = (int) $_GET['series_created'];
    if ($seriesId > 0) {
        $message = 'Série catalogue créée (#' . $seriesId . ') — sans ajout à votre bibliothèque.';
    }
}

View::render('import-catalogue-magazines', [
    'pageTitle' => 'Import catalogue magazines',
    'magazineModuleAvailable' => $magazineModuleAvailable,
    'message' => $message,
    'errors' => $errors,
    'importResult' => $importResult,
    'defaultJsonPath' => 'install_seed/abm-magazines.json',
]);
