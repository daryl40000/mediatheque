<?php
/**
 * Import CSV de la dvdthèque + enrichissement TMDB.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\ExportCatalog;
use Moncine\FilmEnricher;
use Moncine\FilmRepository;
use Moncine\GameEnricher;
use Moncine\GameRepository;
use Moncine\IgdbConfig;
use Moncine\ImportCsv;
use Moncine\ImportOds;
use Moncine\ImportPostersZip;
use Moncine\PosterIdRemap;
use Moncine\SteamConfig;
use Moncine\SteamLibraryImporter;
use Moncine\StoreLinkEnricher;
use Moncine\OeuvreStoreLinkRepository;
use Moncine\TmdbConfig;
use Moncine\UploadLimits;
use Moncine\UserContext;
use Moncine\View;

$message = '';
$posterZipMessage = '';
$posterRemapMessage = '';
$errors = [];
$tmdbMessage = '';
$enrichMessage = '';
$igdbMessage = '';
$igdbEnrichMessage = '';
$steamMessage = '';
$steamImportMessage = '';

if (isset($_GET['export_error'])) {
    $exportErr = (string) $_GET['export_error'];
    $errors[] = match ($exportErr) {
        'empty' => 'Aucune entrée dans votre bibliothèque à exporter.',
        'empty_catalog' => 'Le catalogue est vide — rien à exporter.',
        'format' => 'Format d’export non reconnu.',
        'failed' => 'Export impossible. Réessayez ou consultez les logs du serveur.',
        default => 'Export impossible.',
    };
}
if (isset($_GET['tmdb_key_saved'])) {
    $tmdbMessage = 'Clé API TMDB enregistrée — les synopsis seront récupérés en français.';
}
if (isset($_GET['tmdb_key_error'])) {
    $errors[] = 'Impossible d\'enregistrer la clé TMDB (vérifiez les droits sur le dossier data/).';
}
if (isset($_GET['tmdb_key_cleared'])) {
    $tmdbMessage = 'Clé TMDB supprimée du serveur. Vous pouvez en enregistrer une nouvelle ci-dessous.';
}
if (isset($_GET['tmdb_key_clear_env'])) {
    $errors[] = 'La clé TMDB est définie par la variable d’environnement MONCINE_TMDB_API_KEY : '
        . 'supprimez-la ou modifiez-la dans la configuration PHP-FPM du serveur (pas depuis cette page).';
}
if (isset($_GET['tmdb_key_clear_error'])) {
    $errors[] = 'Impossible de supprimer la clé TMDB (droits sur le dossier data/ ?).';
}
if (isset($_GET['tmdb_test'])) {
    $msg = (string) ($_GET['tmdb_test_msg'] ?? '');
    $tmdbMessage = ($_GET['tmdb_test'] === 'ok' ? '✓ ' : '✗ ') . $msg;
}
if (isset($_GET['csrf_error'])) {
    $errors[] = Csrf::REJECT_MESSAGE;
    $errors[] = 'Si vous envoyiez un gros fichier (ZIP affiches), la cause est souvent la limite PHP '
        . '(post_max_size = ' . UploadLimits::postMaxSizeLabel()
        . ', upload_max_filesize = ' . UploadLimits::uploadMaxFilesizeLabel()
        . ') — pas un problème de droits sur le dossier posters/.';
}
if (isset($_GET['enrich_done'])) {
    $processed = (int) ($_GET['processed'] ?? 0);
    $enriched = (int) ($_GET['enriched'] ?? 0);
    $notFound = (int) ($_GET['not_found'] ?? 0);
    $remaining = (int) ($_GET['remaining'] ?? 0);
    $enrichMessage = sprintf(
        'Lot traité : %d film(s), %d enrichi(s), %d introuvable(s) sur TMDB.',
        $processed,
        $enriched,
        $notFound
    );
    if (!empty($_SESSION['enrich_last_errors'])) {
        $errors = array_merge($errors, $_SESSION['enrich_last_errors']);
        unset($_SESSION['enrich_last_errors']);
        $enrichMessage .= ' Détail des erreurs ci-dessous.';
    }
    if ($remaining > 0) {
        $enrichMessage .= ' Il reste ' . $remaining . ' film(s) à traiter — relancez le bouton.';
    } else {
        $enrichMessage .= ' Enrichissement terminé pour tous vos films.';
    }
}

if (isset($_GET['igdb_saved'])) {
    $igdbMessage = 'Identifiants IGDB enregistrés — vous pouvez enrichir vos jeux.';
}
if (isset($_GET['igdb_error'])) {
    $errors[] = 'Impossible d\'enregistrer les identifiants IGDB (vérifiez les droits sur le dossier data/).';
}
if (isset($_GET['igdb_cleared'])) {
    $igdbMessage = 'Identifiants IGDB supprimés du serveur.';
}
if (isset($_GET['igdb_clear_env'])) {
    $errors[] = 'Les identifiants IGDB viennent de MONCINE_IGDB_CLIENT_ID / MONCINE_IGDB_CLIENT_SECRET : '
        . 'modifiez-les dans la configuration du serveur.';
}
if (isset($_GET['igdb_clear_error'])) {
    $errors[] = 'Impossible de supprimer les identifiants IGDB.';
}
if (isset($_GET['igdb_test'])) {
    $msg = (string) ($_GET['igdb_test_msg'] ?? '');
    $igdbMessage = ($_GET['igdb_test'] === 'ok' ? '✓ ' : '✗ ') . $msg;
}
if (isset($_GET['igdb_enrich_done'])) {
    $processed = (int) ($_GET['igdb_processed'] ?? 0);
    $enriched = (int) ($_GET['igdb_enriched'] ?? 0);
    $notFound = (int) ($_GET['igdb_not_found'] ?? 0);
    $remaining = (int) ($_GET['igdb_remaining'] ?? 0);
    $igdbEnrichMessage = sprintf(
        'Lot traité : %d jeu(x), %d enrichi(s), %d introuvable(s) sur IGDB.',
        $processed,
        $enriched,
        $notFound
    );
    if (!empty($_SESSION['igdb_enrich_last_errors'])) {
        $errors = array_merge($errors, $_SESSION['igdb_enrich_last_errors']);
        unset($_SESSION['igdb_enrich_last_errors']);
        $igdbEnrichMessage .= ' Détail des erreurs ci-dessous.';
    }
    if ($remaining > 0) {
        $igdbEnrichMessage .= ' Il reste ' . $remaining . ' jeu(x) à traiter — relancez le bouton.';
    } else {
        $igdbEnrichMessage .= ' Enrichissement terminé pour tous vos jeux.';
    }
}

$storeLinksMessage = '';
$storeLinksEnrichMessage = '';
if (isset($_GET['store_links_test'])) {
    $msg = (string) ($_GET['store_links_test_msg'] ?? '');
    $storeLinksMessage = ($_GET['store_links_test'] === 'ok' ? '✓ ' : '✗ ') . $msg;
}
if (isset($_GET['store_links_done'])) {
    $processed = (int) ($_GET['store_links_processed'] ?? 0);
    $linked = (int) ($_GET['store_links_linked'] ?? 0);
    $pending = (int) ($_GET['store_links_pending'] ?? 0);
    $skipped = (int) ($_GET['store_links_skipped'] ?? 0);
    $storeLinksEnrichMessage = sprintf(
        'Liens magasins : %d fiche(s) traitée(s), %d liée(s) automatiquement, %d à revoir, %d ignorée(s).',
        $processed,
        $linked,
        $pending,
        $skipped
    );
    if (!empty($_SESSION['store_links_enrich_errors'])) {
        $errors = array_merge($errors, $_SESSION['store_links_enrich_errors']);
        unset($_SESSION['store_links_enrich_errors']);
        $storeLinksEnrichMessage .= ' Détail des erreurs ci-dessous.';
    }
}

if (isset($_GET['steam_key_saved'])) {
    $steamMessage = 'Clé API Steam enregistrée.';
}
if (isset($_GET['steam_key_error'])) {
    $errors[] = 'Impossible d’enregistrer la clé Steam (vérifiez les droits sur le dossier data/).';
}
if (isset($_GET['steam_key_cleared'])) {
    $steamMessage = 'Clé Steam supprimée du serveur.';
}
if (isset($_GET['steam_key_clear_env'])) {
    $errors[] = 'La clé Steam est définie par MONCINE_STEAM_API_KEY : modifiez-la dans la configuration du serveur.';
}
if (isset($_GET['steam_key_clear_error'])) {
    $errors[] = 'Impossible de supprimer la clé Steam.';
}
if (isset($_GET['steam_test'])) {
    $msg = (string) ($_GET['steam_test_msg'] ?? '');
    $steamMessage = ($_GET['steam_test'] === 'ok' ? '✓ ' : '✗ ') . $msg;
}
if (isset($_GET['steam_prepare_error'])) {
    $errors[] = (string) ($_GET['steam_prepare_msg'] ?? 'Préparation Steam impossible.');
}
if (isset($_GET['steam_import_done'])) {
    $added = (int) ($_GET['steam_added'] ?? 0);
    $updated = (int) ($_GET['steam_updated'] ?? 0);
    $proposed = (int) ($_GET['steam_proposed'] ?? 0);
    $skipped = (int) ($_GET['steam_skipped'] ?? 0);
    $steamImportMessage = sprintf(
        'Import Steam : %d ajouté(s), %d mis à jour, %d ignoré(s).',
        $added,
        $updated,
        $skipped
    );
    if ($proposed > 0) {
        $steamImportMessage .= ' ' . $proposed . ' proposition(s) envoyée(s) — ajout à Mes jeux en attente de validation catalogue.';
    }
    if (!empty($_SESSION['steam_import_last_errors'])) {
        $errors = array_merge($errors, $_SESSION['steam_import_last_errors']);
        unset($_SESSION['steam_import_last_errors']);
        $steamImportMessage .= ' Détail des erreurs ci-dessous.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (UploadLimits::postBodyWasDiscarded()) {
        $errors[] = UploadLimits::postTooLargeMessage();
    } elseif (($_POST['action'] ?? '') === 'remap_posters') {
        if (!Csrf::validateFromPost($_POST)) {
            header('Location: /import.php?csrf_error=1');
            exit;
        }

        if (!CatalogAdmin::canAccess()) {
            $errors[] = 'Le recalage des affiches est réservé à l’administrateur.';
        } else {
            $uploadError = (int) ($_FILES['remap_catalog_csv']['error'] ?? UPLOAD_ERR_NO_FILE);
            if (!isset($_FILES['remap_catalog_csv']) || $uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier CSV trop volumineux.',
                    UPLOAD_ERR_NO_FILE => 'Sélectionnez l’export catalogue de l’ancienne instance (avec ID catalogue).',
                    default => 'Erreur lors de l’envoi du CSV.',
                };
            } else {
                $result = (new PosterIdRemap())->remapFromCatalogExportPath(
                    (string) $_FILES['remap_catalog_csv']['tmp_name']
                );
                $posterRemapMessage = sprintf('%d affiche(s) recalée(s).', $result['remapped']);
                if ($result['skipped'] > 0) {
                    $posterRemapMessage .= ' ' . $result['skipped'] . ' ignorée(s).';
                }
                $errors = array_merge($errors, $result['errors']);
            }
        }
    } elseif (($_POST['action'] ?? '') === 'import_posters_zip') {
        if (!Csrf::validateFromPost($_POST)) {
            header('Location: /import.php?csrf_error=1');
            exit;
        }

        if (!CatalogAdmin::canAccess()) {
            $errors[] = 'L’import des affiches ZIP est réservé à l’administrateur.';
        } else {
            $uploadError = (int) ($_FILES['posters_zip']['error'] ?? UPLOAD_ERR_NO_FILE);
            if (!isset($_FILES['posters_zip']) || $uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Archive trop volumineuse (maximum '
                        . (int) (MONCINE_POSTERS_ZIP_MAX_BYTES / 1024 / 1024) . ' Mo).',
                    UPLOAD_ERR_NO_FILE => 'Aucune archive ZIP sélectionnée.',
                    default => 'Erreur lors de l’envoi du fichier ZIP.',
                };
            } elseif ((int) $_FILES['posters_zip']['size'] > MONCINE_POSTERS_ZIP_MAX_BYTES) {
                $errors[] = 'Archive trop volumineuse (maximum '
                    . (int) (MONCINE_POSTERS_ZIP_MAX_BYTES / 1024 / 1024) . ' Mo).';
            } else {
                $ext = strtolower(pathinfo((string) ($_FILES['posters_zip']['name'] ?? ''), PATHINFO_EXTENSION));
                if ($ext !== 'zip') {
                    $errors[] = 'Le fichier doit être une archive .zip.';
                } else {
                    $result = (new ImportPostersZip())->importFromPath(
                        (string) $_FILES['posters_zip']['tmp_name']
                    );
                    $posterZipMessage = sprintf(
                        '%d affiche(s) importée(s).',
                        $result['imported']
                    );
                    if ($result['skipped'] > 0) {
                        $posterZipMessage .= ' ' . $result['skipped'] . ' ignorée(s).';
                    }
                    $errors = array_merge($errors, $result['errors']);
                }
            }
        }
    } elseif (!isset($_POST['action'])) {
        if (!Csrf::validateFromPost($_POST)) {
            header('Location: /import.php?csrf_error=1');
            exit;
        }

        $replaceAll = isset($_POST['replace_all']);
        $replaceCatalog = CatalogAdmin::canAccess() && isset($_POST['replace_catalog']);

        $uploadError = (int) ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if (!isset($_FILES['csv_file']) || $uploadError !== UPLOAD_ERR_OK) {
            $errors[] = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (maximum '
                    . (int) (MONCINE_CSV_MAX_BYTES / 1024 / 1024) . ' Mo).',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné.',
                default => 'Erreur lors de l\'envoi du fichier.',
            };
        } elseif ((int) $_FILES['csv_file']['size'] > MONCINE_CSV_MAX_BYTES) {
            $errors[] = 'Fichier trop volumineux (maximum '
                . (int) (MONCINE_CSV_MAX_BYTES / 1024 / 1024) . ' Mo).';
        } else {
            $tmp = $_FILES['csv_file']['tmp_name'];

            if ($replaceAll) {
                (new FilmRepository())->deleteAll();
            }

            $ext = strtolower(pathinfo((string) ($_FILES['csv_file']['name'] ?? ''), PATHINFO_EXTENSION));
            if ($ext === 'ods') {
                $result = (new ImportOds())->importFromPath($tmp, $replaceCatalog);
            } else {
                $result = (new ImportCsv())->importFromPath($tmp, MONCINE_CSV_DELIMITER, $replaceCatalog);
            }

            $message = sprintf(
                '%d entrée(s) importée(s) ou mise(s) à jour. %d vision(s) enregistrée(s) dans l’historique.',
                $result['imported'],
                $result['vues']
            );
            if (!empty($result['format_label'])) {
                $message .= ' Format détecté : ' . $result['format_label'] . '.';
            }
            if (!empty($result['catalog_cleared'])) {
                $message .= ' Catalogue réinitialisé avant import.';
            }
            if (empty($result['has_id_column'])) {
                $errors[] = 'Aucune colonne « ID catalogue » détectée dans le fichier : '
                    . 'les numéros des œuvres ne peuvent pas être conservés (affiches décalées).';
            }
            $errors = array_merge($errors, $result['errors']);
        }
    }
}

$repo = new FilmRepository();
$filmCount = $repo->count();
$libraryCount = $repo->usesCatalogModel() ? $repo->countLibraryEntries() : $filmCount;
$catalogCount = CatalogAdmin::canAccess() ? (new ExportCatalog())->catalogEntryCount() : 0;
$enrichPending = (new FilmEnricher())->countPending();
$hasTmdbKey = TmdbConfig::hasApiKey();
$tmdbKeyFromEnvironment = TmdbConfig::getKeySource() === TmdbConfig::SOURCE_ENVIRONMENT;
$igdbEnrichPending = GameRepository::isAvailable() ? (new GameEnricher())->countPending() : 0;
$hasIgdbCredentials = IgdbConfig::hasCredentials();
$igdbCredentialsFromEnvironment = IgdbConfig::getCredentialsSource() === IgdbConfig::SOURCE_ENVIRONMENT;
$igdbModuleReady = GameRepository::hasIgdbColumns();
$steamModuleReady = SteamLibraryImporter::isAvailable();
$hasSteamApiKey = SteamConfig::hasApiKey();
$steamKeyFromEnvironment = SteamConfig::getKeySource() === SteamConfig::SOURCE_ENVIRONMENT;
$steamImporter = new SteamLibraryImporter();
$userSteamId = $steamModuleReady
    ? $steamImporter->getUserSteamId(UserContext::currentUserId())
    : '';
$hasUserSteamId = SteamConfig::isValidSteamId($userSteamId);
$canPrepareSteamImport = SteamLibraryImporter::canImport() && $hasUserSteamId;
$storeLinksModuleReady = StoreLinkEnricher::isAvailable();
$storeLinksPendingReview = $storeLinksModuleReady
    ? (new OeuvreStoreLinkRepository())->countPendingReview()
    : 0;

View::render('import', [
    'pageTitle' => 'Importer',
    'message' => $message,
    'posterZipMessage' => $posterZipMessage,
    'posterRemapMessage' => $posterRemapMessage,
    'errors' => $errors,
    'tmdbMessage' => $tmdbMessage,
    'enrichMessage' => $enrichMessage,
    'filmCount' => $filmCount,
    'libraryCount' => $libraryCount,
    'catalogCount' => $catalogCount,
    'canManageCatalog' => CatalogAdmin::canAccess(),
    'enrichPending' => $enrichPending,
    'hasTmdbKey' => $hasTmdbKey,
    'tmdbKeyFromEnvironment' => $tmdbKeyFromEnvironment,
    'enrichBatchSize' => MONCINE_ENRICH_BATCH_SIZE,
    'igdbMessage' => $igdbMessage,
    'igdbEnrichMessage' => $igdbEnrichMessage,
    'igdbEnrichPending' => $igdbEnrichPending,
    'hasIgdbCredentials' => $hasIgdbCredentials,
    'igdbCredentialsFromEnvironment' => $igdbCredentialsFromEnvironment,
    'igdbModuleReady' => $igdbModuleReady,
    'steamMessage' => $steamMessage,
    'steamImportMessage' => $steamImportMessage,
    'steamModuleReady' => $steamModuleReady,
    'hasSteamApiKey' => $hasSteamApiKey,
    'steamKeyFromEnvironment' => $steamKeyFromEnvironment,
    'canPrepareSteamImport' => $canPrepareSteamImport,
    'hasUserSteamId' => $hasUserSteamId,
    'phpPostMaxSize' => UploadLimits::postMaxSizeLabel(),
    'phpUploadMaxSize' => UploadLimits::uploadMaxFilesizeLabel(),
    'importEngineBuild' => MONCINE_IMPORT_ENGINE_BUILD,
    'storeLinksModuleReady' => $storeLinksModuleReady,
    'storeLinksMessage' => $storeLinksMessage,
    'storeLinksEnrichMessage' => $storeLinksEnrichMessage,
    'storeLinksPendingReview' => $storeLinksPendingReview,
]);
