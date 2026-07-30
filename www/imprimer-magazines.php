<?php
/**
 * Version imprimable de Mes magazines (liste des séries, mêmes filtres que /magazines.php).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\MagazinePrintListService;
use Moncine\MagazineRepository;
use Moncine\MediaDomainGuards;
use Moncine\View;

MediaDomainGuards::ensureMagazineContext('/imprimer-magazines.php');

if (!MagazineRepository::isAvailable()) {
    header('Location: /magazines.php');
    exit;
}

View::render('imprimer-magazines', (new MagazinePrintListService())->viewDataForCollectionPrint($_GET));
