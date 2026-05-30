<?php
/**
 * Téléchargement de la base SQLite complète — administrateur uniquement, POST + mot de passe.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Auth;
use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\DatabaseBackupRateLimit;
use Moncine\DatabaseBackupService;

CatalogAdmin::denyUnlessAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /maintenance-catalogue.php');
    exit;
}

Csrf::rejectUnlessValid($_POST, '/maintenance-catalogue.php');

$returnUrl = '/maintenance-catalogue.php';
$adminUserId = Auth::currentUserId();
$password = (string) ($_POST['admin_password'] ?? '');

$service = new DatabaseBackupService();

if (!DatabaseBackupRateLimit::allowPasswordAttempt()) {
    header('Location: ' . $returnUrl . '?db_backup_error=rate_password');
    exit;
}

if (!$service->verifyAdminPassword($adminUserId, $password)) {
    DatabaseBackupRateLimit::recordPasswordFailure();
    header('Location: ' . $returnUrl . '?db_backup_error=password');
    exit;
}

if (!DatabaseBackupRateLimit::allowOperation()) {
    header('Location: ' . $returnUrl . '?db_backup_error=rate_operation');
    exit;
}

$tempPath = $service->tempBackupPath('export');
$result = $service->exportToPath($tempPath);
if ($result !== true) {
    if (is_file($tempPath)) {
        @unlink($tempPath);
    }
    header('Location: ' . $returnUrl . '?db_backup_error=export');
    exit;
}

DatabaseBackupRateLimit::recordOperation();
$service->logExport($adminUserId);

try {
    $service->sendDownload($tempPath);
} catch (\Throwable) {
    if (is_file($tempPath)) {
        @unlink($tempPath);
    }
    header('Location: ' . $returnUrl . '?db_backup_error=export');
    exit;
}

exit;
