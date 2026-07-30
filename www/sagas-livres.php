<?php
/**
 * Liste et détail des sagas livres.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\Csrf;
use Moncine\LivreRepository;
use Moncine\MediaDomainGuards;
use Moncine\UserContext;
use Moncine\View;

MediaDomainGuards::renderCollectionPageOrExit();
MediaDomainGuards::ensureLivreContext('/sagas-livres.php');

$repo = new LivreRepository();
$userId = UserContext::currentUserId();
$foyerId = UserContext::currentFoyerId();
$saga = trim((string) ($_GET['saga'] ?? $_POST['saga'] ?? ''));
$searched = $saga !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'rename_saga') {
    $oldName = trim((string) ($_POST['saga_old'] ?? ''));
    $redirectSaga = $oldName !== '' ? $oldName : $saga;
    $failUrl = View::sagasLivresUrl($redirectSaga);

    Csrf::rejectUnlessValid($_POST, $failUrl);

    $newName = trim((string) ($_POST['saga_new'] ?? ''));
    $result = $repo->renameSaga($oldName, $newName);

    if (!$result['ok']) {
        header('Location: ' . $failUrl . '&rename_error=' . rawurlencode($result['error']));
        exit;
    }

    $params = http_build_query([
        'renamed' => '1',
        'count' => $result['updated'],
    ]);
    header('Location: ' . View::sagasLivresUrl($newName) . '&' . $params);
    exit;
}

$books = $searched ? $repo->findBySaga($saga, $userId, $foyerId) : [];
$sagas = LivreRepository::isAvailable()
    ? $repo->listSagasWithCounts($userId, $foyerId)
    : [];

View::render('sagas-livres', [
    'pageTitle' => $searched ? 'Saga : ' . $saga : 'Sagas livres',
    'saga' => $saga,
    'searched' => $searched,
    'books' => $books,
    'sagas' => $sagas,
    'moduleError' => LivreRepository::isAvailable() ? '' : 'Le module livres n’est pas disponible.',
]);
