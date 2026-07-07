<?php
/**
 * Enregistre les liens magasins PC sur une fiche jeu catalogue.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\CatalogAdmin;
use Moncine\CatalogGameStoreLinks;
use Moncine\CatalogListContext;
use Moncine\Csrf;
use Moncine\View;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalogue.php');
    exit;
}

CatalogAdmin::denyUnlessAccess();

$oeuvreId = (int) ($_POST['oeuvre_id'] ?? 0);
$catalogListContext = CatalogListContext::fromQuery($_POST);
$returnUrl = View::oeuvreJeuUrl(
    $oeuvreId,
    $catalogListContext->search(),
    $catalogListContext->sortBy(),
    $catalogListContext->sortDir(),
    $catalogListContext->page(),
    $catalogListContext->mediaDomain()
);

Csrf::rejectUnlessValid($_POST, $returnUrl);

$error = (new CatalogGameStoreLinks())->saveFromPost($oeuvreId, $_POST);
if ($error !== null) {
    header('Location: ' . $returnUrl . '&store_links_error=' . rawurlencode($error));
    exit;
}

header('Location: ' . $returnUrl . '&store_links_saved=1');
exit;
