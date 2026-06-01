<?php
/**
 * Configuration du stockage médias (admin) : racine, sous-dossiers, test lecture/écriture.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\MediaPathConfig;
use Moncine\MediaStorage;
use Moncine\StoredObjectRepository;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/maintenance-medias.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_media_root') {
        $path = (string) ($_POST['media_root'] ?? '');
        $result = MediaPathConfig::saveRootPath($path);
        if ($result === true) {
            $message = 'Racine médias enregistrée.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'reset_media_root') {
        MediaPathConfig::clearOverride();
        $message = 'Racine médias réinitialisée (valeur par défaut ou variable d’environnement).';
    } elseif ($action === 'ensure_layout') {
        $layout = MediaStorage::ensureLayout();
        if ($layout === true) {
            $message = 'Sous-dossiers créés ou déjà présents.';
        } else {
            $error = (string) $layout;
        }
    } elseif ($action === 'self_test') {
        $test = MediaPathConfig::runSelfTest();
        if ($test['ok']) {
            $message = $test['message'];
        } else {
            $error = $test['message'];
        }
        if ($test['details'] !== []) {
            $detailText = implode("\n", $test['details']);
            if ($test['ok']) {
                $message .= "\n" . $detailText;
            } else {
                $error .= "\n" . $detailText;
            }
        }
    }
}

$storedCount = 0;
if (StoredObjectRepository::tableExists()) {
    $storedCount = (int) \Moncine\Database::getInstance()->query('SELECT COUNT(*) FROM stored_objects')->fetchColumn();
}

View::render('maintenance-medias', [
    'pageTitle' => 'Stockage médias',
    'wideLayout' => true,
    'message' => $message,
    'error' => $error,
    'defaultRoot' => MediaPathConfig::defaultRootPath(),
    'effectiveRoot' => MediaPathConfig::effectiveRootPath(),
    'envRoot' => MONCINE_MEDIA_PATH,
    'hasOverride' => trim((new \Moncine\SchemaMigrator(\Moncine\Database::getInstance()))->getMetadata(MediaPathConfig::META_ROOT_PATH)) !== '',
    'subdirs' => array_values(MediaStorage::SUBDIRS),
    'storedCount' => $storedCount,
    'uploadLimitsWarning' => \Moncine\UploadLimits::phpLimitsWarning(),
    'uploadMaxLabel' => \Moncine\UploadLimits::uploadMaxFilesizeLabel(),
    'postMaxLabel' => \Moncine\UploadLimits::postMaxSizeLabel(),
    'pdfMaxLabel' => \Moncine\UploadLimits::maxPdfBytesLabel(),
]);
