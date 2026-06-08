<?php
/**
 * Maintenance admin — sujets magazines (orphelins, doublons).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\MagazineSubjectMaintenance;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

if (!MagazineSubjectMaintenance::isAvailable()) {
    View::render('maintenance-magazine-sujets', [
        'pageTitle' => 'Sujets magazines',
        'wideLayout' => true,
        'moduleError' => 'Le module sujets magazines n’est pas disponible. Exécutez les migrations.',
        'stats' => ['total' => 0, 'orphan_count' => 0, 'duplicate_groups' => 0],
        'orphanSubjects' => [],
        'duplicateGroups' => [],
        'message' => '',
        'error' => '',
    ]);
    exit;
}

$maintenance = new MagazineSubjectMaintenance();
$message = '';
$error = '';
$adminUserId = \Moncine\Auth::currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/maintenance-magazine-sujets.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_orphan') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $result = $maintenance->deleteOrphanSubject($subjectId, $adminUserId);
        if ($result === true) {
            $message = 'Sujet #' . $subjectId . ' supprimé.';
        } else {
            $error = (string) $result;
        }
    } elseif ($action === 'purge_orphans') {
        $result = $maintenance->purgeOrphanSubjects($adminUserId);
        $message = $result['deleted'] . ' sujet(s) orphelin(s) supprimé(s).';
        if ($result['errors'] !== []) {
            $error = 'Échec pour : ' . implode(', ', $result['errors']);
        }
    } elseif ($action === 'merge_subjects') {
        $keepId = (int) ($_POST['keep_id'] ?? 0);
        $removeId = (int) ($_POST['remove_id'] ?? 0);
        $result = $maintenance->mergeSubjects($keepId, $removeId, $adminUserId);
        if ($result === true) {
            $message = 'Fusion réussie : sujet #' . $removeId . ' intégré dans #' . $keepId . '.';
        } else {
            $error = (string) $result;
        }
    }
}

View::render('maintenance-magazine-sujets', [
    'pageTitle' => 'Sujets magazines',
    'wideLayout' => true,
    'moduleError' => '',
    'stats' => $maintenance->dashboardStats(),
    'orphanSubjects' => $maintenance->findOrphanSubjects(100),
    'duplicateGroups' => $maintenance->findDuplicateGroupsByLabelKey(30),
    'message' => $message,
    'error' => $error,
]);
