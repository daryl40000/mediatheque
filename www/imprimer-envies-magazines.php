<?php
/**
 * Version imprimable de Mes envies magazines (mêmes filtres que /magazines-envies.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazinePrintListService;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureMagazineContext('/imprimer-envies-magazines.php');

if (!MagazineRepository::isAvailable()) {
    header('Location: /magazines-envies.php');
    exit;
}

View::render('imprimer-envies-magazines', (new MagazinePrintListService())->viewDataForWishlistPrint($_GET));
