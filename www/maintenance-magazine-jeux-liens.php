<?php
/**
 * Maintenance admin — rattachement rétroactif sujets magazine ↔ jeux catalogue.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\Csrf;
use Moncine\MagazineGameLinkMaintenance;
use Moncine\View;

CatalogAdmin::denyUnlessAccess();

$query = trim((string) ($_GET['q'] ?? ''));
$view = (string) ($_GET['view'] ?? 'unlinked');
if (!in_array($view, ['unlinked', 'linked'], true)) {
    $view = 'unlinked';
}

if (!MagazineGameLinkMaintenance::isAvailable()) {
    View::render('maintenance-magazine-jeux-liens', [
        'pageTitle' => 'Liens magazine ↔ jeux',
        'wideLayout' => true,
        'moduleError' => 'Le pont magazine ↔ jeux n’est pas disponible. Exécutez les migrations jeux (039+).',
        'stats' => ['linkable_total' => 0, 'linked_count' => 0, 'unlinked_count' => 0],
        'subjects' => [],
        'query' => $query,
        'view' => $view,
        'message' => '',
        'error' => '',
    ]);
    exit;
}

$maintenance = new MagazineGameLinkMaintenance();
$message = '';
$error = '';
$adminUserId = \Moncine\Auth::currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::rejectUnlessValid($_POST, '/maintenance-magazine-jeux-liens.php');

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'link_subject') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $catalogOeuvreId = max(0, (int) ($_POST['catalog_oeuvre_id'] ?? 0));
        $result = $maintenance->setSubjectCatalogLink(
            $subjectId,
            $catalogOeuvreId > 0 ? $catalogOeuvreId : null,
            $adminUserId
        );
        if ($result === true) {
            $message = $catalogOeuvreId > 0
                ? 'Sujet #' . $subjectId . ' relié au jeu catalogue #' . $catalogOeuvreId . '.'
                : 'Lien catalogue retiré pour le sujet #' . $subjectId . '.';
        } else {
            $error = (string) $result;
        }
    }
}

$subjects = $view === 'linked'
    ? $maintenance->findLinkedSubjects($query, 100)
    : $maintenance->findUnlinkedSubjects($query, 100);

View::render('maintenance-magazine-jeux-liens', [
    'pageTitle' => 'Liens magazine ↔ jeux',
    'wideLayout' => true,
    'moduleError' => '',
    'stats' => $maintenance->dashboardStats(),
    'subjects' => $subjects,
    'query' => $query,
    'view' => $view,
    'message' => $message,
    'error' => $error,
]);
