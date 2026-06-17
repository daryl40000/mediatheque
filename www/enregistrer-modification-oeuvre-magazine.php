<?php
/**
 * Enregistre la modification d’un numéro magazine catalogue (administrateur).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogListContext;
use Moncine\Csrf;
use Moncine\MagazineRepository;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_POST);
$returnUrl = View::oeuvreMagazineUrl(
    $oeuvreId,
    $catalogListContext->search(),
    $catalogListContext->sortBy(),
    $catalogListContext->sortDir(),
    $catalogListContext->page()
);

Csrf::rejectUnlessValid($_POST, $returnUrl);

if ($oeuvreId <= 0) {
    header('Location: ' . $returnUrl . '&save_error=' . rawurlencode('Œuvre invalide.'));
    exit;
}

$result = (new MagazineRepository())->updateCatalogByOeuvreId($oeuvreId, [
    'numero' => (string) ($_POST['numero'] ?? ''),
    'date_parution' => (string) ($_POST['date_parution'] ?? ''),
    'pages' => (int) ($_POST['pages'] ?? 0),
    'est_hors_serie' => !empty($_POST['est_hors_serie']),
    'sommaire' => (string) ($_POST['sommaire'] ?? ''),
    'poster_url' => (string) ($_POST['poster_url'] ?? ''),
]);

if ($result !== true) {
    header('Location: ' . $returnUrl . '&edit=1&save_error=' . rawurlencode((string) $result));
    exit;
}

header('Location: ' . $returnUrl . '&saved=1');
exit;
