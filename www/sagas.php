<?php
/**
 * Liste des sagas, détail d’une saga (films ordonnés), renommage.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MediaDomainGuards;

MediaDomainGuards::redirectUnlessFilmFeature();

use Moncine\Csrf;
use Moncine\FilmRepository;
use Moncine\View;

$repo = new FilmRepository();
$saga = trim((string) ($_GET['saga'] ?? $_POST['saga'] ?? ''));
$searched = $saga !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'rename_saga') {
    $oldName = trim((string) ($_POST['saga_old'] ?? ''));
    $redirectSaga = $oldName !== '' ? $oldName : $saga;
    $failUrl = View::sagaUrl($redirectSaga);

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
    header('Location: ' . View::sagaUrl($newName) . '&' . $params);
    exit;
}

$films = $searched ? $repo->findBySaga($saga) : [];
$sagas = $repo->listSagasWithCounts();

View::render('sagas', [
    'pageTitle' => $searched ? 'Saga : ' . $saga : 'Sagas',
    'saga' => $saga,
    'searched' => $searched,
    'films' => $films,
    'sagas' => $sagas,
]);
