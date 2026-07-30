<?php
/**
 * Version imprimable de Mes envies BD (mêmes filtres que /bd-envies.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\BdPrintListService;
use Moncine\BdRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureBdContext('/imprimer-envies-bd.php');

if (!BdRepository::isAvailable()) {
    header('Location: /bd-envies.php');
    exit;
}

View::render('imprimer-envies-bd', (new BdPrintListService())->viewDataForWishlistPrint($_GET));
