<?php
/**
 * Maintenance du catalogue (admin) : doublons, fusion, nettoyage, journal.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\CatalogAuditLog;
use Moncine\CatalogMaintenance;
use Moncine\Csrf;
use Moncine\DatabaseBackupRateLimit;
use Moncine\DatabaseBackupService;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$maintenance = new CatalogMaintenance();
$audit = new CatalogAuditLog();
$message = '';
$error = '';
$adminUserId = Auth::currentUserId();
$dbBackupService = new DatabaseBackupService();
$dbBackupSqliteOk = DatabaseBackupService::sqlite3Available();

$dbBackupErrors = [
    'password' => 'Mot de passe administrateur incorrect.',
    'rate_password' => 'Trop de tentatives. Réessayez dans environ 15 minutes.',
    'rate_operation' => 'Limite d’exports / restaurations atteinte. Réessayez plus tard.',
    'export' => 'Impossible de générer la sauvegarde.',
    'upload' => 'Envoi du fichier de sauvegarde invalide.',
    'confirm' => 'Cochez la case de confirmation et saisissez RESTAURER en majuscules.',
    'restore' => 'La restauration a échoué. La base précédente a été conservée si possible.',
];
$dbBackupErrorKey = isset($_GET['db_backup_error']) ? (string) $_GET['db_backup_error'] : '';
if ($dbBackupErrorKey !== '' && isset($dbBackupErrors[$dbBackupErrorKey])) {
    $error = $dbBackupErrors[$dbBackupErrorKey];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/maintenance-catalogue.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'restore_database') {
        if (!$dbBackupSqliteOk) {
            $error = 'L’extension PHP SQLite3 est requise pour restaurer une sauvegarde.';
        } elseif (!DatabaseBackupRateLimit::allowPasswordAttempt()) {
            $error = $dbBackupErrors['rate_password'];
        } else {
            $password = (string) ($_POST['admin_password'] ?? '');
            if (!$dbBackupService->verifyAdminPassword($adminUserId, $password)) {
                DatabaseBackupRateLimit::recordPasswordFailure();
                $error = $dbBackupErrors['password'];
            } elseif (
                empty($_POST['confirm_restore'])
                || trim((string) ($_POST['confirm_phrase'] ?? '')) !== DatabaseBackupService::RESTORE_CONFIRM_PHRASE
            ) {
                $error = $dbBackupErrors['confirm'];
            } elseif (!DatabaseBackupRateLimit::allowOperation()) {
                $error = $dbBackupErrors['rate_operation'];
            } elseif (
                !isset($_FILES['backup_file'])
                || !is_array($_FILES['backup_file'])
                || (int) ($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            ) {
                $error = $dbBackupErrors['upload'];
            } else {
                $upload = $_FILES['backup_file'];
                $uploadSize = (int) ($upload['size'] ?? 0);
                if ($uploadSize <= 0 || $uploadSize > MONCINE_DB_BACKUP_MAX_BYTES) {
                    $error = $dbBackupErrors['upload'];
                } else {
                    $tmpUpload = (string) ($upload['tmp_name'] ?? '');
                    $destPath = $dbBackupService->tempBackupPath('import');
                    if ($tmpUpload === '' || !is_uploaded_file($tmpUpload) || !move_uploaded_file($tmpUpload, $destPath)) {
                        $error = $dbBackupErrors['upload'];
                    } else {
                        $restoreResult = $dbBackupService->restoreFromPath($destPath, $adminUserId);
                        if ($restoreResult === true) {
                            DatabaseBackupRateLimit::recordOperation();
                            $message = 'Base restaurée avec succès. Une copie de l’ancienne base a été conservée dans data/db_snapshots/.';
                        } else {
                            $error = is_string($restoreResult) ? $restoreResult : $dbBackupErrors['restore'];
                        }
                    }
                }
            }
        }
    } elseif ($action === 'merge_oeuvres') {
        $keepId = (int) ($_POST['keep_id'] ?? 0);
        $removeId = (int) ($_POST['remove_id'] ?? 0);
        $result = $maintenance->mergeOeuvres($keepId, $removeId, $adminUserId);
        if ($result === true) {
            $message = 'Fusion réussie : fiche #' . $removeId . ' intégrée dans #' . $keepId . '.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'purge_orphan_posters') {
        $result = $maintenance->purgeOrphanPosters($adminUserId);
        $message = $result['deleted'] . ' affiche(s) orpheline(s) supprimée(s).';
        if ($result['errors'] !== []) {
            $error = 'Échec pour : ' . implode(', ', $result['errors']);
        }
    }
}

View::render('maintenance-catalogue', [
    'pageTitle' => 'Maintenance catalogue',
    'wideLayout' => true,
    'stats' => $maintenance->dashboardStats(),
    'duplicateTitleGroups' => $maintenance->findDuplicateGroupsByTitle(),
    'duplicateTmdbGroups' => $maintenance->findDuplicateGroupsByTmdb(),
    'duplicateMagazineGroups' => $maintenance->findDuplicateMagazineIssueGroups(),
    'incompleteOeuvres' => $maintenance->findIncompleteOeuvres(),
    'orphanPosters' => $maintenance->findOrphanPosterFiles(),
    'auditLog' => $audit->listRecent(25),
    'message' => $message,
    'error' => $error,
    'dbBackupSqliteOk' => $dbBackupSqliteOk,
    'dbBackupMaxMb' => (int) floor(MONCINE_DB_BACKUP_MAX_BYTES / (1024 * 1024)),
]);
